<?php
require_once("Database.class.php");
require_once("config.php");
$db = new Database($dbhost, $dbname, $dbuser, $dbpass, $dbprefix);
if(intval($db->getconnerrno()) != 0 || $db->getconnerror()){
	echo $db->getconnerrno().": ".$db->getconnerror();
	exit;
}