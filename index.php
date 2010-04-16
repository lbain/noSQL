<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"
  "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
<head>
  <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
  <title>toDo</title>
  <link rel="stylesheet" type="text/css" href="style.css" />
</head>
<script type="text/javascript" src="calendarDateInput.js"></script>

<body>
<div id="wrap">

    
	<div id="main">
<!--
 <form method="post" action="index.php">
 <label for="title">Title:</label>
 <input type="text" id="title" name="title" /><br />
  <label for="body">Body:</label>
 <textarea id="body" name="body" rows = 10></textarea><br />
  <input type="submit" value="Publish" name="submit" />
 </form>
-->
  <?php

$couch_dsn = "http://localhost:5984/";
$couch_db = "nosql";


/**
* include the library
*/

require_once "couch.php";
require_once "couchClient.php";
require_once "couchDocument.php";

/**
* create the client
*/
$client = new couchClient($couch_dsn,$couch_db);


  /*
  
  if (isset($_POST['title']))
  {
     $title = $_POST['title'];
     $body = $_POST['body'];
     echo "<p> Added $title</p>";
   */
     /* create a new task document */
     $article = new StdClass();
     $article->_id = $title;
     $article->title = $title;
     $article->body = $body;
     
     /* now try to insert it */
	 /*
     try {
			$response = $client->storeDoc($article);
     } catch (Exception $e) {
	     echo "Error: ".$e->getMessage()." (errcode=".$e->getCode().")\n";
	     exit(1);
	 }
*/
      #print_r($response);
  /*          
     
  }
  */
 $all_docs = $client->getAllDocs();
 #echo "Database got ".$all_docs->total_rows." documents.<BR>\n";
 echo "<table id=\"hor-minimalist-b\">\n<tr><th>Title</th><th>Body</th><tr>\n\n";

 foreach ( $all_docs->rows as $row ) {
    #echo "".$row->id."<BR>\n";
    
    /* this just gets an id so need to retrieve document */
    $doc = $client->getDoc($row->id);
    $body =  $doc->body;
    $title = $doc->title;
    echo "<tr><td  >$title</td><td  >$body</td></tr>\n";
  }
  
 
  ?>
   </table>
   <button style="width:65; height:65" onClick="window.location='http://localhost/noSQL/addArticle.php'">Add an Article</button>
   </div>
</div>
</body>
</html>
