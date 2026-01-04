<?php
// api/services/WhiteboardService.php

require_once __DIR__ . '/../../config/database/redis.php';

class WhiteboardService {
    private $pdo;
    private $redis;
    private $storageDir;

    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->redis = RedisClient::getInstance();
        $this->storageDir = __DIR__ . '/../../storage/whiteboards/';
        
        if (!is_dir($this->storageDir)) {
            mkdir($this->storageDir, 0777, true);
        }
    }

    public function create($userId, $name) {
        $uuid = Utils::generateUuid(); // Asumiendo que mueves generateUuid a Utils o lo copias aquí
        
        try {
            // 1. Crear Metadatos en MySQL
            $stmt = $this->pdo->prepare("INSERT INTO whiteboards (uuid, user_id, name) VALUES (?, ?, ?)");
            $stmt->execute([$uuid, $userId, $name]);

            // 2. Crear archivo físico inicial (vacío)
            file_put_contents($this->storageDir . $uuid . '.json', '[]');

            return ['success' => true, 'uuid' => $uuid, 'redirect' => '/ProjectAurora/whiteboard/' . $uuid];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Error al crear pizarrón: ' . $e->getMessage()];
        }
    }

    public function listByUser($userId) {
        $stmt = $this->pdo->prepare("SELECT uuid, name, updated_at FROM whiteboards WHERE user_id = ? ORDER BY updated_at DESC");
        $stmt->execute([$userId]);
        return ['success' => true, 'data' => $stmt->fetchAll()];
    }

    public function load($uuid, $userId) {
        // Validación de propiedad (Opcional: permitir si es colaborador)
        $stmt = $this->pdo->prepare("SELECT id, name FROM whiteboards WHERE uuid = ? AND user_id = ?");
        $stmt->execute([$uuid, $userId]);
        $meta = $stmt->fetch();

        if (!$meta) {
            return ['success' => false, 'message' => 'Pizarrón no encontrado o acceso denegado.'];
        }

        $content = '[]';
        $source = 'disk';

        // 1. Intentar leer de Redis
        if ($this->redis) {
            $cached = $this->redis->get("wb:{$uuid}:data");
            if ($cached) {
                $content = $cached;
                $source = 'redis';
            }
        }

        // 2. Si no está en Redis, leer de disco
        if ($source === 'disk') {
            $filePath = $this->storageDir . $uuid . '.json';
            if (file_exists($filePath)) {
                $content = file_get_contents($filePath);
                // Hydrate Redis para la próxima
                if ($this->redis) {
                    $this->redis->set("wb:{$uuid}:data", $content);
                    $this->redis->expire("wb:{$uuid}:data", 3600 * 24); // TTL 24h
                }
            }
        }

        return ['success' => true, 'data' => json_decode($content), 'meta' => $meta];
    }

    public function save($uuid, $dataRaw) {
        // NOTA: $dataRaw debe ser el string JSON, no el array decodificado
        if (!$this->redis) {
            // Fallback sin Redis: Escritura directa (lento)
            file_put_contents($this->storageDir . $uuid . '.json', $dataRaw);
            return ['success' => true, 'source' => 'disk_direct'];
        }

        // Estrategia Write-Through Buffer
        $keyData = "wb:{$uuid}:data";
        $keyChanges = "wb:{$uuid}:changes";

        // 1. Guardar siempre en Redis
        $this->redis->set($keyData, $dataRaw);
        $this->redis->expire($keyData, 3600 * 24); // Refrescar TTL

        // 2. Incrementar contador de cambios
        $changes = $this->redis->incr($keyChanges);

        // 3. Evaluar volcado a disco
        if ($changes >= 20) {
            file_put_contents($this->storageDir . $uuid . '.json', $dataRaw);
            $this->redis->set($keyChanges, 0); // Reset contador
            return ['success' => true, 'saved_to_disk' => true];
        }

        return ['success' => true, 'saved_to_disk' => false, 'changes_buffer' => $changes];
    }
    
    // Método especial para el sendBeacon (Force Save)
    public function forceSaveToDisk($uuid, $dataRaw) {
        file_put_contents($this->storageDir . $uuid . '.json', $dataRaw);
        if ($this->redis) {
            $this->redis->set("wb:{$uuid}:data", $dataRaw);
            $this->redis->set("wb:{$uuid}:changes", 0);
        }
        return ['success' => true];
    }
}
?>