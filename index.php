<?php
require_once("Database.class.php");
$db = new Database;
if(intval($db->getconnerrno) != 0 || intval($db->getconnerror) != 0){
	echo $db->getconnerrno().": ".$db->getconnerror();
	exit;
}
