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


// check for document in database, create new if not there
  try
  {
  $article = $client->getDoc($_REQUEST['title']);
  }
  catch (Exception $e)
  {
	$article = new StdClass();
  }
  
  if (isset($_POST['title']))
  {
     $title = $_POST['title'];
     $body = $_POST['body'];
     echo "<p> Added $title</p>";
   
     /* create a new task document */
     //$article = new StdClass();
     $article->_id = $title;
     $article->title = $title;
     $article->body = $body;
     
     /* now try to insert it */
     try {
			$response = $client->storeDoc($article);
			header("Location: index.php?page=".$editArticle->_id);
     } catch (Exception $e) {
	     echo "Error: ".$e->getMessage()." (errcode=".$e->getCode().")\n";
	     exit(1);
	 }

      #print_r($response);
            
     
  }
  ?>

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
	<!-- value inbetween textareas to edit -->
 <form method="post" action="editArticle.php">
 <label for="title">Title:</label>
 <input type="text" id="title" name="title" value="<?php echo $article->title;?>" /><br />
  <label for="body">Body:</label>
 <textarea id="body" name="body" rows = 10><?php echo $article->body;?></textarea><br />
  <input type="submit" value="Done Editing" name="submit" />
 </form>  
  
  
  
   <button style="width:65; height:65" onClick="window.location='http://localhost/noSQL/index.php'">See all Articles</button>
   </table>
</div>
</div>
</body>
</html>