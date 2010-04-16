<?PHP
/*
Copyright (C) 2009  Mickael Bailly

This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
*/

/**
* couch class
*
* basics to implement JSON / REST / HTTP CouchDB protocol
* 
*/
class couch {
	/**
	* @var string database source name
	*/
	protected $dsn = '';

	/**
	* @var array database source name parsed
	*/
	protected $dsn_parsed = null;

	/**
	* @var array couch options
	*/
	protected $options = null;
	/**
	* @var array allowed HTTP methods for REST dialog
	*/
	protected $HTTP_METHODS = array('GET','POST','PUT','DELETE','COPY');
	/**
	* @var resource HTTP server socket
	* @see _connect()
	*/
	protected $socket = NULL;

	/**
	* @var boolean tell if curl PHP extension has been detected
	*/
	protected $curl = FALSE;

	/**
	* class constructor
	*
	* @param string $dsn CouchDB Data Source Name
	*	@param array $options Couch options
	*/
	public function __construct ($dsn, $options = array() ) {
		$this->dsn = preg_replace('@/+$@','',$dsn);
		$this->options = $options;
		$this->dsn_parsed = parse_url($this->dsn);
		if ( !isset($this->dsn_parsed['port']) ) {
			$this->dsn_parsed['port'] = 80;
		}
		if ( function_exists('curl_init') )	$this->curl = TRUE;
	}

	/**
	* return a part of the data source name
	*
	* @param string $part part to return
	* @return string DSN part
	*/
	public function dsn_part($part) {
		if ( isset($this->dsn_parsed[$part]) ) {
			return $this->dsn_parsed[$part];
		}
	}

	/**
	* parse a CouchDB server response and sends back an array 
	* the array contains keys :
	* status_code : the HTTP status code returned by the server
	* status_message : the HTTP message related to the status code
	* body : the response body (if any). If CouchDB server response Content-Type is application/json
	*        the body will by json_decode()d
	*
	* @static
	* @param string $raw_data data sent back by the server
	* @param boolean $json_as_array is true, the json response will be decoded as an array. Is false, it's decoded as an object
	* @return array CouchDB response
	*/
	public static function parseRawResponse($raw_data, $json_as_array = FALSE) {
		if ( !strlen($raw_data) ) throw new InvalidArgumentException("no data to parse");
		if ( !substr_compare($raw_data, "HTTP/1.1 100 Continue\r\n\r\n", 0, 25) ) {
			$raw_data = substr($raw_data, 25);
		}
		$response = array('body'=>null);
		list($headers, $body) = explode("\r\n\r\n", $raw_data,2);
// 		echo "Headers : $headers , Body : $body\n";
		$headers_array=explode("\n",$headers);
		$status_line = reset($headers_array);
		$status_array = explode(' ',$status_line,3);
		$response['status_code'] = trim($status_array[1]);
		$response['status_message'] = trim($status_array[2]);
		if ( strlen($body) ) {
			$response['body'] = preg_match('@Content-Type:\s+application/json@i',$headers) ? json_decode($body,$json_as_array) : $body ;
		}
		return $response;
	}

	/**
	*send a query to the CouchDB server
	*
	* @param string $method HTTP method to use (GET, POST, ...)
	* @param string $url URL to fetch
	* @param array $parameters additionnal parameters to send with the request
	* @param string|array|object $data request body
	*
	* @return string|false server response on success, false on error
	*/
	public function query ( $method, $url, $parameters = array() , $data = NULL ) {
		if ( $this->curl )	return $this->_curl_query($method,$url,$parameters, $data);
		else				return $this->_socket_query($method,$url,$parameters, $data);
	}

	/**
	* record a file located on the disk as a CouchDB attachment
	*
	* @param string $url CouchDB URL to store the file to
	* @param string $file path to the on-disk file
	* @param string $content_type attachment content_type
	*
	* @return string server response
	*/
	public function storeFile ( $url, $file, $content_type ) {
		if ( $this->curl )	return $this->_curl_storeFile($url,$file,$content_type);
		else				return $this->_socket_storeFile($url,$file,$content_type);
	}

	/**
	* store some data as a CouchDB attachment
	*
	* @param string $url CouchDB URL to store the file to
	* @param string $data data to send as the attachment content
	* @param string $content_type attachment content_type
	*
	* @return string server response
	*/
	public function storeAsFile($url,$data,$content_type) {
		if ( $this->curl )	return $this->_curl_storeAsFile($url,$data,$content_type);
		else				return $this->_socket_storeAsFile($url,$data,$content_type);

	}

	/**
	*send a query to the CouchDB server
	*
	* In a continuous query, the server send headers, and then a JSON object per line.
	* On each line received, the $callable callback is fired, with two arguments :
	*
	* - the JSON object decoded as a PHP object
	*
	* - a couchClient instance to use to make queries inside the callback
	*
	* If the callable returns the boolean FALSE , continuous reading stops.
	*
	* @param callable $callable PHP function name / callable array ( see http://php.net/is_callable )
	* @param string $method HTTP method to use (GET, POST, ...)
	* @param string $url URL to fetch
	* @param array $parameters additionnal parameters to send with the request
	* @param string|array|object $data request body
	*
	* @return string|false server response on success, false on error
	*/
	public function continuousQuery($callable,$method,$url,$parameters = array(),$data = null) {
		if ( !in_array($method, $this->HTTP_METHODS )    )
			throw new Exception("Bad HTTP method: $method");
		if ( !is_callable($callable) ) 
			throw new InvalidArgumentException("callable argument have to success to is_callable PHP function");
		if ( is_array($parameters) AND count($parameters) )
			$url = $url.'?'.http_build_query($parameters);

		$request = $this->_socket_buildRequest($method,$url,$data);
		if ( !$this->_connect() )	return FALSE;
		fwrite($this->socket, $request);
		$response = '';
		$code=0;
		$headers = false;
		while (!feof($this->socket)&& !$headers) {
			$response.=fgets($this->socket);
			if (preg_match("/\r\n\r\n$/",$response) ) {
				$headers = true;
			}
		}
		$headers = explode("\n",trim($response));
		$split=explode(" ",trim(reset($headers)));
		$code = $split[1];
		unset($split);

		$c = clone $this;
		
		while ($this->socket && !feof($this->socket)) {
			$e = NULL;
			$e2 = NULL;
			$read = array($this->socket);
			if (false === ($num_changed_streams = stream_select($read, $e, $e2, 1))) {
				$this->socket = null;
			} elseif ($num_changed_streams > 0) {
				$line = fgets($this->socket);
				if ( strlen(trim($line)) ) {
					$break = call_user_func($callable,json_decode($line),$c);
					if ( $break === FALSE ) {
						fclose($this->socket);
					}
				}
			}
		}
		return $code;
	}

	/**
	*send a query to the CouchDB server
	*
	* @param string $method HTTP method to use (GET, POST, ...)
	* @param string $url URL to fetch
	* @param array $parameters additionnal parameters to send with the request
	* @param string|array|object $data request body
	*
	* @return string|false server response on success, false on error
	*/
	public function _socket_query ( $method, $url, $parameters = array() , $data = NULL ) {
		if ( !in_array($method, $this->HTTP_METHODS )    )
			throw new Exception("Bad HTTP method: $method");

		if ( is_array($parameters) AND count($parameters) )
			$url = $url.'?'.http_build_query($parameters);

		$request = $this->_socket_buildRequest($method,$url,$data);
		if ( !$this->_connect() )	return FALSE;
		$raw_response = $this->_execute($request);
		$this->_disconnect();

    //log_message('debug',"COUCH : Executed query $method $url");
    //log_message('debug',"COUCH : ".$raw_response);
		return $raw_response;
	}


	/**
	* returns first lines of request headers
	*
	* lines :
	* <code>
	* VERB HTTP/1.0
	* Host: my.super.server.com
	* Authorization: Basic...
	* Accept: application/json,text/html,text/plain,* /*
    * </code>
	*
	* @param string $method HTTP method to use
	* @param string $url the request URL
	* @return string start of HTTP request
	*/
	protected function _socket_startRequestHeaders($method,$url) {
		$req = "$method $url HTTP/1.0\r\nHost: ".$this->dsn_part('host')."\r\n";
		if ( $this->dsn_part('user') && $this->dsn_part('pass') ) {
		  $req .= 'Authorization: Basic '.base64_encode($this->dsn_part('user').':'.
		        	$this->dsn_part('pass'))."\r\n";
		}
		$req.="Accept: application/json,text/html,text/plain,*/*\r\n";

		return $req;
	}

	/**
	* build HTTP request to send to the server
	*
	* @param string $method HTTP method to use
	* @param string $url the request URL
	* @param string|object|array $data the request body. If it's an array or an object, $data is json_encode()d
	* @return string HTTP request
	*/
	protected function _socket_buildRequest($method,$url,$data) {
		if ( is_object($data) OR is_array($data) )
			$data = json_encode($data);
		$req = $this->_socket_startRequestHeaders($method,$url);

		if ( $method == 'COPY') {
			$req .= 'Destination: '.$data."\r\n\r\n";
		} elseif ($data) {
			$req .= 'Content-Length: '.strlen($data)."\r\n";
			$req .= 'Content-Type: application/json'."\r\n\r\n";
			$req .= $data."\r\n";
		} else {
			$req .= "\r\n";
		}
		return $req;
	}

	/**
	* record a file located on the disk as a CouchDB attachment
	* uses PHP socket API
	*
	* @param string $url CouchDB URL to store the file to
	* @param string $file path to the on-disk file
	* @param string $content_type attachment content_type
	*
	* @return string server response
	*/
	protected function _socket_storeFile($url,$file,$content_type) {

		if ( !strlen($url) )	throw new InvalidArgumentException("Attachment URL can't be empty");
		if ( !strlen($file) OR !is_file($file) OR !is_readable($file) )	throw new InvalidArgumentException("Attachment file does not exist or is not readable");
		if ( !strlen($content_type) ) throw new InvalidArgumentException("Attachment Content Type can't be empty");
		$req = $this->_socket_startRequestHeaders('PUT',$url);
		$req .= 'Content-Length: '.filesize($file)."\r\n"
				.'Content-Type: '.$content_type."\r\n\r\n";
		$fstream=fopen($file,'r');
		$this->_connect();
		fwrite($this->socket, $req);
		stream_copy_to_stream($fstream,$this->socket);
		$response = '';
		while(!feof($this->socket))
			$response .= fgets($this->socket);
		$this->_disconnect();
		fclose($fstream);
		return $response;
	}


	/**
	* store some data as a CouchDB attachment
	* uses PHP socket API
	*
	* @param string $url CouchDB URL to store the file to
	* @param string $data data to send as the attachment content
	* @param string $content_type attachment content_type
	*	
	* @return string server response
	*/
  public function _socket_storeAsFile($url,$data,$content_type) {
		if ( !strlen($url) )	throw new InvalidArgumentException("Attachment URL can't be empty");
		if ( !strlen($content_type) ) throw new InvalidArgumentException("Attachment Content Type can't be empty");

		$req = $this->_socket_startRequestHeaders('PUT',$url);
		$req .= 'Content-Length: '.strlen($data)."\r\n"
				.'Content-Type: '.$content_type."\r\n\r\n";
		$this->_connect();
		fwrite($this->socket, $req);
		fwrite($this->socket, $data);
		$response = '';
		while(!feof($this->socket))
			$response .= fgets($this->socket);
		$this->_disconnect();
		return $response;
  }

	/**
	*open the connection to the CouchDB server
	*
	*This function can throw an Exception if it fails
	*
	* @return boolean wheter the connection is successful
	*/
	protected function _connect() {
		$ssl = $this->dsn_part('scheme') == 'https' ? 'ssl://' : '';
		$this->socket = @fsockopen($ssl.$this->dsn_part('host'), $this->dsn_part('port'), $err_num, $err_string);
		if(!$this->socket) {
			throw new Exception('Could not open connection to '.$this->dsn_part('host').':'.$this->dsn_part('port').': '.$err_string.' ('.$err_num.')');
			return FALSE;
		}
		return TRUE;
	}

	/**
	*send the HTTP request to the server and read the response
	*
	* @param string $request HTTP request to send
	* @return string $response HTTP response from the CouchDB server
	*/
	protected function _execute($request) {
		fwrite($this->socket, $request);
		$response = '';
		while(!feof($this->socket))
			$response .= fgets($this->socket);
		return $response;
	}

	/**
	*closes the connection to the server
	*
	*
	*/
	protected function _disconnect() {
		@fclose($this->socket);
		$this->socket = NULL;
	}


	/**
	* build HTTP request to send to the server
	* uses PHP cURL API
	*
	* @param string $method HTTP method to use
	* @param string $url the request URL
	* @param string|object|array $data the request body. If it's an array or an object, $data is json_encode()d
	* @return resource CURL request resource
	*/
	protected function _curl_buildRequest($method,$url,$data) {
		$http = curl_init($url);
		$http_headers = array('Accept: application/json,text/html,text/plain,*/*') ;
		if ( is_object($data) OR is_array($data) )
			$data = json_encode($data);

		curl_setopt($http, CURLOPT_CUSTOMREQUEST, $method);

		if ( $method == 'COPY') {
			$http_headers[] = "Destination: $data";
		} elseif ($data) {
			curl_setopt($http, CURLOPT_POSTFIELDS, $data);
		}
		curl_setopt($http, CURLOPT_HTTPHEADER,$http_headers);
		return $http;
	}


	/**
	*send a query to the CouchDB server
	* uses PHP cURL API
	*
	* @param string $method HTTP method to use (GET, POST, ...)
	* @param string $url URL to fetch
	* @param array $parameters additionnal parameters to send with the request
	* @param string|array|object $data request body
	*
	* @return string|false server response on success, false on error
	*/
	public function _curl_query ( $method, $url, $parameters = array() , $data = NULL ) {
//  		echo "_curl_query : $method $url ".print_r($parameters,true)." , ".print_r($data,true);
		if ( !in_array($method, $this->HTTP_METHODS )    )
			throw new Exception("Bad HTTP method: $method");

		$url = $this->dsn.$url;
		if ( is_array($parameters) AND count($parameters) )
			$url = $url.'?'.http_build_query($parameters);
// 		echo $url;
		$http = $this->_curl_buildRequest($method,$url,$data);
		curl_setopt($http,CURLOPT_HEADER, true);
		curl_setopt($http,CURLOPT_RETURNTRANSFER, true);
		curl_setopt($http,CURLOPT_FOLLOWLOCATION, true);

		$response = curl_exec($http);
		curl_close($http);
// 		echo $response;

		return $response;
    //log_message('debug',"COUCH : Executed query $method $url");
    //log_message('debug',"COUCH : ".$raw_response);

	}

	/**
	* record a file located on the disk as a CouchDB attachment
	* uses PHP cURL API
	*
	* @param string $url CouchDB URL to store the file to
	* @param string $file path to the on-disk file
	* @param string $content_type attachment content_type
	*
	* @return string server response
	*/
	public function _curl_storeFile ( $url, $file, $content_type ) {
		if ( !strlen($url) )	throw new InvalidArgumentException("Attachment URL can't be empty");
		if ( !strlen($file) OR !is_file($file) OR !is_readable($file) )	throw new InvalidArgumentException("Attachment file does not exist or is not readable");
		if ( !strlen($content_type) ) throw new InvalidArgumentException("Attachment Content Type can't be empty");
		$url = $this->dsn.$url;
		$http = curl_init($url);
		$http_headers = array('Accept: application/json,text/html,text/plain,*/*','Content-Type: '.$content_type) ;
		curl_setopt($http, CURLOPT_PUT, 1);
		curl_setopt($http, CURLOPT_HTTPHEADER,$http_headers);
		curl_setopt($http, CURLOPT_UPLOAD, true);
		curl_setopt($http, CURLOPT_HEADER, true);
		curl_setopt($http, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($http, CURLOPT_FOLLOWLOCATION, true);
		$fstream=fopen($file,'r');
		curl_setopt($http, CURLOPT_INFILE, $fstream);
		curl_setopt($http, CURLOPT_INFILESIZE, filesize($file));
		$response = curl_exec($http);
		fclose($fstream);
		curl_close($http);
		return $response;
	}

	/**
	* store some data as a CouchDB attachment
	* uses PHP cURL API
	*
	* @param string $url CouchDB URL to store the file to
	* @param string $data data to send as the attachment content
	* @param string $content_type attachment content_type
	*
	* @return string server response
	*/
	public function _curl_storeAsFile($url,$data,$content_type) {
		if ( !strlen($url) )	throw new InvalidArgumentException("Attachment URL can't be empty");
		if ( !strlen($content_type) ) throw new InvalidArgumentException("Attachment Content Type can't be empty");
		$url = $this->dsn.$url;
		$http = curl_init($url);
		$http_headers = array('Accept: application/json,text/html,text/plain,*/*','Content-Type: '.$content_type,'Content-Length: '.strlen($data)) ;
		curl_setopt($http, CURLOPT_CUSTOMREQUEST, 'PUT');
		curl_setopt($http, CURLOPT_HTTPHEADER,$http_headers);
		curl_setopt($http, CURLOPT_HEADER, true);
		curl_setopt($http, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($http, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($http, CURLOPT_POSTFIELDS, $data);
		$response = curl_exec($http);
		curl_close($http);
		return $response;
	}

}

