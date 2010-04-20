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

require_once "couch.php";
require_once "couchClient.php";
require_once "couchDocument.php";

$client = new couchClient($couch_dsn,$couch_db);

if (!isset($_POST['searchid'])) {
	//Redirect to index
	echo('<meta http-equiv="refresh" content="0;url=index.php">');
	exit;
}

$doc = $client->getDoc($_POST['searchid']);
    $body =  $doc->body;
    $title = $doc->title;
    echo "<tr><td  ><a href='entry_display.php?title=$title'>$title</a></td><td  >$body</td></tr>\n";

?>
  </table>
   </div>
</div>
</body>
</html>