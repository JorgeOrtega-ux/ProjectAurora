<?php
// api/services/StudioService.php

namespace Aurora\Services;

use Aurora\Libs\Utils;
use Aurora\Libs\Logger;
use Exception;
use PDO;

class StudioService {
    private $pdo;
    private $redis;
    private $i18n;
    private $userId;
    private $rawDir;
    private $tempDir;
    private $publicVideoDir;
    private $thumbnailDir;

    public function __construct($pdo, $redis, $i18n, $userId) {
        $this->pdo = $pdo;
        $this->redis = $redis;
        $this->i18n = $i18n;
        $this->userId = $userId;

        // Directorios Base
        $this->rawDir = __DIR__ . '/../../storage/uploads/raw';
        $this->tempDir = __DIR__ . '/../../storage/temp';
        $this->publicVideoDir = __DIR__ . '/../../public/storage/videos';
        $this->thumbnailDir = __DIR__ . '/../../public/storage/thumbnails';

        // Asegurar existencia
        if (!is_dir($this->rawDir)) mkdir($this->rawDir, 0755, true);
        if (!is_dir($this->tempDir)) mkdir($this->tempDir, 0755, true);
        if (!is_dir($this->publicVideoDir)) mkdir($this->publicVideoDir, 0755, true);
        if (!is_dir($this->thumbnailDir)) mkdir($this->thumbnailDir, 0755, true);
    }

    // 1. INICIAR SUBIDA (Registrar en BD)
    public function initUpload($batchId, $fileName) {
        if (empty($batchId)) return ['success' => false, 'message' => 'Falta Batch ID'];

        $uuid = Utils::generateUUID();
        $title = pathinfo($fileName, PATHINFO_FILENAME);

        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO videos (uuid, user_id, batch_id, title, status) 
                VALUES (?, ?, ?, ?, 'uploading_chunks')
            ");
            $stmt->execute([$uuid, $this->userId, $batchId, $title]);

            return ['success' => true, 'video_uuid' => $uuid];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $this->i18n->t('api.db_error')];
        }
    }

    // 2. PROCESAR CHUNK (Append)
    public function uploadChunk($uuid, $file, $index, $isLast) {
        // Verificar propiedad
        if (!$this->isOwner($uuid)) return ['success' => false, 'message' => 'Acceso denegado'];

        if (!isset($file['tmp_name']) || $file['error'] !== UPLOAD_ERR_OK) {
            return ['success' => false, 'message' => 'Error en chunk upload'];
        }

        $tempFilePath = $this->tempDir . '/' . $uuid . '.part';

        // Append del chunk al archivo temporal
        // Nota: Asumimos subida secuencial desde el cliente.
        $chunkData = file_get_contents($file['tmp_name']);
        file_put_contents($tempFilePath, $chunkData, FILE_APPEND);

        // Si es el último, finalizamos
        if ($isLast) {
            return $this->finalizeUpload($uuid, $tempFilePath);
        }

        return ['success' => true, 'status' => 'chunk_received'];
    }

    private function finalizeUpload($uuid, $tempPath) {
        $finalPath = $this->rawDir . '/' . $uuid . '.mp4';
        
        if (rename($tempPath, $finalPath)) {
            // Actualizar DB a 'queued'
            $stmt = $this->pdo->prepare("UPDATE videos SET status = 'queued', raw_file_path = ? WHERE uuid = ?");
            $stmt->execute(['storage/uploads/raw/' . $uuid . '.mp4', $uuid]);

            // Enviar tarea al Worker (Redis)
            if ($this->redis) {
                $task = [
                    'task' => 'process_video',
                    'payload' => [
                        'video_uuid' => $uuid,
                        'raw_path' => $finalPath
                    ]
                ];
                $this->redis->rpush('aurora_task_queue', json_encode($task));
            }

            return ['success' => true, 'status' => 'queued'];
        }

        return ['success' => false, 'message' => 'Error al ensamblar archivo final'];
    }

    // 3. SUBIR MINIATURA (MANUAL Y OBLIGATORIA)
    public function uploadThumbnail($uuid, $file) {
        if (!$this->isOwner($uuid)) return ['success' => false, 'message' => 'Acceso denegado'];

        // Usamos Utils para validar y procesar la imagen (seguridad centralizada)
        $uploadResult = Utils::processImageUpload($this->pdo, $file, $uuid . '_thumb', 'custom'); 
        
        if (!$uploadResult['success']) {
            return $uploadResult;
        }

        // Mover la imagen procesada a la carpeta pública de thumbnails
        // Nota: Utils guarda en 'public/storage/profilePicture/custom', nosotros queremos 'thumbnails'.
        // Para simplificar y reutilizar Utils, aceptamos esa ruta o movemos el archivo manualmente.
        // Dado que Utils devuelve base64, podemos guardar ese base64 decodificado o mover el archivo.
        // Lo ideal es moverlo:
        
        $sourcePath = __DIR__ . '/../../' . $uploadResult['db_path'];
        $destRelPath = 'public/storage/thumbnails/' . $uuid . '.png';
        $destAbsPath = __DIR__ . '/../../' . $destRelPath;

        if (rename($sourcePath, $destAbsPath)) {
            $stmt = $this->pdo->prepare("UPDATE videos SET thumbnail_path = ? WHERE uuid = ?");
            $stmt->execute([$destRelPath, $uuid]);
            
            return ['success' => true, 'new_src' => $uploadResult['base64']]; 
        }

        return ['success' => false, 'message' => 'Error moviendo miniatura'];
    }

    // 4. GUARDAR METADATOS Y PUBLICAR
    public function saveMetadata($uuid, $data) {
        if (!$this->isOwner($uuid)) return ['success' => false, 'message' => 'Acceso denegado'];

        $title = trim($data['title']);
        $desc = trim($data['description']);
        $publish = $data['publish'];

        if (empty($title)) return ['success' => false, 'message' => 'El título es obligatorio'];

        try {
            $stmt = $this->pdo->prepare("UPDATE videos SET title = ?, description = ? WHERE uuid = ?");
            $stmt->execute([$title, $desc, $uuid]);

            if ($publish) {
                // VALIDACIÓN ESTRICTA
                $check = $this->pdo->prepare("SELECT status, thumbnail_path FROM videos WHERE uuid = ?");
                $check->execute([$uuid]);
                $video = $check->fetch();

                // 1. Debe estar procesado (waiting_for_metadata)
                if ($video['status'] !== 'waiting_for_metadata') {
                    return ['success' => false, 'message' => 'El video aún se está procesando. Espera un momento.'];
                }

                // 2. Miniatura OBLIGATORIA
                if (empty($video['thumbnail_path'])) {
                    return ['success' => false, 'message' => 'Debes subir una miniatura antes de publicar.'];
                }

                $upd = $this->pdo->prepare("UPDATE videos SET status = 'published' WHERE uuid = ?");
                $upd->execute([$uuid]);
                
                // Opcional: Borrar raw file para ahorrar espacio
                // @unlink($this->rawDir . '/' . $uuid . '.mp4');

                return ['success' => true, 'published' => true, 'message' => '¡Video publicado exitosamente!'];
            }

            return ['success' => true, 'published' => false, 'message' => 'Cambios guardados'];

        } catch (Exception $e) {
            return ['success' => false, 'message' => $this->i18n->t('api.db_error')];
        }
    }

    // 5. RECUPERAR ESTADO AL RECARGAR
    public function getPendingVideos() {
        // Busca videos que no estén publicados ni fallidos, del usuario actual
        $sql = "SELECT uuid, title, description, status, thumbnail_path, processing_percentage 
                FROM videos 
                WHERE user_id = ? AND status NOT IN ('published', 'error')";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$this->userId]);
        $videos = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Formatear rutas para el frontend
        foreach ($videos as &$v) {
            if ($v['thumbnail_path']) {
                $path = __DIR__ . '/../../' . $v['thumbnail_path'];
                if (file_exists($path)) {
                    $data = file_get_contents($path);
                    $v['thumbnail_src'] = 'data:image/png;base64,' . base64_encode($data);
                }
            }
        }

        return ['success' => true, 'videos' => $videos];
    }

    // 6. CANCELAR LOTE (Atomic Failure)
    public function cancelBatch($batchId) {
        if (empty($batchId)) return;

        // Obtener videos del lote del usuario actual
        $stmt = $this->pdo->prepare("SELECT uuid, raw_file_path FROM videos WHERE batch_id = ? AND user_id = ?");
        $stmt->execute([$batchId, $this->userId]);
        $videos = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($videos as $v) {
            // Borrar archivos
            $tempPart = $this->tempDir . '/' . $v['uuid'] . '.part';
            $rawFile = __DIR__ . '/../../' . ($v['raw_file_path'] ?? '');
            
            if (file_exists($tempPart)) @unlink($tempPart);
            if (!empty($v['raw_file_path']) && file_exists($rawFile)) @unlink($rawFile);
        }

        // Borrar de BD
        $del = $this->pdo->prepare("DELETE FROM videos WHERE batch_id = ? AND user_id = ?");
        $del->execute([$batchId, $this->userId]);

        return ['success' => true];
    }

    private function isOwner($uuid) {
        $stmt = $this->pdo->prepare("SELECT id FROM videos WHERE uuid = ? AND user_id = ?");
        $stmt->execute([$uuid, $this->userId]);
        return (bool)$stmt->fetch();
    }
}
?>