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
     * Lógica Híbrida para Likes:
     * 1. MySQL (Síncrono): Guarda el estado "Yo le di like" (Source of Truth).
     * 2. Redis (Inmediato): Actualiza el contador (+1/-1) para feedback visual instantáneo.
     */
    public function toggleLike($videoUuid, $type = 'like') {
        if (!isset($_SESSION['user_id'])) {
            return ['success' => false, 'message' => $this->i18n->t('api.auth_required'), 'require_login' => true];
        }

        $userId = $_SESSION['user_id'];
        $allowedTypes = ['like', 'dislike'];
        if (!in_array($type, $allowedTypes)) $type = 'like';

        try {
            // 1. Obtener ID numérico del video (Más rápido para JOINS)
            $stmt = $this->pdo->prepare("SELECT id FROM videos WHERE uuid = ?");
            $stmt->execute([$videoUuid]);
            $video = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$video) {
                return ['success' => false, 'message' => $this->i18n->t('api.video_not_found')];
            }

            $videoId = $video['id'];
            $redisKey = "video:stats:{$videoUuid}";

            // INICIO TRANSACCIÓN MYSQL (Bloqueo para consistencia)
            $this->pdo->beginTransaction();

            // Verificar estado actual
            $check = $this->pdo->prepare("SELECT type FROM video_interactions WHERE user_id = ? AND video_id = ?");
            $check->execute([$userId, $videoId]);
            $existing = $check->fetch(PDO::FETCH_ASSOC);

            $actionPerformed = '';
            
            if ($existing) {
                if ($existing['type'] === $type) {
                    // CASO A: Quitar interacción (ej. dio Like y volvió a dar Like)
                    $del = $this->pdo->prepare("DELETE FROM video_interactions WHERE user_id = ? AND video_id = ?");
                    $del->execute([$userId, $videoId]);
                    
                    // Redis: Restar al contador correspondiente
                    if ($this->redis) {
                        $field = ($type === 'like') ? 'likes' : 'dislikes';
                        $this->redis->hincrby($redisKey, $field, -1);
                    }
                    $actionPerformed = 'removed';
                } else {
                    // CASO B: Cambiar opinión (ej. tenía Dislike y dio Like)
                    $upd = $this->pdo->prepare("UPDATE video_interactions SET type = ? WHERE user_id = ? AND video_id = ?");
                    $upd->execute([$type, $userId, $videoId]);

                    // Redis: Restar al viejo, Sumar al nuevo
                    if ($this->redis) {
                        $oldField = ($existing['type'] === 'like') ? 'likes' : 'dislikes';
                        $newField = ($type === 'like') ? 'likes' : 'dislikes';
                        $this->redis->hincrby($redisKey, $oldField, -1);
                        $this->redis->hincrby($redisKey, $newField, 1);
                    }
                    $actionPerformed = 'switched';
                }
            } else {
                // CASO C: Nueva interacción
                $ins = $this->pdo->prepare("INSERT INTO video_interactions (user_id, video_id, type) VALUES (?, ?, ?)");
                $ins->execute([$userId, $videoId, $type]);

                // Redis: Sumar
                if ($this->redis) {
                    $field = ($type === 'like') ? 'likes' : 'dislikes';
                    $this->redis->hincrby($redisKey, $field, 1);
                }
                $actionPerformed = 'added';
            }

            $this->pdo->commit();

            // Obtener contadores frescos de Redis para actualizar UI
            $stats = [];
            if ($this->redis) {
                $stats = $this->redis->hmget($redisKey, ['likes', 'dislikes']);
            }

            return [
                'success' => true,
                'action' => $actionPerformed,
                'type' => $type,
                'likes' => (int)($stats[0] ?? 0),
                'dislikes' => (int)($stats[1] ?? 0)
            ];

        } catch (Exception $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            return ['success' => false, 'message' => 'Error processing interaction: ' . $e->getMessage()];
        }
    }

    /**
     * Lógica para Suscripciones
     */
    public function toggleSubscribe($channelUuid) {
        if (!isset($_SESSION['user_id'])) {
            return ['success' => false, 'message' => $this->i18n->t('api.auth_required'), 'require_login' => true];
        }

        $subscriberId = $_SESSION['user_id'];

        // Evitar auto-suscripción
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

            // Verificar si ya sigue
            $check = $this->pdo->prepare("SELECT id FROM subscriptions WHERE subscriber_id = ? AND channel_id = ?");
            $check->execute([$subscriberId, $channelId]);
            $exists = $check->fetch();

            $isSubscribed = false;

            if ($exists) {
                // Desuscribirse
                $this->pdo->prepare("DELETE FROM subscriptions WHERE subscriber_id = ? AND channel_id = ?")
                          ->execute([$subscriberId, $channelId]);
                
                if ($this->redis) $this->redis->hincrby($redisKey, 'subscribers', -1);
                $isSubscribed = false;
            } else {
                // Suscribirse
                $this->pdo->prepare("INSERT INTO subscriptions (subscriber_id, channel_id) VALUES (?, ?)")
                          ->execute([$subscriberId, $channelId]);
                
                if ($this->redis) $this->redis->hincrby($redisKey, 'subscribers', 1);
                $isSubscribed = true;
            }

            $this->pdo->commit();

            // Obtener contador actualizado
            $subCount = 0;
            if ($this->redis) {
                $subCount = $this->redis->hget($redisKey, 'subscribers');
            }

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
     * 1. Verifica Cooldown en Redis (por IP y Video).
     * 2. Si es válido: Incrementa Redis instantáneo.
     * 3. Envía tarea al Worker para persistir en MySQL (video_views log).
     */
// api/services/InteractionService.php

public function registerView($videoUuid) {
    if (!$this->redis) return ['success' => false];

    $ip = Utils::getClientIp();
    $cooldownKey = "view:cooldown:{$videoUuid}:{$ip}";
    
    // 1. Debounce (Protección F5 - 30 minutos)
    if ($this->redis->exists($cooldownKey)) {
        return ['success' => true, 'status' => 'ignored_cooldown'];
    }

    try {
        // 2. Marcar cooldown
        $this->redis->setex($cooldownKey, 1800, '1');

        // 3. INCREMENTO ATÓMICO EN REDIS (El Buffer)
        // Usamos una key especial: "video:buffer:views:{uuid}"
        $bufferKey = "video:buffer:views:{$videoUuid}";
        $currentBuffer = $this->redis->incr($bufferKey);

        // [OPCIONAL] También actualizamos la key visual antigua por si acaso
        $this->redis->hincrby("video:stats:{$videoUuid}", 'views', 1);

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