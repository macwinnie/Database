<?php
class Database{
    private $mysqli;
    private $connect_errno;
    private $connect_error;
    private $errno;
    private $error;
    private $cache;
    private $count;
    private $hostname;
    
    public function __construct(){
        $this->hostname = php_uname('n');
        $this->cache = array();
        require_once('config.php');
       	@$this->mysqli = new mysqli(DBHOST, DBUSER, DBPASS, DBNAME);
        if(!@$this->mysqli->stat()){
            echo json_encode(array("data" => array("errno" => $this->mysqli->connect_errno, "error" => $this->mysqli->connect_error), "error" => 2));
            $this->connect_errno = null;
            $this->connect_error = $this->mysqli->connect_error;
            exit;
        }
        if ($this->mysqli->connect_errno || !$this->mysqli) {
            echo json_encode(array("data" => array("errno" => $this->mysqli->connect_errno, "error" => $this->mysqli->connect_error), "error" => 2));
            $this->connect_errno = $this->mysqli->connect_errno;
            $this->connect_error = $this->mysqli->connect_error;
            exit;
        }
    }
    
    public function query($query, $params = array()){
        $sql = $this->prepare_query($query, $params);
        $result = $this->mysqli->query($sql);
        if(!is_null($result) && $this->mysqli->errno == 0){
            $this->errno = null;
            $this->error = null;
            $data_arr = array();
            $this->count = $result->num_rows;
            while ($row = $result->fetch_assoc()) {
                $data_arr[] = $row;
            }
            $result->close();
            return $data_arr;
        }else{
            $this->errno = $this->mysqli->errno;
            $this->error = $this->mysqli->error;
            return false;
        }
    }
    
    /*public function query_scalar($query, $params = array()){
    	$sql = $this->prepare_query($query, $params);
    	$result = $this->cacheout($sql, "scalar");
        if($result !== false){
            return $result;
        }elseif($result == false && $result = $this->mysqli->query($sql)){
            $this->errno = null;
            $this->error = null;
            $row = $result->fetch_array();
            $this->cachein($sql, "scalar", $row[0]);
            $result->close();
            return $row[0];
        }else{
            $this->errno = $this->mysqli->errno;
            $this->error = $this->mysqli->error;
            return false;
        }
    }*/
    
    public function query_scalar($query, $params = array()){
        $row = $this->query_row($query, $params);
        if($row === false){
            return false;
        }else{
            return $row[0];
        }
    }
    
    public function query_row($query, $params = array()){
    	$sql = $this->prepare_query($query, $params);
    	$result = $this->cacheout($sql, "row");
        if($result !== false){
            return $result;
        }elseif($result == false && $result = $this->mysqli->query($sql)){
            $this->errno = null;
            $this->error = null;
            $row = $result->fetch_array();
            $this->cachein($sql, "row", $row);
            $result->close();
            return $row;
        }else{
            $this->errno = $this->mysqli->errno;
            $this->error = $this->mysqli->error;
            return false;
        }
    }
    
    public function execute($query, $params = array()){
        $sql = $this->prepare_query($query, $params);
        if($this->mysqli->query($sql)){
            $this->errno = null;
            $this->error = null;
            return true;
        }else{
            $this->errno = $this->mysqli->errno;
            $this->error = $this->mysqli->error;
            return false;
        }
    }
    
    public function geterrno(){
        return $this->errno;
    }
    
    public function geterror(){
        return $this->error;
    }
    
    public function getconnerrno(){
        return $this->connect_errno;
    }
    
    public function getconnerror(){
        return $this->connect_error;
    }
    
    private function prepare_query($query, $params){
    	foreach($params as $key => $value){
    		if(is_array($value)){
    			$i=0;
    			foreach($value as $subval){
    				$value[$i] = $this->mysqli->real_escape_string($subval);
    				if(!is_int($value[$i])){
    					$value[$i] = "\"".$value[$i]."\"";
    				}
    				$i++;
    			}
    			$value = implode(", ", $value);
    		}else{
    			$value = $this->mysqli->real_escape_string($value);
    			if(!is_int($value)){
    				$value = "\"".$value."\"";
    			}
    		}
    		$query = preg_replace('#'.$key.'\b#', $value, $query);
    	}
    	$query = preg_replace("/\s\{(\w*)\}(\s|;|)/", " `".YPANEL_PREFIX."\\1`\\2", $query);
    	return $query;
    }
    
    private function cachein($sql, $type, $result){
        $this->cache[$sql][$type] = $result;
    }
    
    private function cacheout($sql, $type){
        if(array_key_exists($sql, $this->cache) && array_key_exists($type, $this->cache[$sql]) && !is_null($this->cache[$sql][$type])){
            $result = $this->cache[$sql][$type];
        }else{
            return false;
        }
        return $result;
    }
    
    public function getinfo(){
        return array("server" => array("stat" => $this->mysqli->stat, "version" => $this->mysqli->server_info, "info" => $this->mysqli->host_info, "thread_id" => $this->mysqli->thread_id), "connection" => array("sqlstate" => $this->mysqli->sqlstate, "protocol_versoin" => $this->mysqli->protocol_version), "client" => array("version" => $this->mysqli->client_version, "info" => $this->mysqli->client_info));
    }
    
    public function getlastid(){
        return $this->mysqli->insert_id;
    }
    
    public function getlastcount(){
        return $this->count;
    }
    
    public function getaffectedrows(){
        return $this->mysqli->affected_rows;
    }
    
    public function getfieldcount(){
        return $this->mysqli->field_count;
    }
    
    public function __destruct(){
        @$this->mysqli->close();
        unset($this->cache);
    }
}