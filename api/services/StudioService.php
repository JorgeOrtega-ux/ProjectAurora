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

        $this->rawDir = __DIR__ . '/../../storage/uploads/raw';
        $this->tempDir = __DIR__ . '/../../storage/temp';
        $this->publicVideoDir = __DIR__ . '/../../public/storage/videos';
        $this->thumbnailDir = __DIR__ . '/../../public/storage/thumbnails';

        if (!is_dir($this->rawDir)) mkdir($this->rawDir, 0755, true);
        if (!is_dir($this->tempDir)) mkdir($this->tempDir, 0755, true);
        if (!is_dir($this->publicVideoDir)) mkdir($this->publicVideoDir, 0755, true);
        if (!is_dir($this->thumbnailDir)) mkdir($this->thumbnailDir, 0755, true);
    }

    public function initUpload($batchId, $fileName) {
        if (empty($batchId)) return ['success' => false, 'message' => 'Falta Batch ID'];

        $uuid = Utils::generateUUID();
        // Usamos pathinfo para obtener solo el nombre sin extensión por defecto
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

    public function uploadChunk($uuid, $file, $index, $isLast) {
        if (!$this->isOwner($uuid)) return ['success' => false, 'message' => 'Acceso denegado'];

        if (!isset($file['tmp_name']) || $file['error'] !== UPLOAD_ERR_OK) {
            return ['success' => false, 'message' => 'Error en chunk upload'];
        }

        $tempFilePath = $this->tempDir . '/' . $uuid . '.part';
        $chunkData = file_get_contents($file['tmp_name']);
        file_put_contents($tempFilePath, $chunkData, FILE_APPEND);

        if ($isLast) {
            return $this->finalizeUpload($uuid, $tempFilePath);
        }

        return ['success' => true, 'status' => 'chunk_received'];
    }

    private function finalizeUpload($uuid, $tempPath) {
        $finalPath = $this->rawDir . '/' . $uuid . '.mp4';
        
        if (rename($tempPath, $finalPath)) {
            $stmt = $this->pdo->prepare("UPDATE videos SET status = 'queued', raw_file_path = ? WHERE uuid = ?");
            $stmt->execute(['storage/uploads/raw/' . $uuid . '.mp4', $uuid]);

            // Obtener el título original para devolverlo al frontend
            $stmtTitle = $this->pdo->prepare("SELECT title FROM videos WHERE uuid = ?");
            $stmtTitle->execute([$uuid]);
            $title = $stmtTitle->fetchColumn();

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

            // [CAMBIO] Devolvemos UUID y Título para que el frontend abra el editor
            return [
                'success' => true, 
                'status' => 'queued',
                'video_uuid' => $uuid,
                'title' => $title
            ];
        }

        return ['success' => false, 'message' => 'Error al ensamblar archivo final'];
    }

    public function uploadThumbnail($uuid, $file) {
        if (!$this->isOwner($uuid)) return ['success' => false, 'message' => 'Acceso denegado'];

        $uploadResult = Utils::processImageUpload($this->pdo, $file, $uuid . '_thumb', 'custom'); 
        
        if (!$uploadResult['success']) {
            return $uploadResult;
        }

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

    public function saveMetadata($uuid, $data) {
        if (!$this->isOwner($uuid)) return ['success' => false, 'message' => 'Acceso denegado'];

        $title = trim($data['title']);
        $desc = trim($data['description']);
        $publish = $data['publish']; // boolean string 'true'/'false'

        if (empty($title)) return ['success' => false, 'message' => 'El título es obligatorio'];

        try {
            $stmt = $this->pdo->prepare("UPDATE videos SET title = ?, description = ? WHERE uuid = ?");
            $stmt->execute([$title, $desc, $uuid]);

            if ($publish === true || $publish === 'true') {
                $check = $this->pdo->prepare("SELECT status, thumbnail_path FROM videos WHERE uuid = ?");
                $check->execute([$uuid]);
                $video = $check->fetch();

                // Regla 1: Debe estar en 'waiting_for_metadata' (significa procesado OK)
                if ($video['status'] !== 'waiting_for_metadata') {
                    return [
                        'success' => false, 
                        'message' => 'El video aún se está procesando o no está listo.'
                    ];
                }

                // Regla 2: Miniatura obligatoria
                if (empty($video['thumbnail_path'])) {
                    return ['success' => false, 'message' => 'Debes subir una miniatura antes de publicar.'];
                }

                $upd = $this->pdo->prepare("UPDATE videos SET status = 'published' WHERE uuid = ?");
                $upd->execute([$uuid]);

                return ['success' => true, 'published' => true, 'message' => '¡Video publicado exitosamente!'];
            }

            // Si solo es borrador (publish = false), guardamos sin validar estado
            return ['success' => true, 'published' => false, 'message' => 'Borrador guardado'];

        } catch (Exception $e) {
            return ['success' => false, 'message' => $this->i18n->t('api.db_error')];
        }
    }

    public function getPendingVideos() {
        $sql = "SELECT uuid, title, description, status, thumbnail_path, processing_percentage 
                FROM videos 
                WHERE user_id = ? AND status NOT IN ('published', 'error')";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$this->userId]);
        $videos = $stmt->fetchAll(PDO::FETCH_ASSOC);

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

    public function cancelBatch($batchId) {
        if (empty($batchId)) return;
        $stmt = $this->pdo->prepare("SELECT uuid, raw_file_path FROM videos WHERE batch_id = ? AND user_id = ?");
        $stmt->execute([$batchId, $this->userId]);
        $videos = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($videos as $v) {
            $tempPart = $this->tempDir . '/' . $v['uuid'] . '.part';
            $rawFile = __DIR__ . '/../../' . ($v['raw_file_path'] ?? '');
            if (file_exists($tempPart)) @unlink($tempPart);
            if (!empty($v['raw_file_path']) && file_exists($rawFile)) @unlink($rawFile);
        }

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