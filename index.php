<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"
  "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
<head>
  <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
  <title>toDo</title>
  <link rel="stylesheet" type="text/css" href="style.css" />
</head>
<script type="text/javascript" src="calendarDateInput.js" />

<body>
<div id="wrap">

    
	<div id="main">
	
 <form method="post" action="index.php">
 <label for="item">Task:</label>
 <input type="text" id="item" name="item" /><br />
  <label for="priority">priority:</label>
 <input type="text" id="priority" name="priority" /><br />
  <input type="submit" value="add" name="submit" />
 </form>

  <?php

$couch_dsn = "http://localhost:5984/";
$couch_db = "example";


/**
* include the library
*/

require_once "../lib/couch.php";
require_once "../lib/couchClient.php";
require_once "../lib/couchDocument.php";

/**
* create the client
*/
$client = new couchClient($couch_dsn,$couch_db);


  
  
  if (isset($_POST['item']))
  {
     $task = $_POST['item'];
     $priority = $_POST['priority'];
     echo "<p> Added $task</p>";
   
     /* create a new task document */
     $todoTask = new StdClass();
     $todoTask->_id = $task;
     $todoTask->task = $task;
     $todoTask->priority = $priority;
     
     /* now try to insert it */
     try {
			$response = $client->storeDoc($todoTask);
     } catch (Exception $e) {
	     echo "Error: ".$e->getMessage()." (errcode=".$e->getCode().")\n";
	     exit(1);
	 }

      #print_r($response);
            
     
  }
  
 $all_docs = $client->getAllDocs();
 #echo "Database got ".$all_docs->total_rows." documents.<BR>\n";
 echo "<table id=\"hor-minimalist-b\">\n<tr><th>Task</th><th>Priority</th><tr>\n\n";

 foreach ( $all_docs->rows as $row ) {
    #echo "".$row->id."<BR>\n";
    
    /* this just gets an id so need to retrieve document */
    $doc = $client->getDoc($row->id);
    $priority =  $doc->priority;
    $task = $doc->task;
    echo "<tr><td  >$task</td><td  >$priority</td></tr>\n";
  }
  
 
  ?>
   </table>

	
	
	
	</div>
	
	
   

	
</div>
</body>
</html>
