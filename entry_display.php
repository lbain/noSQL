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


  
  $qs_title = $_GET['title'];
  #echo "$title";

$delete =  "http://localhost/noSQL/deleteArticle.php?title=".$qs_title;
  
  
 $all_docs = $client->getAllDocs();
 #echo "Database got ".$all_docs->total_rows." documents.<BR>\n";
 #echo "<table id=\"hor-minimalist-b\">\n<tr><th>Title</th><th>Text</th><tr>\n\n";

 foreach ( $all_docs->rows as $row ) {
    #echo "".$row->id."<BR>\n";
    #echo "".$row->title."<BR>\n";

    
    /* this just gets an id so need to retrieve document */
    $doc = $client->getDoc($row->id);
    $title =  $doc->title;
    $body = $doc->body;

	if($title == $qs_title)
{
 	   echo "<tr><td><h1>$title</h1></td><td  >$body</td></tr>\n";
}
  }
?>
   </table>

	
	
	
	</div>
	
	<div style = "width: 300px; padding-top: 100px">
		<a href='index.php'>Back to main page</a>
		</br>
	<?php
		echo "<a href=\"$delete\">Delete this article</a>";
	?>
</div>
</div>
</body>
</html>
