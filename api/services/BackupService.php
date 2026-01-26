<?php
// api/services/BackupService.php

class BackupService {
    private $pdo;
    private $i18n;
    private $userId;
    private $backupDir;

    public function __construct($pdo, $i18n, $userId) {
        $this->pdo = $pdo;
        $this->i18n = $i18n;
        $this->userId = $userId;
        
        $this->backupDir = __DIR__ . '/../../storage/backups/';
        
        if (!is_dir($this->backupDir)) {
            mkdir($this->backupDir, 0755, true);
        }
        if (!file_exists($this->backupDir . '.htaccess')) {
            file_put_contents($this->backupDir . '.htaccess', "Order Deny,Allow\nDeny from all");
        }
    }

    public function getAllBackups() {
        $files = glob($this->backupDir . '*.sql');
        $backups = [];

        usort($files, function($a, $b) {
            return filemtime($b) - filemtime($a);
        });

        foreach ($files as $file) {
            $filename = basename($file);
            $size = filesize($file);
            $date = filemtime($file);

            // === Detección de Origen (Sistema vs Manual) ===
            $source = 'unknown';
            try {
                // Buscamos en los logs quién creó este archivo específico
                $stmt = $this->pdo->prepare("SELECT user_identifier FROM security_logs WHERE action_type = 'backup_create' AND user_identifier LIKE ? LIMIT 1");
                $stmt->execute(["%Created: $filename%"]);
                $logIdentifier = $stmt->fetchColumn();

                if ($logIdentifier) {
                    if (stripos($logIdentifier, 'System') === 0) {
                        $source = 'system';
                    } elseif (strpos($logIdentifier, 'Admin') === 0) {
                        $source = 'manual';
                    }
                }
            } catch (Exception $e) {
                // Si falla la consulta de logs, no rompemos la lista
            }

            $backups[] = [
                'filename' => $filename,
                'size' => $this->formatSize($size),
                'date' => date('Y-m-d H:i:s', $date),
                'timestamp' => $date,
                'source' => $source
            ];
        }

        return ['success' => true, 'backups' => $backups];
    }

    public function createBackup($isSystemAction = false) {
        // Si es acción del sistema (Python), saltamos el rate limit de usuario
        if (!$isSystemAction && $this->checkRateLimit('backup_create', 5, 60)) {
            return ['success' => false, 'message' => 'Límite de copias de seguridad excedido.'];
        }

        $host = getenv('DB_HOST') ?: 'localhost';
        $user = getenv('DB_USER') ?: 'root';
        $pass = getenv('DB_PASS') ?: '';
        $name = getenv('DB_NAME') ?: 'project_aurora_db';

        $filename = 'backup_' . date('Y-m-d_H-i-s') . '_' . substr(md5(time()), 0, 6) . '.sql';
        $filePath = $this->backupDir . $filename;

        $dumpPath = $this->getExecutablePath('mysqldump');

        $cmd = sprintf(
            '"%s" --host=%s --user=%s --password=%s %s > %s 2>&1',
            $dumpPath,
            escapeshellarg($host),
            escapeshellarg($user),
            escapeshellarg($pass),
            escapeshellarg($name),
            escapeshellarg($filePath)
        );

        $output = [];
        $returnVar = null;
        exec($cmd, $output, $returnVar);

        if ($returnVar === 0 && file_exists($filePath) && filesize($filePath) > 0) {
            $this->logAction('backup_create', "Created: $filename", $isSystemAction);
            
            // Lógica de Retención (Limpieza automática)
            if ($isSystemAction) {
                $this->enforceRetentionPolicy();
            }

            return ['success' => true, 'message' => 'Copia de seguridad creada exitosamente.', 'filename' => $filename];
        } else {
            if (file_exists($filePath)) @unlink($filePath);
            error_log("Backup Error (Cmd: $dumpPath): " . implode("\n", $output));
            return ['success' => false, 'message' => "Error al generar backup. Verifica la ruta de mysqldump en el servidor."];
        }
    }

    public function restoreBackup($filename) {
        if (!$this->isValidFilename($filename)) {
            return ['success' => false, 'message' => 'Nombre de archivo inválido.'];
        }

        $filePath = $this->backupDir . $filename;
        if (!file_exists($filePath)) {
            return ['success' => false, 'message' => 'Archivo no encontrado.'];
        }

        $host = getenv('DB_HOST') ?: 'localhost';
        $user = getenv('DB_USER') ?: 'root';
        $pass = getenv('DB_PASS') ?: '';
        $name = getenv('DB_NAME') ?: 'project_aurora_db';

        $mysqlPath = $this->getExecutablePath('mysql');

        $cmd = sprintf(
            '"%s" --host=%s --user=%s --password=%s %s < %s 2>&1',
            $mysqlPath,
            escapeshellarg($host),
            escapeshellarg($user),
            escapeshellarg($pass),
            escapeshellarg($name),
            escapeshellarg($filePath)
        );

        $output = [];
        $returnVar = null;
        exec($cmd, $output, $returnVar);

        if ($returnVar === 0) {
            $this->logAction('backup_restore', "Restored: $filename");
            return ['success' => true, 'message' => 'Base de datos restaurada correctamente.'];
        } else {
            error_log("Restore Error: " . implode("\n", $output));
            return ['success' => false, 'message' => 'Error al restaurar. Revisa los logs.'];
        }
    }

    public function deleteBackup($filename) {
        if (!$this->isValidFilename($filename)) return ['success' => false, 'message' => 'Nombre inválido.'];
        
        $filePath = $this->backupDir . $filename;
        if (file_exists($filePath)) {
            if (unlink($filePath)) {
                $this->logAction('backup_delete', "Deleted: $filename");
                return ['success' => true, 'message' => 'Archivo eliminado.'];
            }
            return ['success' => false, 'message' => 'Error de permisos al eliminar.'];
        }
        return ['success' => false, 'message' => 'Archivo no encontrado.'];
    }

    // === GESTIÓN DE CONFIGURACIÓN AUTOMÁTICA ===

    public function getAutoConfig() {
        try {
            $stmt = $this->pdo->prepare("SELECT config_key, config_value FROM server_config WHERE config_key LIKE 'auto_backup_%'");
            $stmt->execute();
            $config = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
            
            return [
                'success' => true,
                'enabled' => ($config['auto_backup_enabled'] ?? '0') === '1',
                'frequency' => (int)($config['auto_backup_frequency'] ?? 24),
                'retention' => (int)($config['auto_backup_retention'] ?? 10)
            ];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Error leyendo configuración.'];
        }
    }

    public function updateAutoConfig($enabled, $frequency, $retention) {
        try {
            $this->pdo->beginTransaction();
            $stmt = $this->pdo->prepare("INSERT INTO server_config (config_key, config_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE config_value = VALUES(config_value)");
            
            $stmt->execute(['auto_backup_enabled', $enabled ? '1' : '0']);
            $stmt->execute(['auto_backup_frequency', (int)$frequency]);
            $stmt->execute(['auto_backup_retention', (int)$retention]);
            
            $this->pdo->commit();
            return ['success' => true, 'message' => 'Configuración automática guardada.'];
        } catch (Exception $e) {
            $this->pdo->rollBack();
            return ['success' => false, 'message' => 'Error guardando configuración.'];
        }
    }

    // --- Helpers ---

    private function enforceRetentionPolicy() {
        $limit = (int)Utils::getServerConfig($this->pdo, 'auto_backup_retention', '10');
        if ($limit <= 0) return;

        $files = glob($this->backupDir . '*.sql');
        if (count($files) <= $limit) return;

        usort($files, function($a, $b) {
            return filemtime($b) - filemtime($a);
        });

        for ($i = $limit; $i < count($files); $i++) {
            @unlink($files[$i]);
        }
        $deletedCount = count($files) - $limit;
        $this->logAction('backup_auto_cleanup', "Cleaned $deletedCount old backups", true);
    }

    private function getExecutablePath($binary) {
        $configKey = 'sys_' . $binary . '_path';
        $configPath = Utils::getServerConfig($this->pdo, $configKey, '');
        if (!empty($configPath)) return $configPath;

        $commonPaths = [
            'C:/xampp/mysql/bin/' . $binary . '.exe',
            'C:/laragon/bin/mysql/mysql-8.0.30-winx64/bin/' . $binary . '.exe', 
            '/usr/bin/' . $binary,
            '/usr/local/bin/' . $binary,
            '/opt/lampp/bin/' . $binary
        ];

        foreach ($commonPaths as $path) {
            if (file_exists($path)) return $path;
        }
        return $binary;
    }

    private function isValidFilename($filename) {
        return preg_match('/^[a-zA-Z0-9_\-\.]+$/', $filename) && strpos($filename, '..') === false && substr($filename, -4) === '.sql';
    }

    private function formatSize($bytes) {
        if ($bytes >= 1073741824) return number_format($bytes / 1073741824, 2) . ' GB';
        if ($bytes >= 1048576) return number_format($bytes / 1048576, 2) . ' MB';
        if ($bytes >= 1024) return number_format($bytes / 1024, 2) . ' KB';
        return $bytes . ' bytes';
    }

    private function logAction($actionType, $details = '', $isSystem = false) {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $stmt = $this->pdo->prepare("INSERT INTO security_logs (user_identifier, action_type, ip_address) VALUES (?, ?, ?)");
        
        $identifier = $isSystem ? "System | $details" : "Admin:{$this->userId} | $details"; 
        
        $stmt->execute([$identifier, $actionType, $ip]);
    }

    private function checkRateLimit($actionType, $limit, $minutes) {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $sql = "SELECT COUNT(*) FROM security_logs WHERE action_type = ? AND ip_address = ? AND created_at > (NOW() - INTERVAL $minutes MINUTE)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$actionType, $ip]);
        return ($stmt->fetchColumn() >= $limit);
    }
}
?>