<?php
// config/database.php
namespace App\Config;

use PDO;
use PDOException;
use App\Core\Logger;

class Database {
    private $host;
    private $db_name;
    private $username;
    private $password;
    public $conn;

    public function __construct() {
        // Usamos $_ENV que es mucho más seguro en peticiones concurrentes que getenv()
        $this->host = $_ENV['DB_HOST'] ?? getenv('DB_HOST');
        $this->db_name = $_ENV['DB_NAME'] ?? getenv('DB_NAME');
        $this->username = $_ENV['DB_USER'] ?? getenv('DB_USER');
        $this->password = $_ENV['DB_PASS'] ?? getenv('DB_PASS');
    }

    public function getConnection() {
        $this->conn = null;
        try {
            $this->conn = new PDO("mysql:host=" . $this->host . ";dbname=" . $this->db_name, $this->username, $this->password);
            $this->conn->exec("set names utf8");
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            Logger::database("Fallo al conectar a la base de datos.", Logger::LEVEL_CRITICAL, $e);
            throw $e; // Relanzamos para que Bootstrap despliegue el error 500 visualmente
        }
        
        return $this->conn;
    }
}
?>