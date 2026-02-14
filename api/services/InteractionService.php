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

    /**
     * Lógica Híbrida Blindada:
     * 1. MySQL: Ejecuta la acción (Insert/Delete).
     * 2. Redis: SE RECALCULA el total real desde la BD.
     */
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
    // SECCIÓN DE COMENTARIOS (NUEVO)
    // ==========================================

    /**
     * Obtiene los comentarios estructurados (Padres con sus respuestas)
     */
    public function getComments($videoUuid, $limit = 50, $offset = 0) {
        try {
            $stmtV = $this->pdo->prepare("SELECT id FROM videos WHERE uuid = ?");
            $stmtV->execute([$videoUuid]);
            $videoId = $stmtV->fetchColumn();

            if (!$videoId) return ['success' => false, 'message' => 'Video no encontrado'];

            // Obtenemos todos los comentarios planos
            // Nota: En un sistema muy grande, esto se paginaría solo por padres.
            // Para Aurora versión inicial, traemos un lote y lo organizamos en PHP.
            $stmt = $this->pdo->prepare("
                SELECT c.id, c.parent_id, c.content, c.created_at, 
                       u.username, u.avatar_path, u.uuid as user_uuid
                FROM video_comments c
                JOIN users u ON c.user_id = u.id
                WHERE c.video_id = ?
                ORDER BY c.created_at DESC
                LIMIT ? OFFSET ?
            ");
            // PDO limit necesita integer binding real a veces, pero execute array suele castear a string.
            // Para seguridad en MySQL LIMIT, usamos bindParam si fuera estricto, 
            // pero la mayoría de drivers modernos lo manejan.
            $stmt->bindParam(1, $videoId, PDO::PARAM_INT);
            $stmt->bindParam(2, $limit, PDO::PARAM_INT);
            $stmt->bindParam(3, $offset, PDO::PARAM_INT);
            $stmt->execute();
            
            $rawComments = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Procesar Avatares y Estructura
            $parents = [];
            $replies = [];

            foreach ($rawComments as &$comment) {
                $comment['avatar_url'] = $this->processAvatarUrl($comment['avatar_path'], $comment['username']);
                $comment['is_reply'] = !empty($comment['parent_id']);
                
                if ($comment['is_reply']) {
                    $replies[] = $comment;
                } else {
                    $comment['replies'] = []; // Inicializar array de respuestas
                    $parents[$comment['id']] = $comment;
                }
            }

            // Asignar respuestas a sus padres
            // Nota: Como ordenamos por fecha DESC, las respuestas pueden salir antes que el padre
            // si no paginamos con cuidado. 
            // Para simplificar: Si el padre no está en este lote (ej. paginación), la respuesta queda huérfana en UI.
            // Solución robusta: Se suele cargar solo padres y pedir respuestas vía AJAX, 
            // pero aquí haremos "Attach if parent exists".
            foreach ($replies as $reply) {
                $pid = $reply['parent_id'];
                if (isset($parents[$pid])) {
                    // Añadimos la respuesta al padre (unshift para que queden en orden cronológico dentro del padre si queremos, o push)
                    // Generalmente respuestas: Oldest top.
                    array_unshift($parents[$pid]['replies'], $reply);
                } else {
                    // Si es huérfano (padre en otra página), podríamos devolverlo suelto o ignorarlo.
                    // Lo añadimos al final como 'root' fallback para que se vea
                    $reply['is_orphaned_reply'] = true;
                    $parents[$reply['id']] = $reply;
                }
            }

            return [
                'success' => true,
                'comments' => array_values($parents), // Reindexar para JSON
                'total_count' => count($rawComments) // Aproximado del lote
            ];

        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Publica un comentario (o respuesta)
     */
    public function addComment($videoUuid, $content, $parentId = null) {
        if (!isset($_SESSION['user_id'])) {
            return ['success' => false, 'message' => $this->i18n->t('api.auth_required'), 'require_login' => true];
        }

        $userId = $_SESSION['user_id'];
        $content = trim($content);

        if (empty($content)) {
            return ['success' => false, 'message' => 'El comentario no puede estar vacío'];
        }

        try {
            // 1. Validar Video
            $stmtV = $this->pdo->prepare("SELECT id FROM videos WHERE uuid = ?");
            $stmtV->execute([$videoUuid]);
            $videoId = $stmtV->fetchColumn();
            if (!$videoId) return ['success' => false, 'message' => 'Video no encontrado'];

            // 2. Lógica de Anidamiento (Máximo 1 Nivel)
            $finalParentId = null;

            if ($parentId) {
                // Verificar que el padre existe y pertenece al mismo video
                $stmtP = $this->pdo->prepare("SELECT id, parent_id FROM video_comments WHERE id = ? AND video_id = ?");
                $stmtP->execute([$parentId, $videoId]);
                $parentData = $stmtP->fetch(PDO::FETCH_ASSOC);

                if (!$parentData) {
                    return ['success' => false, 'message' => 'El comentario al que respondes no existe'];
                }

                // REGLA DE ORO 1 NIVEL:
                // Si intentamos responder a un comentario que YA es una respuesta ($parentData['parent_id'] no es null),
                // entonces nuestro nuevo comentario debe ser hermano, no hijo.
                // Asignamos como padre al padre del comentario objetivo.
                if (!empty($parentData['parent_id'])) {
                    $finalParentId = $parentData['parent_id'];
                } else {
                    $finalParentId = $parentData['id'];
                }
            }

            // 3. Insertar
            $stmt = $this->pdo->prepare("
                INSERT INTO video_comments (video_id, user_id, parent_id, content) 
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$videoId, $userId, $finalParentId, $content]);
            $newId = $this->pdo->lastInsertId();

            // 4. Retornar datos completos para inyección en DOM (Optimistic UI)
            $stmtUser = $this->pdo->prepare("SELECT username, avatar_path, uuid FROM users WHERE id = ?");
            $stmtUser->execute([$userId]);
            $userData = $stmtUser->fetch(PDO::FETCH_ASSOC);

            return [
                'success' => true,
                'comment' => [
                    'id' => $newId,
                    'content' => htmlspecialchars($content),
                    'parent_id' => $finalParentId,
                    'created_at' => date('Y-m-d H:i:s'), // Ahora mismo
                    'username' => $userData['username'],
                    'user_uuid' => $userData['uuid'],
                    'avatar_url' => $this->processAvatarUrl($userData['avatar_path'], $userData['username']),
                    'replies' => []
                ]
            ];

        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Error al comentar: ' . $e->getMessage()];
        }
    }

    private function processAvatarUrl($path, $username) {
        if (!empty($path) && file_exists(__DIR__ . '/../../' . $path)) {
            return '/ProjectAurora/' . ltrim($path, '/');
        }
        return "https://ui-avatars.com/api/?name=" . urlencode($username) . "&background=333&color=fff";
    }
}
?>