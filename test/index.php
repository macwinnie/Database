<?php
require_once("../Database.class.php");
require_once("../DatabaseException.class.php");

use \Database\Database;
use \Database\DatabaseException;

require_once("config.php");
try {
	$db = new Database($dbhost, $dbname, $dbuser, $dbpass, $dbprefix);	
} catch (Exception $e) {
	echo $e->getMessage();
}
