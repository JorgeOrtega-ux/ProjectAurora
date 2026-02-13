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
     * Esto evita el bug de "-1 Likes" si la caché estaba vacía.
     */
    public function toggleLike($videoUuid, $type = 'like') {
        if (!isset($_SESSION['user_id'])) {
            return ['success' => false, 'message' => $this->i18n->t('api.auth_required'), 'require_login' => true];
        }

        $userId = $_SESSION['user_id'];
        $allowedTypes = ['like', 'dislike'];
        
        // Normalización forzada para evitar errores de mayúsculas/minúsculas
        if (!in_array($type, $allowedTypes)) $type = 'like';

        try {
            // 1. Obtener ID numérico del video
            $stmt = $this->pdo->prepare("SELECT id FROM videos WHERE uuid = ?");
            $stmt->execute([$videoUuid]);
            $video = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$video) {
                return ['success' => false, 'message' => $this->i18n->t('api.video_not_found')];
            }

            $videoId = $video['id'];

            // INICIO TRANSACCIÓN
            $this->pdo->beginTransaction();

            // Verificar estado actual
            $check = $this->pdo->prepare("SELECT type FROM video_interactions WHERE user_id = ? AND video_id = ?");
            $check->execute([$userId, $videoId]);
            $existing = $check->fetch(PDO::FETCH_ASSOC);

            $actionPerformed = '';
            
            if ($existing) {
                if ($existing['type'] === $type) {
                    // CASO A: Quitar interacción (Toggle Off)
                    $del = $this->pdo->prepare("DELETE FROM video_interactions WHERE user_id = ? AND video_id = ?");
                    $del->execute([$userId, $videoId]);
                    $actionPerformed = 'removed';
                } else {
                    // CASO B: Cambiar opinión (Switch)
                    $upd = $this->pdo->prepare("UPDATE video_interactions SET type = ? WHERE user_id = ? AND video_id = ?");
                    $upd->execute([$type, $userId, $videoId]);
                    $actionPerformed = 'switched';
                }
            } else {
                // CASO C: Nueva interacción (Add)
                $ins = $this->pdo->prepare("INSERT INTO video_interactions (user_id, video_id, type) VALUES (?, ?, ?)");
                $ins->execute([$userId, $videoId, $type]);
                $actionPerformed = 'added';
            }

            $this->pdo->commit();
            // FIN TRANSACCIÓN - La BD ya tiene el dato correcto (o borrado).

            // --- ACTUALIZACIÓN INTELIGENTE DE REDIS ---
            // En lugar de sumar/restar ciegamente, recalculamos los totales reales.
            // Esto corrige automáticamente cualquier desincronización (incluyendo el "-1").
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

    /**
     * Helper para contar Likes reales en BD y actualizar Redis.
     * Devuelve el array con los nuevos valores.
     */
    private function syncVideoStats($videoId, $videoUuid) {
        $stats = ['likes' => 0, 'dislikes' => 0];

        try {
            // 1. Contar Likes Reales
            $stmtLike = $this->pdo->prepare("SELECT COUNT(*) FROM video_interactions WHERE video_id = ? AND type = 'like'");
            $stmtLike->execute([$videoId]);
            $stats['likes'] = $stmtLike->fetchColumn();

            // 2. Contar Dislikes Reales
            $stmtDislike = $this->pdo->prepare("SELECT COUNT(*) FROM video_interactions WHERE video_id = ? AND type = 'dislike'");
            $stmtDislike->execute([$videoId]);
            $stats['dislikes'] = $stmtDislike->fetchColumn();

            // 3. Actualizar Redis (Setear valor absoluto, no incremental)
            if ($this->redis) {
                $redisKey = "video:stats:{$videoUuid}";
                $this->redis->hmset($redisKey, [
                    'likes' => $stats['likes'],
                    'dislikes' => $stats['dislikes']
                ]);
            }
        } catch (Exception $e) {
            // Si falla el recálculo, al menos no rompemos la respuesta principal,
            // pero logueamos el error.
            // Logger::error("Error sync stats: " . $e->getMessage());
        }

        return $stats;
    }

    /**
     * Lógica para Suscripciones
     */
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
                // Desuscribirse
                $this->pdo->prepare("DELETE FROM subscriptions WHERE subscriber_id = ? AND channel_id = ?")
                          ->execute([$subscriberId, $channelId]);
                $isSubscribed = false;
            } else {
                // Suscribirse
                $this->pdo->prepare("INSERT INTO subscriptions (subscriber_id, channel_id) VALUES (?, ?)")
                          ->execute([$subscriberId, $channelId]);
                $isSubscribed = true;
            }

            $this->pdo->commit();

            // --- ACTUALIZACIÓN INTELIGENTE DE SUBS ---
            // Igual que con likes, contamos la realidad.
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

    /**
     * Registro de Visitas con Debounce y Cola
     */
    public function registerView($videoUuid) {
        if (!$this->redis) return ['success' => false];

        $ip = Utils::getClientIp();
        $cooldownKey = "view:cooldown:{$videoUuid}:{$ip}";
        
        if ($this->redis->exists($cooldownKey)) {
            return ['success' => true, 'status' => 'ignored_cooldown'];
        }

        try {
            // 2. Marcar cooldown
            $this->redis->setex($cooldownKey, 1800, '1');

            // 3. INCREMENTO ATÓMICO EN REDIS (El Buffer)
            $bufferKey = "video:buffer:views:{$videoUuid}";
            $currentBuffer = $this->redis->incr($bufferKey);
            
            // 4. [NUEVO] Despachar Tarea de Analítica al Worker
            // Esto llena la tabla video_views (historial)
            $jobPayload = json_encode([
                'task' => 'register_view_persistence',
                'payload' => [
                    'video_uuid' => $videoUuid,
                    'user_id' => $_SESSION['user_id'] ?? null,
                    'ip' => $ip,
                    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
                    'timestamp' => time()
                ]
            ]);
            
            // Insertar en la cola 'aurora_task_queue'
            $this->redis->rpush('aurora_task_queue', $jobPayload);

            return [
                'success' => true, 
                'status' => 'buffered', 
                'buffer_val' => $currentBuffer
            ];

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
?>