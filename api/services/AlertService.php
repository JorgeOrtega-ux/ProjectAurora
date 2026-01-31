<?php
// api/services/AlertService.php

class AlertService {
    private $pdo;
    private $redis;
    private $userId;

    public function __construct($pdo, $redis, $userId) {
        $this->pdo = $pdo;
        $this->redis = $redis;
        $this->userId = $userId;
    }

    public function createAlert($data) {
        try {
            // 1. Desactivar cualquier alerta activa anterior
            $this->deactivateCurrentAlerts();

            // 2. Preparar datos
            $uuid = Utils::generateUUID();
            $type = $data['type'];
            $severity = $this->determineSeverity($type, $data);
            $message = $data['message'] ?? '';
            $metaData = json_encode($data['meta'] ?? []);

            // 3. Insertar en MySQL
            $stmt = $this->pdo->prepare("INSERT INTO system_alerts (uuid, type, severity, message, meta_data, created_by, is_active) VALUES (?, ?, ?, ?, ?, ?, 1)");
            $stmt->execute([$uuid, $type, $severity, $message, $metaData, $this->userId]);

            // 4. Estructurar Payload para el Cliente
            $alertPayload = [
                'id' => $uuid,
                'type' => $type,
                'severity' => $severity,
                'message' => $message,
                'meta' => $data['meta'] ?? []
            ];

            // 5. Guardar en Redis (Persistencia para nuevos usuarios)
            // Clave: system:active_alert
            $this->redis->set('system:active_alert', json_encode($alertPayload));

            // 6. Publicar evento para WebSocket (Usuarios conectados ahora)
            $wsMessage = [
                'cmd' => 'BROADCAST',
                'msg_type' => 'system_alert',
                'message' => $alertPayload
            ];
            $this->redis->publish('aurora_ws_control', json_encode($wsMessage));

            return ['success' => true, 'message' => 'Alerta emitida correctamente'];

        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Error DB: ' . $e->getMessage()];
        }
    }

    public function deactivateAlert() {
        $this->deactivateCurrentAlerts();
        
        // Limpiar Redis
        $this->redis->del('system:active_alert');
        
        // Avisar a usuarios conectados que cierren la alerta
        $wsMessage = [
            'cmd' => 'BROADCAST',
            'msg_type' => 'system_alert_clear',
            'message' => null
        ];
        $this->redis->publish('aurora_ws_control', json_encode($wsMessage));

        return ['success' => true, 'message' => 'Alerta desactivada'];
    }

    public function getActiveAlert() {
        // Intentar leer de Redis primero (más rápido)
        $cached = $this->redis->get('system:active_alert');
        if ($cached) {
            return ['success' => true, 'alert' => json_decode($cached, true)];
        }
        return ['success' => true, 'alert' => null];
    }

    private function deactivateCurrentAlerts() {
        $stmt = $this->pdo->prepare("UPDATE system_alerts SET is_active = 0 WHERE is_active = 1");
        $stmt->execute();
    }

    private function determineSeverity($type, $data) {
        if ($type === 'maintenance') {
            return ($data['meta']['subtype'] ?? '') === 'emergency' ? 'critical' : 'warning';
        }
        if ($type === 'performance') return 'warning';
        return 'info';
    }
}
?>