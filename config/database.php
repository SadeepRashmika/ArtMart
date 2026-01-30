<?php
// config/database.php

class Database {
    private $host = "localhost";
    private $db_name = "artmart";
    private $username = "root";
    private $password = "";
    public $conn;

    public function getConnection() {
        $this->conn = null;
        try {
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->db_name,
                $this->username,
                $this->password
            );
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->exec("SET NAMES utf8");
        } catch(PDOException $exception) {
            echo "Database error: " . $exception->getMessage();
        }
        return $this->conn;
    }
}

// Optional helper
function getConnection() {
    $db = new Database();
    return $db->getConnection();
}
