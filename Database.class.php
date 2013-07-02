<?php
class Database {
  private $mysqli;
  private $connect_errno;
  private $connect_error;
  private $errno;
  private $error;
  private $count;
  private $prefix;
  private $hostname;
  private $lastQuery;

  public function __construct($host = NULL, $database = NULL, $user = NULL, $pass = NULL, $prefix = NULL) {
    $this->hostname = php_uname('n');
    @$this->mysqli = new mysqli($host, $user, $pass, $database);
    if (!@$this->mysqli->stat() || $this->mysqli->connect_errno || !$this->mysqli) {
      $this->connect_errno = $this->mysqli->connect_errno;
      $this->connect_error = $this->mysqli->connect_error;
      return;
    }
    $this->connect_errno = null;
    $this->connect_error = null;
    $this->prefix = $prefix;
    return;
  }

  public function query($query, $params = array()) {
    $sql = $this->prepare_query($query, $params);
    $result = @$this->mysqli->query($sql);
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

  public function query_scalar($query, $params = array()) {
    $row = $this->query_row($query, $params, false);
    if ($row === false) {
      return false;
    } else {
      return $row[0];
    }
  }

  public function query_row($query, $params = array(), $assoc = true) {
    $sql = $this->prepare_query($query, $params);
    if ($result = $this->mysqli->query($sql)) {
      $this->errno = null;
      $this->error = null;
      if ($assoc) {
        $row = $result->fetch_assoc();
      } else {
        $row = $result->fetch_array();
      }
      $result->close();
      return $row;
    } else {
      $this->errno = $this->mysqli->errno;
      $this->error = $this->mysqli->error;
      return false;
    }
  }

  public function execute($query, $params = array()) {
    $sql = $this->prepare_query($query, $params);
    if ($this->mysqli->query($sql)) {
      $this->errno = null;
      $this->error = null;
      return true;
    } else {
      $this->errno = $this->mysqli->errno;
      $this->error = $this->mysqli->error;
      return false;
    }
  }

  public function geterrno() {
    return $this->errno;
  }

  public function geterror() {
    return $this->error;
  }

  public function getconnerrno() {
    return $this->connect_errno;
  }

  public function getconnerror() {
    return $this->connect_error;
  }

  public function prepare_query($query, $params) {
    if (substr(trim($query), -1) != ";") {
      $query .= ";";
    }
    foreach ($params as $key => $value) {
      if (is_array($value)) {
        $i = 0;
        foreach ($value as $subval) {
          $value[$i] = $this->mysqli->real_escape_string($subval);
          if (!is_int($value[$i])) {
            $value[$i] = "\"" . $value[$i] . "\"";
          }
          $i++;
        }
        $value = implode(", ", $value);
      } else {
        if ($value === null) {
          $value = "NULL";
        }

        if (!is_int($value) && $value !== "NULL") {
          $value = @$this->mysqli->real_escape_string($value);
          $value = "\"" . $value . "\"";
        }
      }
      $query = preg_replace('#' . $key . '\b#', $value, $query);
    }
    $query = preg_replace("/\s\{(\w*)\}(\s|;|)/", " `" . $this->prefix . "\\1`\\2", $query);
    $this->lastQuery = $query;
    return $query;
  }

  public function getinfo() {
    return array("server" => array("stat" => $this->mysqli->stat, "version" => $this->mysqli->server_info, "info" => $this->mysqli->host_info, "thread_id" => $this->mysqli->thread_id), "connection" => array("sqlstate" => $this->mysqli->sqlstate, "protocol_versoin" => $this->mysqli->protocol_version), "client" => array("version" => $this->mysqli->client_version, "info" => $this->mysqli->client_info));
  }

  public function getlastid() {
    return $this->mysqli->insert_id;
  }

  public function getcount() {
    return $this->count;
  }

  public function getaffectedrows() {
    return $this->mysqli->affected_rows;
  }

  public function getfieldcount() {
    return $this->mysqli->field_count;
  }

  public function getLastQuery() {
    return $this->lastQuery;
  }

  public function __destruct() {
    @$this->mysqli->close();
  }

}
