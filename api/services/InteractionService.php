<?php
// api/services/InteractionService.php

namespace Aurora\Services;

use Aurora\Libs\Utils;
use Exception;
use PDO;

class InteractionService {
    private $pdo;
    private $i18n;
    private $redis;

    public function __construct($pdo, $i18n, $redis) {
        $this->pdo = $pdo;
        $this->i18n = $i18n;
        $this->redis = $redis;
    }

    public function toggleLike($videoUuid, $type = 'like') {
        if (!isset($_SESSION['user_id'])) {
            return ['success' => false, 'message' => $this->i18n->t('api.auth_required'), 'require_login' => true];
        }

        $userId = $_SESSION['user_id'];
        $allowedTypes = ['like', 'dislike'];
        
        if (!in_array($type, $allowedTypes)) $type = 'like';

        try {
            $stmt = $this->pdo->prepare("SELECT id FROM videos WHERE uuid = ?");
            $stmt->execute([$videoUuid]);
            $video = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$video) {
                return ['success' => false, 'message' => $this->i18n->t('api.video_not_found')];
            }

            $videoId = $video['id'];

            $this->pdo->beginTransaction();

            $check = $this->pdo->prepare("SELECT type FROM video_interactions WHERE user_id = ? AND video_id = ?");
            $check->execute([$userId, $videoId]);
            $existing = $check->fetch(PDO::FETCH_ASSOC);

            $actionPerformed = '';
            
            if ($existing) {
                if ($existing['type'] === $type) {
                    $del = $this->pdo->prepare("DELETE FROM video_interactions WHERE user_id = ? AND video_id = ?");
                    $del->execute([$userId, $videoId]);
                    $actionPerformed = 'removed';
                } else {
                    $upd = $this->pdo->prepare("UPDATE video_interactions SET type = ? WHERE user_id = ? AND video_id = ?");
                    $upd->execute([$type, $userId, $videoId]);
                    $actionPerformed = 'switched';
                }
            } else {
                $ins = $this->pdo->prepare("INSERT INTO video_interactions (user_id, video_id, type) VALUES (?, ?, ?)");
                $ins->execute([$userId, $videoId, $type]);
                $actionPerformed = 'added';
            }

            $this->pdo->commit();

            $stats = $this->syncVideoStats($videoId, $videoUuid);

            return [
                'success' => true,
                'action' => $actionPerformed,
                'type' => $type,
                'likes' => (int)$stats['likes'],
                'dislikes' => (int)$stats['dislikes']
            ];

        } catch (Exception $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            return ['success' => false, 'message' => 'Error processing interaction: ' . $e->getMessage()];
        }
    }

    private function syncVideoStats($videoId, $videoUuid) {
        $stats = ['likes' => 0, 'dislikes' => 0];

        try {
            $stmtLike = $this->pdo->prepare("SELECT COUNT(*) FROM video_interactions WHERE video_id = ? AND type = 'like'");
            $stmtLike->execute([$videoId]);
            $stats['likes'] = $stmtLike->fetchColumn();

            $stmtDislike = $this->pdo->prepare("SELECT COUNT(*) FROM video_interactions WHERE video_id = ? AND type = 'dislike'");
            $stmtDislike->execute([$videoId]);
            $stats['dislikes'] = $stmtDislike->fetchColumn();

            if ($this->redis) {
                $redisKey = "video:stats:{$videoUuid}";
                $this->redis->hmset($redisKey, [
                    'likes' => $stats['likes'],
                    'dislikes' => $stats['dislikes']
                ]);
            }
        } catch (Exception $e) {}

        return $stats;
    }

    public function toggleSubscribe($channelUuid) {
        if (!isset($_SESSION['user_id'])) {
            return ['success' => false, 'message' => $this->i18n->t('api.auth_required'), 'require_login' => true];
        }

        $subscriberId = $_SESSION['user_id'];

        if ($_SESSION['uuid'] === $channelUuid) {
            return ['success' => false, 'message' => 'No puedes suscribirte a ti mismo'];
        }

        try {
            $stmt = $this->pdo->prepare("SELECT id FROM users WHERE uuid = ?");
            $stmt->execute([$channelUuid]);
            $channel = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$channel) {
                return ['success' => false, 'message' => 'Canal no encontrado'];
            }

            $channelId = $channel['id'];
            $redisKey = "user:stats:{$channelUuid}";

            $this->pdo->beginTransaction();

            $check = $this->pdo->prepare("SELECT id FROM subscriptions WHERE subscriber_id = ? AND channel_id = ?");
            $check->execute([$subscriberId, $channelId]);
            $exists = $check->fetch();

            $isSubscribed = false;

            if ($exists) {
                $this->pdo->prepare("DELETE FROM subscriptions WHERE subscriber_id = ? AND channel_id = ?")
                          ->execute([$subscriberId, $channelId]);
                $isSubscribed = false;
            } else {
                $this->pdo->prepare("INSERT INTO subscriptions (subscriber_id, channel_id) VALUES (?, ?)")
                          ->execute([$subscriberId, $channelId]);
                $isSubscribed = true;
            }

            $this->pdo->commit();

            $subCount = 0;
            try {
                $countStmt = $this->pdo->prepare("SELECT COUNT(*) FROM subscriptions WHERE channel_id = ?");
                $countStmt->execute([$channelId]);
                $subCount = $countStmt->fetchColumn();

                if ($this->redis) {
                    $this->redis->hset($redisKey, 'subscribers', $subCount);
                }
            } catch (Exception $e) {}

            return [
                'success' => true,
                'subscribed' => $isSubscribed,
                'subscribers_count' => (int)$subCount
            ];

        } catch (Exception $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            return ['success' => false, 'message' => 'Error en suscripción'];
        }
    }

    public function registerView($videoUuid) {
        if (!$this->redis) return ['success' => false];

        $ip = Utils::getClientIp();
        $cooldownKey = "view:cooldown:{$videoUuid}:{$ip}";
        
        if ($this->redis->exists($cooldownKey)) {
            return ['success' => true, 'status' => 'ignored_cooldown'];
        }

        try {
            $this->redis->setex($cooldownKey, 1800, '1');

            $bufferKey = "video:buffer:views:{$videoUuid}";
            $currentBuffer = $this->redis->incr($bufferKey);
            
            $logEntry = json_encode([
                'video_uuid' => $videoUuid,
                'user_id' => $_SESSION['user_id'] ?? null,
                'ip' => $ip,
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
                'timestamp' => time()
            ]);
            
            $this->redis->rpush('video:logs:buffer', $logEntry);

            return [
                'success' => true, 
                'status' => 'buffered', 
                'buffer_val' => $currentBuffer
            ];

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function registerShare($videoUuid) {
        $ip = Utils::getClientIp();
        $userId = $_SESSION['user_id'] ?? null;

        try {
            $stmt = $this->pdo->prepare("SELECT id FROM videos WHERE uuid = ?");
            $stmt->execute([$videoUuid]);
            $videoId = $stmt->fetchColumn();

            if (!$videoId) {
                return ['success' => false, 'message' => 'Video not found'];
            }

            $ins = $this->pdo->prepare("INSERT INTO video_shares (video_id, user_id, ip_address) VALUES (?, ?, ?)");
            $ins->execute([$videoId, $userId, $ip]);

            return ['success' => true];

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    // ==========================================
    // SECCIÓN DE COMENTARIOS (CORREGIDO NATIVO)
    // ==========================================

    public function getComments($videoUuid, $limit = 50, $offset = 0) {
        try {
            $stmtV = $this->pdo->prepare("SELECT id FROM videos WHERE uuid = ?");
            $stmtV->execute([$videoUuid]);
            $videoId = $stmtV->fetchColumn();

            if (!$videoId) return ['success' => false, 'message' => 'Video no encontrado'];

            $currentUserId = $_SESSION['user_id'] ?? 0;

            // 1. OBTENER COMENTARIOS PERSISTENTES (MySQL)
            $sql = "
                SELECT c.id, c.uuid, c.parent_id, c.content, c.created_at, 
                       u.username, u.avatar_path, u.uuid as user_uuid,
                       (SELECT COUNT(*) FROM comment_interactions ci WHERE ci.comment_id = c.id AND ci.type = 'like') as likes_count,
                       (SELECT type FROM comment_interactions ci WHERE ci.comment_id = c.id AND ci.user_id = :current_user_id) as user_interaction
                FROM video_comments c
                JOIN users u ON c.user_id = u.id
                WHERE c.video_id = :video_id
                ORDER BY c.created_at DESC
                LIMIT :limit OFFSET :offset
            ";

            $stmt = $this->pdo->prepare($sql);
            $stmt->bindParam(':current_user_id', $currentUserId, PDO::PARAM_INT);
            $stmt->bindParam(':video_id', $videoId, PDO::PARAM_INT);
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            
            $mysqlComments = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // 2. OBTENER COMENTARIOS EN BUFFER (Redis - Comandos Nativos)
            $redisComments = [];
            if ($this->redis && $offset === 0) {
                $bufferKey = "video:{$videoUuid}:comments_buffer";
                
                // CORRECCIÓN: Usamos 'lrange' nativo (0 a -1 es todo)
                // getList no existe en el cliente crudo de Redis
                $rawBuffer = $this->redis->lrange($bufferKey, 0, -1); 

                foreach ($rawBuffer as $itemJson) {
                    $item = json_decode($itemJson, true);
                    if ($item) {
                        $redisComments[] = [
                            'id' => $item['uuid'], 
                            'uuid' => $item['uuid'],
                            'content' => $item['content'],
                            'created_at' => date('Y-m-d H:i:s', $item['created_at']),
                            'username' => $item['username'],
                            'user_uuid' => $item['user_uuid'],
                            'avatar_path' => $item['user_avatar'],
                            'parent_id' => $item['parent_id'],
                            'likes_count' => 0,
                            'user_interaction' => null,
                            'is_pending' => true 
                        ];
                    }
                }
            }

            // 3. FUSIONAR (Redis primero)
            $allComments = array_merge($redisComments, $mysqlComments);

            // Procesar Avatares y Estructura
            $parents = [];
            $replies = [];

            foreach ($allComments as &$comment) {
                $comment['avatar_url'] = $this->processAvatarUrl($comment['avatar_path'], $comment['username']);
                $comment['is_reply'] = !empty($comment['parent_id']);
                $comment['likes_count'] = (int)$comment['likes_count'];
                
                if ($comment['is_reply']) {
                    $replies[] = $comment;
                } else {
                    $comment['replies'] = [];
                    // Usamos el ID numérico (MySQL) o UUID (Redis) como key temporal
                    $key = $comment['id']; 
                    $parents[$key] = $comment;
                }
            }

            foreach ($replies as $reply) {
                $pid = $reply['parent_id'];
                
                // Intento simple de anidar (solo funciona si el padre está cargado)
                // Si el padre es de Redis, su 'id' es el UUID. Si es MySQL, es INT.
                // El parent_id del hijo debe coincidir.
                $foundParent = false;
                
                // Buscar por coincidencia directa de ID
                if (isset($parents[$pid])) {
                    array_unshift($parents[$pid]['replies'], $reply);
                    $foundParent = true;
                } else {
                    // Búsqueda más exhaustiva por si acaso (mezcla int/string)
                    foreach ($parents as &$p) {
                         // Comparamos UUIDs si existen, o IDs
                         $pId = $p['id'];
                         // Si el comentario padre es de MySQL (int) y la respuesta dice parent_id (int), coinciden.
                         if ($pId == $pid) {
                             array_unshift($p['replies'], $reply);
                             $foundParent = true;
                             break;
                         }
                    }
                }
                
                if (!$foundParent) {
                    $reply['is_orphaned_reply'] = true;
                    $parents[$reply['id']] = $reply;
                }
            }

            return [
                'success' => true,
                'comments' => array_values($parents), 
                'total_count' => count($allComments) 
            ];

        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function addComment($videoUuid, $content, $parentId = null) {
        if (!isset($_SESSION['user_id'])) {
            return ['success' => false, 'message' => $this->i18n->t('api.auth_required'), 'require_login' => true];
        }

        $userId = $_SESSION['user_id'];
        $content = trim($content);

        if (mb_strlen($content) > 10000) {
            return ['success' => false, 'message' => 'El comentario es demasiado largo.'];
        }

        if (empty($content)) {
            return ['success' => false, 'message' => 'El comentario no puede estar vacío'];
        }

        try {
            // Validación mínima de existencia
            $stmtV = $this->pdo->prepare("SELECT id FROM videos WHERE uuid = ?");
            $stmtV->execute([$videoUuid]);
            $videoId = $stmtV->fetchColumn();
            if (!$videoId) return ['success' => false, 'message' => 'Video no encontrado'];

            $stmtUser = $this->pdo->prepare("SELECT username, avatar_path, uuid FROM users WHERE id = ?");
            $stmtUser->execute([$userId]);
            $userData = $stmtUser->fetch(PDO::FETCH_ASSOC);

            $commentUuid = Utils::generateUuid();
            $timestamp = time();

            $payload = [
                'uuid' => $commentUuid,
                'video_uuid' => $videoUuid,
                'user_id' => $userId,
                'username' => $userData['username'],
                'user_uuid' => $userData['uuid'],
                'user_avatar' => $userData['avatar_path'],
                'content' => htmlspecialchars($content), 
                'parent_id' => $parentId,
                'created_at' => $timestamp
            ];

            if ($this->redis) {
                // CORRECCIÓN: Usamos comandos nativos RPUSH y LPUSH
                $jsonPayload = json_encode($payload);

                // A) COLA DE PROCESAMIENTO (FIFO -> rpush)
                $this->redis->rpush('queue:comments_processing', $jsonPayload);

                // B) BUFFER DE VISUALIZACIÓN (LIFO -> lpush)
                $this->redis->lpush("video:{$videoUuid}:comments_buffer", $jsonPayload);
            } else {
                // FALLBACK MySQL
                $stmt = $this->pdo->prepare("
                    INSERT INTO video_comments (uuid, video_id, user_id, parent_id, content, created_at) 
                    VALUES (?, ?, ?, ?, ?, FROM_UNIXTIME(?))
                ");
                $stmt->execute([$commentUuid, $videoId, $userId, $parentId, $content, $timestamp]);
            }

            return [
                'success' => true,
                'comment' => [
                    'id' => $commentUuid, 
                    'uuid' => $commentUuid,
                    'content' => htmlspecialchars($content),
                    'parent_id' => $parentId,
                    'created_at' => date('Y-m-d H:i:s', $timestamp),
                    'username' => $userData['username'],
                    'user_uuid' => $userData['uuid'],
                    'avatar_url' => $this->processAvatarUrl($userData['avatar_path'], $userData['username']),
                    'replies' => [],
                    'likes_count' => 0,
                    'dislikes_count' => 0, 
                    'user_interaction' => null,
                    'is_pending' => true 
                ]
            ];

        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Error al comentar: ' . $e->getMessage()];
        }
    }

    public function toggleCommentLike($commentId, $type = 'like') {
        if (!isset($_SESSION['user_id'])) {
            return ['success' => false, 'message' => $this->i18n->t('api.auth_required'), 'require_login' => true];
        }

        $userId = $_SESSION['user_id'];
        $allowedTypes = ['like', 'dislike'];
        if (!in_array($type, $allowedTypes)) $type = 'like';

        try {
            $stmtC = $this->pdo->prepare("SELECT id FROM video_comments WHERE id = ? OR uuid = ?");
            $stmtC->execute([$commentId, $commentId]);
            $commentRow = $stmtC->fetch(PDO::FETCH_ASSOC);
            
            if (!$commentRow) {
                return ['success' => false, 'message' => 'Comentario no encontrado o procesando...'];
            }
            
            $realCommentId = $commentRow['id']; 

            $this->pdo->beginTransaction();

            $check = $this->pdo->prepare("SELECT type FROM comment_interactions WHERE user_id = ? AND comment_id = ?");
            $check->execute([$userId, $realCommentId]);
            $existing = $check->fetch(PDO::FETCH_ASSOC);

            $actionPerformed = '';

            if ($existing) {
                if ($existing['type'] === $type) {
                    $del = $this->pdo->prepare("DELETE FROM comment_interactions WHERE user_id = ? AND comment_id = ?");
                    $del->execute([$userId, $realCommentId]);
                    $actionPerformed = 'removed';
                } else {
                    $upd = $this->pdo->prepare("UPDATE comment_interactions SET type = ? WHERE user_id = ? AND comment_id = ?");
                    $upd->execute([$type, $userId, $realCommentId]);
                    $actionPerformed = 'switched';
                }
            } else {
                $ins = $this->pdo->prepare("INSERT INTO comment_interactions (user_id, comment_id, type) VALUES (?, ?, ?)");
                $ins->execute([$userId, $realCommentId, $type]);
                $actionPerformed = 'added';
            }

            $this->pdo->commit();
            $stats = $this->getCommentStats($realCommentId);

            return [
                'success' => true,
                'action' => $actionPerformed,
                'type' => $type,
                'likes' => (int)$stats['likes'],
                'dislikes' => 0 
            ];

        } catch (Exception $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
        }
    }

    private function getCommentStats($commentId) {
        $stats = ['likes' => 0];
        try {
            $stmtLike = $this->pdo->prepare("SELECT COUNT(*) FROM comment_interactions WHERE comment_id = ? AND type = 'like'");
            $stmtLike->execute([$commentId]);
            $stats['likes'] = $stmtLike->fetchColumn();
        } catch (Exception $e) {}
        return $stats;
    }

    private function processAvatarUrl($path, $username) {
        if (!empty($path) && file_exists(__DIR__ . '/../../' . $path)) {
            return '/ProjectAurora/' . ltrim($path, '/');
        }
        return "https://ui-avatars.com/api/?name=" . urlencode($username) . "&background=333&color=fff";
    }
}
?>