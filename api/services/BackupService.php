<?php
// api/services/BackupService.php

namespace Aurora\Services;

use Aurora\Libs\Utils;
use Aurora\Libs\Logger;
use Exception;
use PDO;

class BackupService {
    private $pdo;
    private $redis;
    private $i18n;
    private $userId;
    private $backupDir;

    public function __construct($pdo, $redis, $i18n, $userId) {
        $this->pdo = $pdo;
        $this->redis = $redis;
        $this->i18n = $i18n;
        $this->userId = $userId;
        $this->backupDir = __DIR__ . '/../../storage/backups';
        
        if (!is_dir($this->backupDir)) {
            mkdir($this->backupDir, 0755, true);
        }
    }

    public function getBackups() {
        $files = glob($this->backupDir . '/*.zip');
        $backups = [];

        foreach ($files as $file) {
            $backups[] = [
                'name' => basename($file),
                // [REFACTORIZADO] Uso de Utils::formatSize
                'size' => Utils::formatSize(filesize($file)), 
                'created_at' => date('Y-m-d H:i:s', filemtime($file)),
                'timestamp' => filemtime($file)
            ];
        }

        usort($backups, function($a, $b) {
            return $b['timestamp'] - $a['timestamp'];
        });

        return ['success' => true, 'backups' => $backups];
    }

    public function createBackup() {
        // [REFACTORIZADO] Uso de Utils para Rate Limiting (1 backup cada 5 min por usuario)
        if (Utils::checkSecurityLimit($this->pdo, 'backup_create', 1, 5, $this->userId)) {
             return ['success' => false, 'message' => $this->i18n->t('api.rate_limit_exceeded')];
        }

        if (!$this->redis) {
            return ['success' => false, 'message' => 'Redis no disponible para procesar backups.'];
        }

        try {
            // Delegar la tarea pesada al Worker de Python mediante Redis
            $jobData = [
                'task' => 'create_backup',
                'payload' => [
                    'requested_by' => $this->userId,
                    'timestamp' => time()
                ]
            ];

            $this->redis->rpush('aurora_task_queue', json_encode($jobData));

            // [REFACTORIZADO] Log de seguridad centralizado
            Utils::logSecurityAction($this->pdo, 'backup_create', 5, $this->userId);
            
            // Log de auditoría administrativa
            $this->logAudit('BACKUP_REQUEST', ['method' => 'async_worker']);

            return [
                'success' => true, 
                'queued' => true,
                'message' => 'La solicitud de respaldo se ha enviado al procesador. Recibirás una notificación al finalizar.'
            ];

        } catch (Exception $e) {
            Logger::app('Backup Error', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => $this->i18n->t('api.internal_error')];
        }
    }

    public function restoreBackup($filename) {
        // Validación de seguridad de rutas
        $targetFile = realpath($this->backupDir . '/' . basename($filename));
        
        if (!$targetFile || !file_exists($targetFile) || strpos($targetFile, realpath($this->backupDir)) !== 0) {
             return ['success' => false, 'message' => 'Archivo de respaldo inválido o inexistente.'];
        }

        // [REFACTORIZADO] Rate Limit para restauraciones (1 cada 10 min)
        if (Utils::checkSecurityLimit($this->pdo, 'backup_restore', 1, 10, $this->userId)) {
            return ['success' => false, 'message' => 'Espera antes de solicitar otra restauración.'];
        }

        try {
            // Enviar tarea de restauración al worker
            $jobData = [
                'task' => 'restore_backup',
                'payload' => [
                    'filename' => basename($filename),
                    'requested_by' => $this->userId
                ]
            ];

            $this->redis->rpush('aurora_task_queue', json_encode($jobData));
            
            Utils::logSecurityAction($this->pdo, 'backup_restore', 10, $this->userId);
            $this->logAudit('RESTORE_REQUEST', ['file' => basename($filename)]);

            return [
                'success' => true, 
                'queued' => true,
                'message' => 'El sistema iniciará la restauración en breve. Se activará el modo mantenimiento.'
            ];

        } catch (Exception $e) {
            return ['success' => false, 'message' => $this->i18n->t('api.internal_error')];
        }
    }

    public function deleteBackup($filename) {
        $targetFile = realpath($this->backupDir . '/' . basename($filename));
        
        if (!$targetFile || !file_exists($targetFile) || strpos($targetFile, realpath($this->backupDir)) !== 0) {
             return ['success' => false, 'message' => 'Archivo no encontrado.'];
        }

        if (@unlink($targetFile)) {
            $this->logAudit('BACKUP_DELETE', ['file' => basename($filename)]);
            return ['success' => true, 'message' => 'Respaldo eliminado correctamente.'];
        }

        return ['success' => false, 'message' => 'Error al eliminar el archivo.'];
    }

    private function logAudit($action, $details = []) {
        // Helper simple para auditoría interna del servicio (usa la tabla audit_logs)
        try {
            $stmt = $this->pdo->prepare("INSERT INTO audit_logs (admin_id, target_type, target_id, action, changes, ip_address) VALUES (?, 'system', 'backup', ?, ?, ?)");
            $stmt->execute([
                $this->userId, 
                $action, 
                json_encode($details), 
                Utils::getClientIp()
            ]);
        } catch (Exception $e) { /* Silencio en logs auxiliares */ }
    }
}
?>