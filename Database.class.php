<?php
class Database{
	private $mysqli;
	private $connect_errno;
	private $connect_error;
	private $errno;
	private $error;
	private $count;
	private $prefix;
	private $hostname;
	private $lastQuery;

	/**
	 * connects to database
	 * 
	 * @param host string databaseserver hostname
	 * @param database string name of database to be used
	 * @param user string user connecting to the database
	 * @param pass string password for user
	 * @param prefix string tableprefix
	 */
	public function __construct($host=NULL, $database=NULL, $user=NULL, $pass=NULL, $prefix=NULL){
        $this->hostname = php_uname('n');
	    @$this->mysqli = new mysqli($host, $user, $pass, $database);
	    if(!@$this->mysqli->stat() || $this->mysqli->connect_errno || !$this->mysqli){
            $this->connect_errno = $this->mysqli->connect_errno;
            $this->connect_error = $this->mysqli->connect_error;
			return;
        }
		$this->connect_errno = null;
		$this->connect_error = null;
		$this->prefix = $prefix;
	}
	
	/**
	 * executes datafetching SQL-Query
	 * 
	 * @param query string SQL-Query to be executed
	 * @param params array Array of parameters to be inserted into the SQL-Query
	 *
	 * @return bool|array Returns the Query-Result
	 */
	public function query($query, $params = array()){
	    $sql = $this->prepare_query($query, $params);
	    $result = $this->mysqli->query($sql);
	    if (!is_null($result) && $this->mysqli->errno == 0) {
	        $this->errno = null;
	        $this->error = null;
	        $data_arr = array();
	        $this->count = $result->num_rows;
	        while ($row = $result->fetch_assoc()) {
	            $data_arr[] = $row;
	        }
	        $result->close();
	        return $data_arr;
	    } else {
	        $this->errno = $this->mysqli->errno;
	        $this->error = $this->mysqli->error;
	        return false;
	    }
	}
	/**
	 * executes datafetching SQL-Query
	 * 
	 * @param query string SQL-Query to be executed
	 * @param params array Array of parameters to be inserted into the SQL-Query
	 *
	 * @return mixed returns the one single value of SQL-Query
	 */
	public function query_scalar($query, $params = array()){
        $row = $this->query_row($query, $params, false);
        if($row === false){
            return false;
        }else{
            return $row[0];
        }
    }

	/**
	 * executes datafetching SQL-Query
	 * 
	 * @param query string SQL-Query to be executed
	 * @param params array Array of parameters to be inserted into the SQL-Query
	 *
	 * @return bool|array Returns one line / one Object as result of SQL-Query
	 */
	public function query_row($query, $params = array(), $assoc = true){
		$sql = $this->prepare_query($query, $params);
	    if($result = $this->mysqli->query($sql)){
	        $this->errno = null;
	        $this->error = null;
	        if ($assoc) {
	            $row = $result->fetch_assoc();
	        }
	        else {
		        $row = $result->fetch_array();
	        }
	        $result->close();
	        return $row;
	    }else{
	        $this->errno = $this->mysqli->errno;
	        $this->error = $this->mysqli->error;
	        return false;
	    }
	}
	
	/**
	 * executes SQL-Query without fetching data
	 * 
	 * @param query string SQL-Query to be executed
	 * @param params array Array of parameters to be inserted into the SQL-Query
	 *
	 * @return bool returns success of SQL-Query
	 */
	public function execute($query, $params = array()){
	    $sql = $this->prepare_query($query, $params);
	    if ($this->mysqli->multi_query($sql)) {
	        $this->errno = null;
	        $this->error = null;
	        return true;
	    } else {
	        $this->errno = $this->mysqli->errno;
	        $this->error = $this->mysqli->error;
	        return false;
	    }
	}
	
	/**
	 * get the number of last SQL-Error
	 * 
	 * @param void
	 * @return integer returns the number of last SQL-Error
	 */
	public function geterrno(){
	    return $this->errno;
	}
	
	/**
	 * get the description of the last SQL-Error
	 * 
	 * @param void
	 * @return string returns the description of the last SQL-Error
	 */
	public function geterror(){
	    return $this->error;
	}
	
	/**
	 * get the number of last Connection-Error
	 * 
	 * @param void
	 * @return integer returns the number of last Connection-Error
	 */
	public function getconnerrno(){
	    return $this->connect_errno;
	}
	
	/**
	 * get the description of the last Connection-Error
	 * 
	 * @param void
	 * @return string returns the description of the last Connection-Error
	 */
	public function getconnerror(){
	    return $this->connect_error;
	}
	
	/**
	 * replaces placeholders within SQL-Queries with escaped values
	 * 
	 * @param query string SQL-Query to be executed
	 * @param params array Array of parameters to be inserted into the SQL-Query
	 *
	 * @return string returns the SQL-Query with inserted Params
	 */
	public function prepare_query($query, $params){
		if(substr(trim($query), -1) != ";"){
    		$query .= ";";
    	}
		foreach ($params as $key => $value) {
			if (is_array($value)) {
				$i=0;
				foreach ($value as $subval) {
					$value[$i] = $this->mysqli->real_escape_string($subval);
					if(!is_int($value[$i])){
						$value[$i] = "\"".$value[$i]."\"";
					}
					$i++;
				}
				$value = implode(", ", $value);
			} else {
				if ($value === null) {
					$value = "NULL";
				}

				if (!is_int($value) && $value !== "NULL") {
					$value = $this->mysqli->real_escape_string($value);
					$value = "\"".$value."\"";
				}
			}
			$query = preg_replace('#' . $key . '\b#', $value, $query);
		}
		$query = preg_replace("/\s\{(\w*)\}(\s|;|)/", " `".$this->prefix."\\1`\\2", $query);
		$this->lastQuery = $query;
		return $query;
	}
	
	/**
	 * get information about MySQL-Connection
	 * 
	 * @param void
	 * @return array returns information about MySQL-Connection
	 */
	public function getinfo(){
        return array("server" => array("stat" => $this->mysqli->stat, "version" => $this->mysqli->server_info, "info" => $this->mysqli->host_info, "thread_id" => $this->mysqli->thread_id), "connection" => array("sqlstate" => $this->mysqli->sqlstate, "protocol_versoin" => $this->mysqli->protocol_version), "client" => array("version" => $this->mysqli->client_version, "info" => $this->mysqli->client_info));
    }
	
	/**
	 * get the primary key of last inserted value (primary key has to be AI)
	 * 
	 * @param void
	 * @return integer returns the primary key oflast inserted value
	 */
	public function getlastid() {
	    return $this->mysqli->insert_id;
	}
	
	/**
	 * get the number of Query-Results
	 * 
	 * @param void
	 * @return integer returns the number of Query-Results
	 */
	public function getcount() {
	    return $this->count;
	}
	
	/**
	 * get the number of affected rows
	 * 
	 * @param void
	 * @return integer returns the number of affected rows
	 */
	public function getaffectedrows(){
        return $this->mysqli->affected_rows;
    }
	
	/**
	 * get the number of columns for the most recent query
	 * 
	 * @param void
	 * @return integer returns the number of columns for the most recent query
	 */
	public function getfieldcount() {
	    return $this->mysqli->field_count;
	}
	
	/**
	 * get the most recent executed query
	 * 
	 * @param void
	 * @return string returns the most recent executed query
	 */
	public function getLastQuery() {
		return $this->lastQuery;
	}
	
	/**
	 * blast the database-connection
	 * 
	 * @param void
	 */
	public function __destruct(){
		if (!$this->getconnerrno()) {
		    $this->mysqli->close();
		}
	}
	
	/**
	 * function for fetching ENUM-Possibilities of field
	 *
	 * @param $table name of table without prefixes
	 * @param $field name of the field to be requested
	 */
	public function getEnumValues( $table, $field ) {
		$sql = "SHOW COLUMNS FROM {".$table."} WHERE Field = '".$field."'";
	    $result = $this->query_row( $sql );
	    $type = $result['Type'];
	    preg_match('/^enum\((.*)\)$/', $type, $matches);
	    foreach( explode(',', $matches[1]) as $value ) {
	         $enum[] = trim( $value, "'" );
	    }
	    return $enum;
	}
}
