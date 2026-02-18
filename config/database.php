<?php
// includes/config/database.php
class Database {
    private $host = "localhost";
    private $db_name = "project_aurora_db"; // CAMBIA ESTO POR TU BD
    private $username = "root";             // CAMBIA ESTO
    private $password = "";                 // CAMBIA ESTO
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