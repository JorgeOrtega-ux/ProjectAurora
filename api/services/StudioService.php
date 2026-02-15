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

    // --- HELPER PRIVADO PARA LECTURA HÍBRIDA (Real-Time Batching) ---
    private function getRealViewCount($uuid, $mysqlCount) {
        if (!$this->redis) return (int)$mysqlCount;
        
        try {
            // Leemos el buffer temporal de Redis
            $bufferKey = "video:buffer:views:{$uuid}";
            $bufferCount = (int)$this->redis->get($bufferKey);
            
            // Sumamos: Lo consolidado en BD + Lo pendiente en RAM
            return (int)$mysqlCount + $bufferCount;
        } catch (Exception $e) {
            return (int)$mysqlCount;
        }
    }

    // Método para obtener detalles de un video específico (Edición/Visualización)
    public function getVideoDetails($uuid) {
        if (!$this->isOwner($uuid)) {
            return ['success' => false, 'message' => 'Video no encontrado o acceso denegado.'];
        }

        try {
            // 1. Datos principales
            $stmt = $this->pdo->prepare("
                SELECT id, uuid, title, description, status, thumbnail_path, dominant_color, 
                       generated_thumbnails, orientation, sprite_path, vtt_path, views_count
                FROM videos 
                WHERE uuid = ?
            ");
            $stmt->execute([$uuid]);
            $video = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$video) {
                return ['success' => false, 'message' => 'Video no encontrado.'];
            }

            // Procesar rutas y JSON
            $video['thumbnail_src'] = null;
            if (!empty($video['thumbnail_path'])) {
                $path = __DIR__ . '/../../' . $video['thumbnail_path'];
                if (file_exists($path)) {
                    $video['thumbnail_src'] = $video['thumbnail_path'];
                }
            }
            
            if (!empty($video['generated_thumbnails'])) {
                $video['generated_thumbnails'] = json_decode($video['generated_thumbnails'], true);
            } else {
                $video['generated_thumbnails'] = [];
            }

            // [LÓGICA HÍBRIDA] Calcular visitas reales
            $video['views'] = $this->getRealViewCount($uuid, $video['views_count']);

            // [MODIFICACIÓN 1] Cargar Categorías y Actores asociados
            // A. Categorías
            $stmtCat = $this->pdo->prepare("
                SELECT c.id, c.name, c.slug 
                FROM video_categories c
                JOIN video_categories_map map ON c.id = map.category_id
                WHERE map.video_id = ?
            ");
            $stmtCat->execute([$video['id']]);
            $video['categories'] = $stmtCat->fetchAll(PDO::FETCH_ASSOC);

            // B. Actores (Cast)
            $stmtCast = $this->pdo->prepare("
                SELECT a.id, a.name, a.slug, a.avatar_path 
                FROM video_actors a
                JOIN video_actors_map map ON a.id = map.actor_id
                WHERE map.video_id = ?
            ");
            $stmtCast->execute([$video['id']]);
            $video['cast'] = $stmtCast->fetchAll(PDO::FETCH_ASSOC);

            // Limpiamos el ID interno antes de enviar al front por seguridad/limpieza
            unset($video['id']);

            return ['success' => true, 'video' => $video];

        } catch (Exception $e) {
            return ['success' => false, 'message' => $this->i18n->t('api.db_error')];
        }
    }

    // [NUEVO] Método para búsqueda de tags (Autocompletado)
    public function searchTags($type, $query) {
        $query = trim($query);
        if (strlen($query) < 2) return ['success' => true, 'results' => []];

        try {
            if ($type === 'actor') {
                $stmt = $this->pdo->prepare("SELECT id, name, avatar_path, slug FROM video_actors WHERE name LIKE ? LIMIT 5");
                $stmt->execute(["%$query%"]);
                return ['success' => true, 'results' => $stmt->fetchAll(PDO::FETCH_ASSOC)];
            } else {
                $stmt = $this->pdo->prepare("SELECT id, name, slug, usage_count as count FROM video_categories WHERE name LIKE ? LIMIT 5");
                $stmt->execute(["%$query%"]);
                return ['success' => true, 'results' => $stmt->fetchAll(PDO::FETCH_ASSOC)];
            }
        } catch (Exception $e) {
            return ['success' => false, 'results' => []];
        }
    }

    public function getPublicFeed($page = 1, $limit = 20) {
        $offset = ($page - 1) * $limit;
        
        try {
            $countStmt = $this->pdo->query("SELECT COUNT(*) FROM videos WHERE status = 'published'");
            $totalItems = $countStmt->fetchColumn();

            $sql = "SELECT v.uuid, v.title, v.description, v.thumbnail_path, v.created_at, v.duration, 
                           v.dominant_color, v.hls_path, v.orientation, v.views_count,
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
                    $v['thumbnail_url'] = $v['thumbnail_path'];
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
                
                // [LÓGICA HÍBRIDA] Calcular visitas reales para cada video del feed
                $v['views'] = $this->getRealViewCount($v['uuid'], $v['views_count']);
                $v['views_formatted'] = number_format($v['views']) . ' visualizaciones'; 
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

    public function initUpload($batchId, $fileName, $fileSize = 0) {
        if (empty($batchId)) return ['success' => false, 'message' => 'Falta Batch ID'];

        try {
            $attemptsLimit = (int)Utils::getServerConfig($this->pdo, 'upload_daily_limit', '10');

            if (Utils::checkSecurityLimit($this->pdo, 'video_upload_init', $attemptsLimit, 1440, $this->userId)) {
                return [
                    'success' => false, 
                    'message' => $this->i18n->t('api.upload_security_limit_reached') ?? 'Límite de seguridad de subidas excedido.'
                ];
            }
            Utils::logSecurityAction($this->pdo, 'video_upload_init', 1440, $this->userId);

            $stmtRole = $this->pdo->prepare("SELECT role FROM users WHERE id = ?");
            $stmtRole->execute([$this->userId]);
            $role = $stmtRole->fetchColumn();

            $isFounder = ($role === 'founder');
            $dailyLimit = $isFounder ? 999999 : 3; 
            $maxSizeBytes = $isFounder ? (100 * 1024 * 1024 * 1024) : (25 * 1024 * 1024 * 1024);

            if ($fileSize > $maxSizeBytes) {
                $readableMax = Utils::formatSize($maxSizeBytes);
                return ['success' => false, 'message' => "El archivo excede el límite de tamaño permitido ($readableMax)."];
            }

            if (!$isFounder) {
                $stmtCount = $this->pdo->prepare("SELECT COUNT(*) FROM videos WHERE user_id = ? AND DATE(created_at) = CURDATE()");
                $stmtCount->execute([$this->userId]);
                $countToday = $stmtCount->fetchColumn();

                if ($countToday >= $dailyLimit) {
                    return ['success' => false, 'message' => "Has alcanzado tu límite diario de subidas ($dailyLimit videos)."];
                }
            }

            $uuid = Utils::generateUUID();
            $title = pathinfo($fileName, PATHINFO_FILENAME);
            $uploadToken = bin2hex(random_bytes(32));

            if ($this->redis) {
                $tokenData = json_encode(['user_id' => $this->userId, 'token' => $uploadToken]);
                $this->redis->setex("upload_token:$uuid", 7200, $tokenData);
            }

            $stmt = $this->pdo->prepare("
                INSERT INTO videos (uuid, user_id, batch_id, title, status) 
                VALUES (?, ?, ?, ?, 'uploading_chunks')
            ");
            $stmt->execute([$uuid, $this->userId, $batchId, $title]);

            return [
                'success' => true, 
                'video_uuid' => $uuid,
                'upload_token' => $uploadToken
            ];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $this->i18n->t('api.db_error')];
        }
    }

    public function uploadChunk($uuid, $file, $index, $isLast, $token) {
        if ($this->redis) {
            $redisKey = "upload_token:$uuid";
            $storedDataJson = $this->redis->get($redisKey);

            if (!$storedDataJson) {
                return ['success' => false, 'message' => 'Sesión de subida expirada o inválida.'];
            }

            $storedData = json_decode($storedDataJson, true);
            if ($storedData['token'] !== $token || $storedData['user_id'] != $this->userId) {
                return ['success' => false, 'message' => 'Token de seguridad inválido. Acceso denegado.'];
            }
        } else {
            if (!$this->isOwner($uuid)) return ['success' => false, 'message' => 'Acceso denegado'];
        }

        if (!isset($file['tmp_name']) || $file['error'] !== UPLOAD_ERR_OK) {
            return ['success' => false, 'message' => 'Error en chunk upload'];
        }

        $tempFilePath = $this->tempDir . '/' . $uuid . '.part';
        $chunkData = file_get_contents($file['tmp_name']);

        $lockKey = "lock:upload:$uuid";
        $lockAcquired = false;

        if ($this->redis) {
            for ($i = 0; $i < 50; $i++) {
                $lockAcquired = $this->redis->setnx($lockKey, time());
                if ($lockAcquired) {
                    $this->redis->expire($lockKey, 10);
                    break;
                }
                usleep(100000);
            }

            if (!$lockAcquired) {
                return ['success' => false, 'message' => 'Servidor ocupado (Lock timeout). Reintenta el chunk.'];
            }
        }

        try {
            file_put_contents($tempFilePath, $chunkData, FILE_APPEND);
        } finally {
            if ($this->redis && $lockAcquired) {
                $this->redis->del($lockKey);
                $this->redis->expire("upload_token:$uuid", 7200);
            }
        }

        if ($isLast) {
            return $this->finalizeUpload($uuid, $tempFilePath);
        }

        return ['success' => true, 'status' => 'chunk_received'];
    }

    private function finalizeUpload($uuid, $tempPath) {
        $finalPath = $this->rawDir . '/' . $uuid . '.mp4';
        
        if (rename($tempPath, $finalPath)) {
            if ($this->redis) {
                $this->redis->del("upload_token:$uuid");
                $this->redis->del("lock:upload:$uuid");
            }

            $stmt = $this->pdo->prepare("UPDATE videos SET status = 'queued', raw_file_path = ? WHERE uuid = ?");
            $stmt->execute(['storage/uploads/raw/' . $uuid . '.mp4', $uuid]);

            $stmtTitle = $this->pdo->prepare("SELECT title FROM videos WHERE uuid = ?");
            $stmtTitle->execute([$uuid]);
            $title = $stmtTitle->fetchColumn();

            $stmtRole = $this->pdo->prepare("SELECT role FROM users WHERE id = ?");
            $stmtRole->execute([$this->userId]);
            $role = $stmtRole->fetchColumn();
            $maxDuration = ($role === 'founder') ? 43200 : 7200;

            if ($this->redis) {
                $task = [
                    'task' => 'process_video',
                    'payload' => [
                        'video_uuid' => $uuid,
                        'raw_path' => $finalPath,
                        'max_duration' => $maxDuration
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

        if (Utils::checkSecurityLimit($this->pdo, 'generate_thumbs', 3, 10, $this->userId)) {
            return ['success' => false, 'message' => 'Has excedido el límite de generación. Espera unos minutos.'];
        }
        Utils::logSecurityAction($this->pdo, 'generate_thumbs', 10, $this->userId);

        $stmt = $this->pdo->prepare("SELECT raw_file_path, duration FROM videos WHERE uuid = ?");
        $stmt->execute([$uuid]);
        $video = $stmt->fetch();

        if (!$video || empty($video['raw_file_path'])) {
            return ['success' => false, 'message' => 'El video aún no está procesado.'];
        }

        $rawPath = __DIR__ . '/../../' . $video['raw_file_path']; 
        if (!file_exists($rawPath)) {
            return ['success' => false, 'message' => 'Archivo de video no encontrado en el servidor.'];
        }

        $thumbsDirRel = "public/storage/thumbnails/generated/$uuid";
        $thumbsDirAbs = __DIR__ . "/../../$thumbsDirRel";

        if (!is_dir($thumbsDirAbs)) {
            if (!mkdir($thumbsDirAbs, 0755, true)) {
                return ['success' => false, 'message' => 'No se pudo crear el directorio de miniaturas.'];
            }
        }

        set_time_limit(120); 

        $duration = (float)$video['duration'];
        $numThumbs = 1;
        if ($duration > 60) $numThumbs = (int)($duration / 60); 
        if ($numThumbs > 12) $numThumbs = 12; 
        if ($numThumbs < 3) $numThumbs = 3;   

        $interval = $duration / ($numThumbs + 1);
        $generatedFiles = [];

        for ($i = 1; $i <= $numThumbs; $i++) {
            $timestamp = $i * $interval;
            $fileName = "thumb_{$i}.jpg";
            $outputAbs = "$thumbsDirAbs/$fileName";
            $outputRel = "$thumbsDirRel/$fileName";

            $vfFilter = "scale=1920:1080:force_original_aspect_ratio=decrease,pad=1920:1080:(ow-iw)/2:(oh-ih)/2:black";
            
            $cmd = "ffmpeg -y -ss $timestamp -i " . escapeshellarg($rawPath) . " -vframes 1 -q:v 2 -vf \"$vfFilter\" " . escapeshellarg($outputAbs) . " 2>&1";
            
            exec($cmd, $output, $returnVar);

            if ($returnVar === 0 && file_exists($outputAbs)) {
                $color = '#000000';
                try {
                    $im = @imagecreatefromjpeg($outputAbs);
                    if ($im) {
                        $color = Utils::getDominantColor($im);
                        imagedestroy($im);
                    }
                } catch (Exception $e) {}

                $generatedFiles[] = [
                    'path' => $outputRel,
                    'color' => $color
                ];
            }
        }

        if (!empty($generatedFiles)) {
            $json = json_encode($generatedFiles);
            $upd = $this->pdo->prepare("UPDATE videos SET generated_thumbnails = ? WHERE uuid = ?");
            $upd->execute([$json, $uuid]);

            return [
                'success' => true, 
                'message' => 'Miniaturas generadas correctamente.',
                'thumbnails' => $generatedFiles 
            ];
        }

        return ['success' => false, 'message' => 'No se pudieron generar miniaturas (Fallo FFmpeg).'];
    }

    public function setGeneratedThumbnail($uuid, $path, $color) {
        if (!$this->isOwner($uuid)) return ['success' => false, 'message' => 'Acceso denegado'];

        if (Utils::checkSecurityLimit($this->pdo, 'set_thumb_click', 20, 1, $this->userId)) {
            return ['success' => false, 'message' => 'Demasiados intentos. Calma.'];
        }
        Utils::logSecurityAction($this->pdo, 'set_thumb_click', 1, $this->userId);

        $expectedPath = "public/storage/thumbnails/generated/$uuid/";
        if (strpos($path, $expectedPath) === false) {
             return ['success' => false, 'message' => 'Ruta de miniatura inválida o no pertenece a este video.'];
        }

        if (!preg_match('/^#[a-f0-9]{6}$/i', $color)) {
            $color = '#000000'; 
        }

        try {
            $stmt = $this->pdo->prepare("UPDATE videos SET thumbnail_path = ?, dominant_color = ? WHERE uuid = ?");
            $stmt->execute([$path, $color, $uuid]);
            return ['success' => true, 'message' => 'Miniatura actualizada.'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Error al actualizar miniatura en BD.'];
        }
    }

    public function saveMetadata($uuid, $data) {
        if (!$this->isOwner($uuid)) return ['success' => false, 'message' => 'Acceso denegado'];

        if (Utils::checkSecurityLimit($this->pdo, 'save_draft_limit', 15, 10, $this->userId)) {
            return ['success' => false, 'message' => 'Estás guardando muy rápido. Por favor espera un momento.'];
        }
        Utils::logSecurityAction($this->pdo, 'save_draft_limit', 10, $this->userId);

        $title = trim($data['title']);
        $desc = trim($data['description']);
        $publish = $data['publish'];

        // [MODIFICACIÓN 2] Decodificar arrays de tags
        $categories = isset($data['categories']) ? json_decode($data['categories'], true) : [];
        $actors = isset($data['actors']) ? json_decode($data['actors'], true) : [];

        if (empty($title)) return ['success' => false, 'message' => 'El título es obligatorio'];

        try {
            $this->pdo->beginTransaction(); 

            // 1. Obtener ID numérico del video
            $stmtId = $this->pdo->prepare("SELECT id, status, thumbnail_path FROM videos WHERE uuid = ?");
            $stmtId->execute([$uuid]);
            $video = $stmtId->fetch(PDO::FETCH_ASSOC);
            $videoId = $video['id'];

            // 2. Actualizar datos básicos
            $stmt = $this->pdo->prepare("UPDATE videos SET title = ?, description = ? WHERE id = ?");
            $stmt->execute([$title, $desc, $videoId]);

            // 3. Procesar Categorías
            // Limpiar relaciones previas
            $delCats = $this->pdo->prepare("DELETE FROM video_categories_map WHERE video_id = ?");
            $delCats->execute([$videoId]);

            if (!empty($categories)) {
                $insCatMap = $this->pdo->prepare("INSERT IGNORE INTO video_categories_map (video_id, category_id) VALUES (?, ?)");
                $findCat = $this->pdo->prepare("SELECT id FROM video_categories WHERE name = ?");
                $createCat = $this->pdo->prepare("INSERT INTO video_categories (name, slug) VALUES (?, ?)");

                foreach ($categories as $cat) {
                    $catName = trim($cat['name']);
                    if (empty($catName)) continue;

                    $catId = $cat['id'] ?? null;

                    // Si no tiene ID (es nuevo), buscamos o creamos
                    if (!$catId) {
                        $findCat->execute([$catName]);
                        $existingId = $findCat->fetchColumn();

                        if ($existingId) {
                            $catId = $existingId;
                        } else {
                            $slug = Utils::slugify($catName);
                            try {
                                $createCat->execute([$catName, $slug]);
                                $catId = $this->pdo->lastInsertId();
                            } catch (Exception $ex) {
                                // Si falla por duplicado de slug, intentamos recuperar de nuevo
                                $findCat->execute([$catName]);
                                $catId = $findCat->fetchColumn();
                            }
                        }
                    }

                    if ($catId) {
                        $insCatMap->execute([$videoId, $catId]);
                    }
                }
            }

            // 4. Procesar Actores (Cast)
            // Limpiar relaciones previas
            $delCast = $this->pdo->prepare("DELETE FROM video_actors_map WHERE video_id = ?");
            $delCast->execute([$videoId]);

            if (!empty($actors)) {
                $insCastMap = $this->pdo->prepare("INSERT IGNORE INTO video_actors_map (video_id, actor_id) VALUES (?, ?)");
                $findActor = $this->pdo->prepare("SELECT id FROM video_actors WHERE name = ?");
                $createActor = $this->pdo->prepare("INSERT INTO video_actors (name, slug, type) VALUES (?, ?, 'other')");

                foreach ($actors as $actor) {
                    $actorName = trim($actor['name']);
                    if (empty($actorName)) continue;

                    $actorId = $actor['id'] ?? null;

                    if (!$actorId) {
                        $findActor->execute([$actorName]);
                        $existingId = $findActor->fetchColumn();

                        if ($existingId) {
                            $actorId = $existingId;
                        } else {
                            $slug = Utils::slugify($actorName);
                            try {
                                $createActor->execute([$actorName, $slug]);
                                $actorId = $this->pdo->lastInsertId();
                            } catch (Exception $ex) {
                                $findActor->execute([$actorName]);
                                $actorId = $findActor->fetchColumn();
                            }
                        }
                    }

                    if ($actorId) {
                        $insCastMap->execute([$videoId, $actorId]);
                    }
                }
            }

            // 5. Miniatura (si se seleccionó una de las generadas)
            if (!empty($data['selected_thumbnail'])) {
                $expectedPath = "public/storage/thumbnails/generated/$uuid/";
                
                if (strpos($data['selected_thumbnail'], $expectedPath) !== false) {
                    $thumbSql = "UPDATE videos SET thumbnail_path = ?, dominant_color = ? WHERE id = ?";
                    $thumbStmt = $this->pdo->prepare($thumbSql);
                    $color = $data['dominant_color'] ?? '#000000';
                    $thumbStmt->execute([$data['selected_thumbnail'], $color, $videoId]);
                }
            }

            // 6. Lógica de Publicación
            if ($publish === true || $publish === 'true') {
                $check = $this->pdo->prepare("SELECT status, thumbnail_path FROM videos WHERE id = ?");
                $check->execute([$videoId]);
                $videoInfo = $check->fetch();

                $hasThumbnail = !empty($data['selected_thumbnail']) || !empty($videoInfo['thumbnail_path']);

                if ($videoInfo['status'] !== 'waiting_for_metadata') {
                    $this->pdo->rollBack();
                    return [
                        'success' => false, 
                        'message' => 'El video aún se está procesando o no está listo.'
                    ];
                }

                if (!$hasThumbnail) {
                    $this->pdo->rollBack();
                    return ['success' => false, 'message' => 'Debes subir una miniatura antes de publicar.'];
                }

                $upd = $this->pdo->prepare("UPDATE videos SET status = 'published' WHERE id = ?");
                $upd->execute([$videoId]);

                $this->pdo->commit();
                return ['success' => true, 'published' => true, 'message' => '¡Video publicado exitosamente!'];
            }

            $this->pdo->commit();
            return ['success' => true, 'published' => false, 'message' => 'Borrador guardado'];

        } catch (Exception $e) {
            $this->pdo->rollBack();
            error_log($e->getMessage()); // Debug
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
                    $v['thumbnail_src'] = $v['thumbnail_path'];
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
                           duration, processing_percentage, error_message, dominant_color, views_count, likes_count,
                           (SELECT COUNT(*) FROM video_comments WHERE video_id = videos.id) as comments_count
                    FROM videos 
                    $whereClause 
                    ORDER BY created_at DESC 
                    LIMIT $limit OFFSET $offset";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $videos = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($videos as &$v) {
                if ($v['thumbnail_path']) {
                    $v['thumbnail_url'] = $v['thumbnail_path'];
                } else {
                    $v['thumbnail_url'] = null;
                }
                
                if ($v['duration']) {
                    $minutes = floor($v['duration'] / 60);
                    $seconds = $v['duration'] % 60;
                    $v['duration_formatted'] = sprintf("%02d:%02d", $minutes, $seconds);
                } else {
                    $v['duration_formatted'] = '--:--';
                }
                
                $v['time_ago'] = Utils::timeElapsedString($v['created_at']);

                // [OPCIONAL] Si quieres mostrar visitas reales también en el panel de "Mi Contenido"
                $v['views'] = $this->getRealViewCount($v['uuid'], $v['views_count']);
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
            
            if ($this->redis) {
                $this->redis->del("upload_token:{$v['uuid']}");
                $this->redis->del("lock:upload:{$v['uuid']}");
            }

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

        $stmt = $this->pdo->prepare("SELECT raw_file_path, thumbnail_path, hls_path, generated_thumbnails, sprite_path, vtt_path FROM videos WHERE uuid = ?");
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

        if (!empty($video['sprite_path'])) {
            $spritePath = __DIR__ . '/../../' . $video['sprite_path'];
            if (file_exists($spritePath)) @unlink($spritePath);
        }
        if (!empty($video['vtt_path'])) {
            $vttPath = __DIR__ . '/../../' . $video['vtt_path'];
            if (file_exists($vttPath)) @unlink($vttPath);
        }

        $videoFolder = $this->publicVideoDir . '/' . $uuid;
        if (is_dir($videoFolder)) {
            $this->deleteDirectory($videoFolder);
        }

        $generatedThumbsDir = $this->thumbnailDir . '/generated/' . $uuid;
        if (is_dir($generatedThumbsDir)) {
            $this->deleteDirectory($generatedThumbsDir);
        }

        if ($this->redis) {
            $this->redis->del("upload_token:$uuid");
            $this->redis->del("lock:upload:$uuid");
            // Limpiamos también el buffer de visitas si existiera
            $this->redis->del("video:buffer:views:$uuid");
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