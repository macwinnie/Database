<?php

namespace Database;
use \Database\DatabaseException as Exception;
use \PDO;
use \PDOException;

/**
 * class for MySQL-Connections via PDO
 *
 * @param String        $prefix           table prefix
 * @param PDO           $connection       DB connection for class instance
 * @param String[]      $exceptions       occured errors
 * @param PDOStatement  $statement        statement for query-functions
 * @param Integer       $queryCount       how many resulting rows did the query have?
 * @param Integer       $fieldCount       how many fields does the query result have?
 */
class Database {

	private $prefix          = '';
	private $connection      = null;
	private $exceptions      = array();
	private $statement       = null;
	private $queryCount      = null;
	private $fieldCount      = null;

	/**
	 * setup database connection
	 *
	 * @param String $host     DB host
	 * @param String $database DB database
	 * @param String $user     DB user
	 * @param String $pass     DB user password
	 * @param String $prefix   table prefix
	 */
	public function __construct($host=NULL, $database=NULL, $user=NULL, $pass=NULL, $prefix=NULL) {
		if (PHP_VERSION_ID < 50100) {
			// PDO only defined with PHP > 5.1.0
			throw new Exception('actually PHP-Version '.PHP_VERSION.' is too low &ndash; 5.1.0 at least needed for using PDO', 1);
		}
	    $this->prefix = $prefix;
	    $dsn = 'mysql:host=' . $host . ';dbname=' . $database;
	    $options = array (
	    	// make connections persistent
			PDO::ATTR_PERSISTENT => true,
			// throw exceptions if an error occurs
			PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
		);
		try {
			// setup connection to database / create new PDO instance
			$this->connection = new PDO($dsn, $user, $pass, $options);
		} catch (PDOException $e) {
			// catch and store any errors
			$this->setException($e->getMessage());
			throw new Exception ($e->getMessage(), 2);
		}
	}

	/**
	 * fetch the prefix of current database-connection
	 *
	 * @param  void
	 * @return string prefix
	 */
	public function getPrefix () {
		return $this->prefix;
	}

	/**
	 * bind an value to the statement
	 * 
	 * @param  String $param  string used as placeholder within statement
	 * @param  mixed  $value  value to insert into database
	 * @param  String $type   type
	 * @return void
	 */
	public function bind ($param, $value, $type = null) {
		if (
			// check if no type is given
			is_null($type)
			or
			// check if given type is one of the relevant value types
			!in_array(
				$type,
				array(
					PDO::PARAM_NULL,
					PDO::PARAM_INT,
					PDO::PARAM_BOOL,
					PDO::PARAM_STR,
					PDO::PARAM_LOB,
					PDO::PARAM_STMT,
				)
			)
		) {
			// check parameter for type
			switch (true) {
				case is_null($value): // NULL
					$type = PDO::PARAM_NULL;
					break;
				case is_int($value): // Integer
					$type = PDO::PARAM_INT;
					break;
				case is_bool($value): // Boolean
					$type = PDO::PARAM_BOOL;
					break;
				default: // String
					$type = PDO::PARAM_STR;
					break;
			}
		}
		// check if statement is a PDOStatement, so parameters can be bound
		if (is_a($this->statement, 'PDOStatement')) {
			// bind value to statement
			$this->statement->bindValue($param, $value, $type);
		}
		else {
			// statement is no valid PDOStatement
			throw new Exception('no valid statement set to bind parameters', 3);
		}
	}

	/**
	 * prepare a query with the PDO-Class to be a PDOStatement
	 *
	 * @param  String  $query  query to be prepared
	 * @return void
	 */
	protected function prepareQuery ($query) {
		// RegEx: {tableName} will be replaced by `prefix_tableName`
		$query = preg_replace ( '/\s\{(\w*)\}(\s|;|)/', ' `' . $this->prefix . "\\1`\\2", $query);
		// finally prepare as PDOStatement
		$this->statement = $this->connection->prepare($query);
	}

	/**
	 * bind given array of parameters to statement
	 *
	 * @param  array[]  $params  array with entrys  param => value
	 * @return void
	 */
	private function bindParams ($params = array()) {
		if (!is_null($params) and !empty($params)) {
			$keys = array_keys($params, null, true);
			if (is_array($keys) and !empty($keys)) {
				// prepare query so NULL is no more a problem ...
				$query = $this->statement->queryString;
				foreach ($keys as $key) {
					$query = preg_replace('#!=\s*?' . $key . '\b#', 'NOT IS NULL', $query);
					$query = preg_replace('#=\s*?' . $key . '\b#', 'IS NULL', $query);
				}
				$this->statement = $this->connection->prepare($query);
			}
			foreach ($params as $param => $value) {
				try {
					// only bind parameter, if used with query!
					if (strpos($this->statement->queryString, $param) !== false) {
						// try to bind the value to param within statement
						$this->bind($param, $value);
					}
				} catch (Exception $e) {
					// statement not propperly set, so it is reset to null
					$this->setException($e->getMessage());
					$this->statement = null;
					throw $e;
				}
			}
		}
	}

	/**
	 * check for valid fetchStyle
	 * @param  mixde  &$fetchStyle  Boolean: return result as object?
	 *                              PDO::FETCH_* for using special fetch-method
	 * @return void
	 */
	private function checkFetchStyle (&$fetchStyle) {
		// if $fetchStyle is boolean
		if (is_bool($fetchStyle)) {
			$fetchStyle = ($fetchStyle) ? PDO::FETCH_OBJ : PDO::FETCH_ASSOC;
		}
		// check if given fetchStyle is a valid one
		elseif (
			!in_array(
				$fetchStyle,
				array(
					PDO::FETCH_OBJ,
					PDO::FETCH_CLASS,
					PDO::FETCH_COLUMN,
					PDO::FETCH_BOUND,
					PDO::FETCH_BOTH,
					PDO::FETCH_NUM,
					PDO::FETCH_NAMED,
					PDO::FETCH_ASSOC,
				)
			)
		) {
			$fetchStyle = PDO::FETCH_ASSOC;
		}
	}

	/**
	 * fetch the errors occured until the execution
	 *
	 * @param  Boolean   $reset  if true, the array of errors will be reset
	 * @return String[]          array of error-messages
	 */
	public function fetchErrors ($reset = false) {
		$return = $this->exceptions;
		if ($reset) {
			$this->exceptions = array();
		}
		return $return;
	}

	private function setException ($errorMsg) {
		$this->exceptions[] = $errorMsg;
	}

	/**
	 * execute the current statement or place and execute an DELETE, UPDATE or INSERT statement
	 *
	 * @param  String  $query   query that should be executed
	 * @param  mixed   $params  params array to be bound at PDOStatement – param => value
	 *
	 * @return Boolean          true on success, false on failure
	 */
	public function execute ($query = null, $params = null) {
		if ($query !== null) {
			$this->prepareQuery($query);
			$this->bindParams($params);
		}
		if (is_a($this->statement, 'PDOStatement')) {
			try {
				$return = $this->statement->execute();
			} catch (PDOException $e) {
				$this->setException($e->getMessage());
				throw $e;
			}
		}
		else {
			throw new Exception('no valid statement set to execute', 5);
		}
		return $return;
	}

	/**
	 * function for bundeling procedures used by all query-functions
	 *
	 * @param String   $query        query that should be executed
	 * @param mixed    $params       params array to be bound at PDOStatement – param => value
	 * @param mixed    &$fetchStyle  Boolean: return result as object?
	 *                               PDO::FETCH_* for using special fetch-method
	 *
	 * @return void
	 */
	private function preQuery ($query, $params = array(), &$fetchStyle) {
		$this->prepareQuery($query);
		$this->bindParams($params);
		$this->checkFetchStyle($fetchStyle);
		try {
			$this->execute();
		} catch (PDOException $e) {
			$this->setException($e->getMessage());
			throw $e;
		}
	}

	/**
	 * executes query for fetching multiple rows of datasets
	 *
	 * @param String   $query       query that should be executed
	 * @param mixed    $params      params array to be bound at PDOStatement – param => value
	 * @param mixed    $fetchStyle  Boolean: return result as object?
	 *                              PDO::FETCH_* for using special fetch-method
	 *
	 * @return mixed 				result of query – using fetchStyle
	 */
	public function query ($query, $params = array(), $fetchStyle = false) {
		try {
			$this->preQuery($query, $params, $fetchStyle);
			$return = $this->statement->fetchAll($fetchStyle);
			if ($return !== false) {
				$this->queryCount = count($return);
				$this->fieldCount = count(reset($return));
			}
			return $return;
		} catch (PDOException $e) {
			$this->setException($e->getMessage());
		}
	}

	/**
	 * executes query for fetching one single row with one dataset
	 *
	 * @param String   $query       query that should be executed
	 * @param mixed    $params      params array to be bound at PDOStatement – param => value
	 * @param mixed    $fetchStyle  Boolean: return result as object?
	 *                              PDO::FETCH_* for using special fetch-method
	 *
	 * @return mixed 				result of query – using fetchStyle
	 */
	public function queryRow ($query, $params = array(), $fetchStyle = false) {
		try {
			$this->preQuery($query, $params, $fetchStyle);
			$return = $this->statement->fetch($fetchStyle);
			if ($return !== false) {
				$this->queryCount = 1;
				$this->fieldCount = count($return);
			}
			return $return;
		} catch (PDOException $e) {
			$this->setException($e->getMessage());
		}
	}

	/**
	 * executes query for fetching the first value out of one single dataset
	 *
	 * @param String   $query       query that should be executed
	 * @param mixed    $params      params array to be bound at PDOStatement – param => value
	 * @param Integer  $selectcol   number of the column whose value should be selected, 0 default
	 *
	 * @return mixed 		result of query
	 */
	public function queryScalar ($query, $params = array(), $selectcol = 0) {
		$row = $this->query_row($query, $params, PDO::FETCH_NUM);
		if (isset($row[$selectcol])) {
			$this->fieldCount = 1;
			return $row[$selectcol];
		}
		else {
			$this->setException('Column to be selected is not defined');
		}
		return $return;
	}

	/**
	 * executes query for fetching one single column out of all selected datasets
	 *
	 * @param String   $query       query that should be executed
	 * @param mixed    $params      params array to be bound at PDOStatement – param => value
	 * @param Integer  $selectcol   number of the column whose values should be selected, 0 default
	 *
	 * @return mixed 		result of query
	 */
	public function queryColumn ($query, $params = array(), $selectcol = 0) {
		$fetchStyle = false;
		$all = $this->query($query, $params, PDO::FETCH_NUM);
		$return = array();
		$this->fieldCount = 1;
		foreach ($all as $i => $row) {
			if (isset($row[$selectcol])) {
				$return[] = $row[$selectcol];
			}
			else {
				$this->setException('Column to be selected is not defined in row ' . $i);
			}
		}
		return $return;
	}

	/**
	 * get the code of the error of last action with PDOStatement
	 *
	 * @return Integer  returns the number of last SQL-Error
	 */
	public function getErrno() {
	    return $this->statement->errorCode();
	}

	/**
	 * get the code of the error of last action with PDOStatement
	 *
	 * @param  boolean $array  should the error be returned as an array?
	 * @return String          returns the description
	 */
	public function getError($array = false) {
		$error = $this->statement->errorInfo();
		if ($error[0] == '00000') {
			return null;
		}
		if ($array) {
			return $error;
		}
	    return print_r($error, true);
	}

	/**
	 * fetch the ID of last inserted value
	 *
	 * @return mixed   regularly Integer
	 */
	public function lastInsertId () {
		return $this->connection->lastInsertId();
	}

	/**
	 * Count affected rows
	 * Use only for DELETE, UPDATE or INSERT statements!
	 * Don't use for counting query_results!
	 *
	 * @return Integer
	 */
	public function rowCount(){
		return $this->statement->rowCount();
	}

	/**
	 * get number of returned datasets of last statement
	 *
	 * @return Integer
	 */
	public function getCount () {
		if (!is_a($this->statement, 'PDOStatement') or $this->queryCount === null) {
			throw new Exception('no valid PDOStatement or query results not counted', 6);
		}
		return $this->queryCount;
	}

	/**
	 * get the number of fields of previously ran query
	 *
	 * @return Integer
	 */
	public function getFieldCount () {
		if (!is_a($this->statement, 'PDOStatement') or $this->fieldCount === null) {
			throw new Exception('no valid PDOStatement or query results not counted', 7);
		}
		return $this->fieldCount;
	}

	/**
	 * begin a transaction
	 *
	 * @return Boolean  true on success, false on failure
	 */
	public function beginTransaction () {
		return $this->connection->beginTransaction();
	}

	/**
	 * end a transaction
	 *
	 * @return Boolean  true on success, false on failure
	 */
	public function endTransaction () {
		return $this->connection->commit();
	}

	/**
	 * rollback a transaction
	 *
	 * @return Boolean  true on success, false on failure
	 */
	public function rollBack () {
		return $this->connection->rollBack();
	}

	/**
	 * return all debug-information of PDOStatement
	 *
	 * @return String
	 */
	public function debugDumpParams () {
		ob_start();
		$this->statement->debugDumpParams();
		$debug = ob_get_contents();
		ob_end_clean();
		return $debug;
	}

	/**
	 * Describe current Database
	 *
	 * @return String[] description of actual Database
	 */
	public function describe () {
		$query1 = 'SHOW TABLES';
		$DBtables = $this->query($query1);
		if ($DBtables!=null and !empty($DBtables)) {
			foreach ($DBtables as $table) {
				if (count($table) != 1) {
					throw new Exception('attribute-count while description failed', 8);
				}
				foreach ($table as $key => $value) {
					$tn = $key;
				}
				$query2 = 'DESCRIBE `'.$table[$tn].'`';
				$describe[$table[$tn]] = $this->query($query2);
			}
			return $describe;
		}
		else {
			throw new Exception('no tables to descirbe', 9);
		}
	}

	/**
	 * alias for backwardscompatibility
	 */
	public function query_scalar ($query, $params = array()) {
		return $this->queryScalar($query, $params);
	}

	/**
	 * alias for backwardscompatibility
	 */
	public function query_row ($query, $params = array(), $fetchStyle = false) {
		return $this->queryRow($query, $params, $fetchStyle);
	}

	/**
	 * alias for backwardscompatibility
	 */
	private function prepare_query ($query) {
		return $this->prepareQuery($query);
	}

	/**
	 * alias for backwardscompatibility
	 */
	public function begin_transaction () {
		return $this->beginTransaction();
	}

	/**
	 * alias for backwardscompatibility
	 */
	public function end_transaction () {
		return $this->endTransaction();
	}

	/**
	 * alias for backwardscompatibility
	 */
	public function commit () {
		return $this->endTransaction();
	}

	/**
	 * alias for backwardscompatibility
	 */
	public function getlastid () {
		return $this->lastInsertId();
	}

	/**
	 * alias for backwardscompatibility
	 */
	public function getaffectedrows () {
		return $this->rowCount();
	}
}
