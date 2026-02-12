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

    // [NUEVO] Método para obtener detalles de un video específico (Edición)
    public function getVideoDetails($uuid) {
        if (!$this->isOwner($uuid)) {
            return ['success' => false, 'message' => 'Video no encontrado o acceso denegado.'];
        }

        try {
            $stmt = $this->pdo->prepare("
                SELECT uuid, title, description, status, thumbnail_path, dominant_color 
                FROM videos 
                WHERE uuid = ?
            ");
            $stmt->execute([$uuid]);
            $video = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$video) {
                return ['success' => false, 'message' => 'Video no encontrado.'];
            }

            // Preparar miniatura
            $video['thumbnail_src'] = null;
            if (!empty($video['thumbnail_path'])) {
                $path = __DIR__ . '/../../' . $video['thumbnail_path'];
                // Si es pública, usar URL relativa; si es privada/subida, convertir a base64 o ruta pública
                if (file_exists($path)) {
                    // Opción: Devolver URL pública directa si está en public/storage
                    $video['thumbnail_src'] = 'public/storage/thumbnails/' . basename($video['thumbnail_path']);
                }
            }

            return ['success' => true, 'video' => $video];

        } catch (Exception $e) {
            return ['success' => false, 'message' => $this->i18n->t('api.db_error')];
        }
    }

    public function getPublicFeed($page = 1, $limit = 20) {
        $offset = ($page - 1) * $limit;
        
        try {
            $countStmt = $this->pdo->query("SELECT COUNT(*) FROM videos WHERE status = 'published'");
            $totalItems = $countStmt->fetchColumn();

            $sql = "SELECT v.uuid, v.title, v.description, v.thumbnail_path, v.created_at, v.duration, v.dominant_color, v.hls_path,
                           u.username, u.avatar_path, u.uuid as user_uuid
                    FROM videos v
                    JOIN users u ON v.user_id = u.id
                    WHERE v.status = 'published'
                    ORDER BY v.created_at DESC 
                    LIMIT :limit OFFSET :offset";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            $videos = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($videos as &$v) {
                if ($v['thumbnail_path']) {
                    $v['thumbnail_url'] = 'public/storage/thumbnails/' . basename($v['thumbnail_path']);
                } else {
                    $v['thumbnail_url'] = null;
                }
                
                $avatarPath = $v['avatar_path'];
                if (!empty($avatarPath) && file_exists(__DIR__ . '/../../' . $avatarPath)) {
                    $v['author_avatar_url'] = $avatarPath; 
                } else {
                    $name = urlencode($v['username']);
                    $v['author_avatar_url'] = "https://ui-avatars.com/api/?name={$name}&background=random&color=fff&size=128&length=1";
                }

                if ($v['duration']) {
                    $minutes = floor($v['duration'] / 60);
                    $seconds = $v['duration'] % 60;
                    $v['duration_formatted'] = sprintf("%02d:%02d", $minutes, $seconds);
                } else {
                    $v['duration_formatted'] = '--:--';
                }
                
                $v['time_ago'] = Utils::timeElapsedString($v['created_at']);
                $v['views'] = 0; 
                $v['views_formatted'] = '0 visualizaciones'; 
            }

            return [
                'success' => true,
                'videos' => $videos,
                'pagination' => [
                    'current' => (int)$page,
                    'total_pages' => ceil($totalItems / $limit),
                    'total_items' => (int)$totalItems
                ]
            ];

        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Error al cargar el feed.'];
        }
    }

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
            $color = $uploadResult['dominant_color'] ?? '#000000';
            
            $stmt = $this->pdo->prepare("UPDATE videos SET thumbnail_path = ?, dominant_color = ? WHERE uuid = ?");
            $stmt->execute([$destRelPath, $color, $uuid]);
            
            return [
                'success' => true, 
                'new_src' => $uploadResult['base64'],
                'dominant_color' => $color
            ]; 
        }

        return ['success' => false, 'message' => 'Error moviendo miniatura'];
    }

    public function requestAutoThumbnails($uuid) {
        if (!$this->isOwner($uuid)) return ['success' => false, 'message' => 'Acceso denegado'];

        $stmt = $this->pdo->prepare("SELECT raw_file_path, duration FROM videos WHERE uuid = ?");
        $stmt->execute([$uuid]);
        $video = $stmt->fetch();

        if (!$video || empty($video['raw_file_path'])) {
            return ['success' => false, 'message' => 'El video aún no está procesado o no existe el archivo fuente.'];
        }

        if (!$this->redis) {
            return ['success' => false, 'message' => 'Redis no disponible para procesar tarea.'];
        }

        $task = [
            'task' => 'generate_thumbnails',
            'payload' => [
                'video_uuid' => $uuid,
                'raw_path' => __DIR__ . '/../../' . $video['raw_file_path'],
                'duration' => $video['duration']
            ]
        ];
        
        try {
            $this->redis->rpush('aurora_task_queue', json_encode($task));
            return ['success' => true, 'message' => 'Generación de miniaturas iniciada...'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Error de conexión con cola de tareas.'];
        }
    }

    public function saveMetadata($uuid, $data) {
        if (!$this->isOwner($uuid)) return ['success' => false, 'message' => 'Acceso denegado'];

        $title = trim($data['title']);
        $desc = trim($data['description']);
        $publish = $data['publish'];

        if (empty($title)) return ['success' => false, 'message' => 'El título es obligatorio'];

        try {
            $stmt = $this->pdo->prepare("UPDATE videos SET title = ?, description = ? WHERE uuid = ?");
            $stmt->execute([$title, $desc, $uuid]);

            if ($publish === true || $publish === 'true') {
                $check = $this->pdo->prepare("SELECT status, thumbnail_path FROM videos WHERE uuid = ?");
                $check->execute([$uuid]);
                $video = $check->fetch();

                if ($video['status'] !== 'waiting_for_metadata') {
                    // Permitir también 'ready' o 'processed' si los usas
                    return [
                        'success' => false, 
                        'message' => 'El video aún se está procesando o no está listo.'
                    ];
                }

                if (empty($video['thumbnail_path'])) {
                    return ['success' => false, 'message' => 'Debes subir una miniatura antes de publicar.'];
                }

                $upd = $this->pdo->prepare("UPDATE videos SET status = 'published' WHERE uuid = ?");
                $upd->execute([$uuid]);

                return ['success' => true, 'published' => true, 'message' => '¡Video publicado exitosamente!'];
            }

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

    public function getUserContent($search = '', $status = 'all', $page = 1, $limit = 20) {
        $offset = ($page - 1) * $limit;
        $params = [$this->userId];
        $whereClause = "WHERE user_id = ?";

        if (!empty($search)) {
            $whereClause .= " AND title LIKE ?";
            $params[] = "%$search%";
        }

        if ($status !== 'all' && !empty($status)) {
            $whereClause .= " AND status = ?";
            $params[] = $status;
        }

        try {
            $countStmt = $this->pdo->prepare("SELECT COUNT(*) FROM videos $whereClause");
            $countStmt->execute($params);
            $totalItems = $countStmt->fetchColumn();

            $sql = "SELECT uuid, title, description, status, thumbnail_path, created_at, 
                           duration, processing_percentage, error_message, dominant_color
                    FROM videos 
                    $whereClause 
                    ORDER BY created_at DESC 
                    LIMIT $limit OFFSET $offset";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $videos = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($videos as &$v) {
                if ($v['thumbnail_path']) {
                    $path = __DIR__ . '/../../' . $v['thumbnail_path'];
                    if (file_exists($path)) {
                        $v['thumbnail_url'] = 'public/storage/thumbnails/' . basename($v['thumbnail_path']);
                    } else {
                        $v['thumbnail_url'] = null;
                    }
                }
                
                if ($v['duration']) {
                    $minutes = floor($v['duration'] / 60);
                    $seconds = $v['duration'] % 60;
                    $v['duration_formatted'] = sprintf("%02d:%02d", $minutes, $seconds);
                } else {
                    $v['duration_formatted'] = '--:--';
                }
                
                $v['time_ago'] = Utils::timeElapsedString($v['created_at']);
            }

            return [
                'success' => true,
                'videos' => $videos,
                'pagination' => [
                    'current' => (int)$page,
                    'total_pages' => ceil($totalItems / $limit),
                    'total_items' => (int)$totalItems,
                    'limit' => (int)$limit
                ]
            ];

        } catch (Exception $e) {
            return ['success' => false, 'message' => $this->i18n->t('api.db_error')];
        }
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

    public function deleteVideo($uuid) {
        if (!$this->isOwner($uuid)) {
            return ['success' => false, 'message' => 'Acceso denegado o video no encontrado.'];
        }

        $stmt = $this->pdo->prepare("SELECT raw_file_path, thumbnail_path, hls_path, generated_thumbnails FROM videos WHERE uuid = ?");
        $stmt->execute([$uuid]);
        $video = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!empty($video['raw_file_path'])) {
            $rawPath = __DIR__ . '/../../' . $video['raw_file_path'];
            if (file_exists($rawPath)) @unlink($rawPath);
        }

        $tempPath = $this->tempDir . '/' . $uuid . '.part';
        if (file_exists($tempPath)) @unlink($tempPath);

        if (!empty($video['thumbnail_path'])) {
            $thumbPath = __DIR__ . '/../../' . $video['thumbnail_path'];
            if (file_exists($thumbPath)) @unlink($thumbPath);
        }

        $videoFolder = $this->publicVideoDir . '/' . $uuid;
        if (is_dir($videoFolder)) {
            $this->deleteDirectory($videoFolder);
        }

        $generatedThumbsDir = $this->thumbnailDir . '/generated/' . $uuid;
        if (is_dir($generatedThumbsDir)) {
            $this->deleteDirectory($generatedThumbsDir);
        }

        $del = $this->pdo->prepare("DELETE FROM videos WHERE uuid = ?");
        if ($del->execute([$uuid])) {
            return ['success' => true, 'message' => 'Video eliminado correctamente.'];
        }

        return ['success' => false, 'message' => 'Error al eliminar el registro.'];
    }

    private function deleteDirectory($dir) {
        if (!file_exists($dir)) return;
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            (is_dir("$dir/$file")) ? $this->deleteDirectory("$dir/$file") : unlink("$dir/$file");
        }
        return rmdir($dir);
    }

    private function isOwner($uuid) {
        $stmt = $this->pdo->prepare("SELECT id FROM videos WHERE uuid = ? AND user_id = ?");
        $stmt->execute([$uuid, $this->userId]);
        return (bool)$stmt->fetch();
    }
}
?>