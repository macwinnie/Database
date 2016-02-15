<?php
require_once("../Database.class.php");
require_once("../DatabaseException.class.php");

use \Database\Database;
use \Database\DatabaseException as Exception;

require_once("config.php");
try {
	$db = new Database($dbhost, $dbname, $dbuser, $dbpass, $dbprefix);	
} catch (Exception $e) {
	echo $e->getMessage();
}

try {
	$sql = 'SELECT * FROM {table1} WHERE `id` = :i';
	echo '<pre>'.print_r($db->query($sql, array(':i'=>1)),true).'</pre>';

	echo '<pre>'.print_r($db->describe(),true).'</pre>';
} catch (\Exception $e) {
	echo '<h3>Exception</h3>'.$e->getMessage();
}