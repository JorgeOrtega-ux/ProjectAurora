<?php
// api/services/AlertService.php

namespace Aurora\Services;

use Aurora\Libs\Utils;
use Exception;

class AlertService {
    private $redis;

    public function __construct($redis) {
        $this->redis = $redis;
    }

    /**
     * Crea una alerta del sistema visible para los administradores.
     * @param array $data ['type' => 'info|warning|error', 'message' => '...', 'meta' => []]
     */
    public function createAlert($data) {
        if (!$this->redis) return;

        $alertId = uniqid('alert_');
        $alert = [
            'id' => $alertId,
            'type' => $data['type'] ?? 'info',
            'message' => $data['message'] ?? 'Notificación del sistema',
            'meta' => $data['meta'] ?? [],
            'created_at' => time(),
            'seen_by' => [] // Lista de IDs de admins que ya la cerraron
        ];

        // Guardamos en una lista de Redis (Mantiene historial de últimas 50 alertas)
        $this->redis->lpush('aurora:system_alerts', json_encode($alert));
        $this->redis->ltrim('aurora:system_alerts', 0, 49);

        // Publicar evento en WebSocket para notificación realtime
        $this->redis->publish('aurora_ws_channel', json_encode([
            'event' => 'new_system_alert',
            'data' => $alert
        ]));
    }

    public function getActiveAlerts() {
        if (!$this->redis) return [];

        $rawAlerts = $this->redis->lrange('aurora:system_alerts', 0, -1);
        $alerts = [];

        foreach ($rawAlerts as $raw) {
            $alert = json_decode($raw, true);
            if ($alert) {
                // [REFACTORIZADO] Uso de Utils::timeElapsedString
                // Asumimos que Utils tiene soporte para traducción básica o devuelve string en inglés/español configurable
                $alert['time_ago'] = Utils::timeElapsedString($alert['created_at']);
                $alerts[] = $alert;
            }
        }

        return $alerts;
    }

    public function dismissAlert($alertId, $userId) {
        if (!$this->redis) return ['success' => false];

        $rawAlerts = $this->redis->lrange('aurora:system_alerts', 0, -1);
        $updated = false;

        foreach ($rawAlerts as $index => $raw) {
            $alert = json_decode($raw, true);
            if ($alert['id'] === $alertId) {
                // Agregar usuario a la lista de "visto"
                if (!in_array($userId, $alert['seen_by'])) {
                    $alert['seen_by'][] = $userId;
                    $this->redis->lset('aurora:system_alerts', $index, json_encode($alert));
                    $updated = true;
                }
                break;
            }
        }

        return ['success' => $updated];
    }
    
    // Función auxiliar para desactivar alertas globales (ej. Modo Pánico)
    public function deactivateAlert($type = 'performance') {
        if (!$this->redis) return;
        
        $rawAlerts = $this->redis->lrange('aurora:system_alerts', 0, -1);
        foreach ($rawAlerts as $index => $raw) {
            $alert = json_decode($raw, true);
            if (($alert['type'] ?? '') === $type) {
                $this->redis->lrem('aurora:system_alerts', 1, $raw);
            }
        }
    }
}
?>