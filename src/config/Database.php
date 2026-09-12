<?php

class Database{
    
    private $db_service;
    private $host;
    private $db_name;
    private $username;
    private $password;
    private $conn;

    public function __construct(){
        $this->db_service = $_ENV['DB_SERVICE'] ?: 'mysql';
        $this->host = $_ENV['DB_HOST'] ?: 'localhost';
        $this->db_name = $_ENV['DB_NAME'] ?: 'prueba';
        $this->username = $_ENV['DB_USER'] ?: 'root';
        $this->password = $_ENV['DB_PASSWORD'] ?: '';
    }

    public function getConnection(){
        $this->conn = null;

        try{
            $dsn = $this->db_service . ":host=" . $this->host . ";dbname=" . $this->db_name. ";charset=utf8";

            $this->conn = new PDO($dsn, $this->username, $this->password);
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            echo "Error de conexión: " . $e->getMessage();
        }

        return $this->conn;
    }

}