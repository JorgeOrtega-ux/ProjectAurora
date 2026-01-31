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
        $stats = $this->getAlertPageStats();
        $alertData = null;

        // Intentar leer de Redis primero (más rápido)
        $cached = $this->redis->get('system:active_alert');
        if ($cached) {
            $alertData = json_decode($cached, true);
        } else {
            // Fallback a DB si Redis está vacío pero DB dice activo (consistencia)
            $stmt = $this->pdo->prepare("SELECT * FROM system_alerts WHERE is_active = 1 LIMIT 1");
            $stmt->execute();
            $dbAlert = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($dbAlert) {
                $alertData = [
                    'id' => $dbAlert['uuid'],
                    'type' => $dbAlert['type'],
                    'severity' => $dbAlert['severity'],
                    'message' => $dbAlert['message']
                ];
            }
        }

        return [
            'success' => true, 
            'alert' => $alertData,
            'stats' => $stats
        ];
    }

    private function getAlertPageStats() {
        // 1. Usuarios Online (Alcance Potencial)
        $onlineUsers = 0;
        try {
            $realtimeStats = $this->redis->hgetall('aurora:stats:realtime');
            // Sumamos usuarios registrados + invitados
            $onlineUsers = (int)($realtimeStats['online_users'] ?? 0) + (int)($realtimeStats['online_guests'] ?? 0);
        } catch (Exception $e) { $onlineUsers = 0; }

        // 2. Alertas emitidas hoy
        $stmtToday = $this->pdo->prepare("SELECT COUNT(*) FROM system_alerts WHERE DATE(created_at) = CURDATE()");
        $stmtToday->execute();
        $alertsToday = $stmtToday->fetchColumn();

        // 3. Total histórico
        $stmtTotal = $this->pdo->query("SELECT COUNT(*) FROM system_alerts");
        $total = $stmtTotal->fetchColumn();

        // 4. Última alerta (para calcular tiempo)
        $stmtLast = $this->pdo->query("SELECT created_at FROM system_alerts ORDER BY created_at DESC LIMIT 1");
        $lastDate = $stmtLast->fetchColumn();
        $timeAgo = "N/A";

        if ($lastDate) {
            $timeAgo = $this->timeElapsedString($lastDate);
        }

        return [
            'online_users' => $onlineUsers,
            'alerts_today' => $alertsToday,
            'alerts_total' => $total,
            'last_alert_time' => $timeAgo
        ];
    }

    private function timeElapsedString($datetime, $full = false) {
        $now = new DateTime;
        $ago = new DateTime($datetime);
        $diff = $now->diff($ago);

        // Cálculo seguro de semanas sin modificar el objeto DateInterval
        $weeks = floor($diff->d / 7);
        $days = $diff->d - ($weeks * 7);

        // Mapeo de valores
        $timeMap = [
            'y' => ['val' => $diff->y, 'label' => 'año'],
            'm' => ['val' => $diff->m, 'label' => 'mes'],
            'w' => ['val' => $weeks,   'label' => 'sem.'],
            'd' => ['val' => $days,    'label' => 'día'],
            'h' => ['val' => $diff->h, 'label' => 'hora'],
            'i' => ['val' => $diff->i, 'label' => 'min'],
            's' => ['val' => $diff->s, 'label' => 'seg'],
        ];

        $string = [];
        foreach ($timeMap as $k => $v) {
            if ($v['val'] > 0) {
                $string[] = $v['val'] . ' ' . $v['label'] . ($v['val'] > 1 ? 's' : '');
            }
        }

        if (!$full) $string = array_slice($string, 0, 1);
        return $string ? 'Hace ' . implode(', ', $string) : 'Hace un momento';
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