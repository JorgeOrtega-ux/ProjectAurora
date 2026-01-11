<?php
// config/db.php

class Database {
    private $host = 'localhost';
    private $db_name = 'project_aurora';
    private $username = 'root'; // Cambia esto por tu usuario real
    private $password = '';     // Cambia esto por tu contraseña real
    public $conn;

    public function getConnection() {
        $this->conn = null;
        try {
            $this->conn = new PDO("mysql:host=" . $this->host . ";dbname=" . $this->db_name, $this->username, $this->password);
            $this->conn->exec("set names utf8");
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch(PDOException $exception) {
            echo "Error de conexión: " . $exception->getMessage();
        }
        return $this->conn;
    }
}
?>