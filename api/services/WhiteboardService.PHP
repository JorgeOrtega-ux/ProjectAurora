<?php
// api/services/WhiteboardService.php
require_once __DIR__ . '/../../includes/libs/Utils.php';

class WhiteboardService {
    private $pdo;
    private $storageDir;

    public function __construct($pdo) {
        $this->pdo = $pdo;
        // Ajusta esta ruta según tu estructura real
        $this->storageDir = __DIR__ . '/../../storage/whiteboards/';
        
        // Crear directorio si no existe
        if (!file_exists($this->storageDir)) {
            mkdir($this->storageDir, 0755, true);
        }
    }

    public function createWhiteboard($userId, $name = 'Nuevo Pizarrón') {
        $uuid = Utils::generateUUID();
        $fileName = $uuid . '.json';
        $filePath = 'storage/whiteboards/' . $fileName; // Ruta relativa para guardar en BD
        $fullPath = $this->storageDir . $fileName;

        // 1. Crear archivo JSON vacío
        $initialContent = json_encode(['version' => '1.0', 'objects' => []]);
        if (file_put_contents($fullPath, $initialContent) === false) {
            throw new Exception("Error al crear el archivo físico del pizarrón.");
        }

        // 2. Insertar en BD
        $stmt = $this->pdo->prepare("INSERT INTO whiteboards (uuid, user_id, name, file_path) VALUES (:uuid, :uid, :name, :path)");
        $result = $stmt->execute([
            ':uuid' => $uuid,
            ':uid'  => $userId,
            ':name' => $name,
            ':path' => $filePath
        ]);

        if (!$result) {
            // Rollback manual (borrar archivo)
            unlink($fullPath);
            throw new Exception("Error al registrar el pizarrón en la base de datos.");
        }

        return $uuid;
    }

    public function saveContent($uuid, $userId, $jsonContent) {
        // Verificar propiedad
        if (!$this->isOwner($uuid, $userId)) {
            throw new Exception("No tienes permiso para editar este pizarrón.");
        }

        $fileName = $uuid . '.json';
        $fullPath = $this->storageDir . $fileName;

        if (file_put_contents($fullPath, $jsonContent) === false) {
            throw new Exception("Error al guardar el archivo.");
        }
        
        // Actualizar timestamp en BD
        $stmt = $this->pdo->prepare("UPDATE whiteboards SET updated_at = NOW() WHERE uuid = :uuid");
        $stmt->execute([':uuid' => $uuid]);

        return true;
    }

    public function getContent($uuid, $userId) {
        // Verificar propiedad (o permisos de lectura compartidos en el futuro)
        if (!$this->isOwner($uuid, $userId)) {
            throw new Exception("No tienes permiso para ver este pizarrón.");
        }

        $fileName = $uuid . '.json';
        $fullPath = $this->storageDir . $fileName;

        if (!file_exists($fullPath)) {
            // Si el archivo no existe pero está en BD, devolvemos uno vacío
            return json_encode(['version' => '1.0', 'objects' => []]);
        }

        return file_get_contents($fullPath);
    }

    private function isOwner($uuid, $userId) {
        $stmt = $this->pdo->prepare("SELECT id FROM whiteboards WHERE uuid = :uuid AND user_id = :uid");
        $stmt->execute([':uuid' => $uuid, ':uid' => $userId]);
        return $stmt->fetchColumn() !== false;
    }
}
?>