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

exit;
?>