<?php 
// Atlanta Daniel
// May 2026
// db.php - Class for connecting to the database

class Database {

    //DB connection parameters
    private $host;
    private $dbname;
    private $username;
    private $password;
    
    //DB connection and error messages
    private $conn;
    private $conn_error = "";

    //Constructor
    //connect to the DB or set an error message if connection fails
    function __construct() {
        //Turn off error reporting since we handle errors manually
        mysqli_report(MYSQLI_REPORT_OFF);

        //local deployment
        $config = require __DIR__ . '/db_config_local.php';

        //live deployment
        //$config = require __DIR__ . '/db_config.php';
        
        $this->host = $config['host'];
        $this->dbname = $config['dbname'];
        $this->username = $config['username'];
        $this->password = $config['password'];
        
        //Connect to the database
        $this->conn = mysqli_connect($this->host, $this->username, $this->password, $this->dbname);

        //If the connection fails, set the error message
        if ($this->conn === false) {
            $this->conn_error = "Failed to connect to the database: " . mysqli_connect_error();
        }
    }

    function __destruct() {
        if ($this->conn) {
            mysqli_close($this->conn);
        }
    }

    //return the connection; if the connection failed, it will be false
    function getDbConn() {
        return $this->conn;
    }

    function getDbError() {
        return $this->conn_error;
    }

    //getters for DB connection parameters
    function getDbHost() {
        return $this->host;
    }

    function getDbName() {
        return $this->dbname;
    }

    function getDbUser() {
        return $this->username;
    }

    function getDbUserPw() {
        return $this->password;
    }
}