<?php
// api/services/BackupService.php

require_once __DIR__ . '/../../includes/libs/Utils.php'; // Aseguramos que Utils esté disponible

class BackupService {
    private $pdo;
    private $i18n;
    private $userId;
    private $redis;
    private $backupDir;

    public function __construct($pdo, $i18n, $userId, $redis = null) {
        $this->pdo = $pdo;
        $this->i18n = $i18n;
        $this->userId = $userId;
        $this->redis = $redis;
        
        $this->backupDir = realpath(__DIR__ . '/../../storage/backups/');
        
        if (!is_dir($this->backupDir)) {
            mkdir($this->backupDir, 0755, true);
        }
        if (!file_exists($this->backupDir . '/.htaccess')) {
            file_put_contents($this->backupDir . '/.htaccess', "Order Deny,Allow\nDeny from all");
        }
    }

    public function getAllBackups() {
        $files = glob($this->backupDir . '/*.sql');
        $backups = [];

        usort($files, function($a, $b) {
            return filemtime($b) - filemtime($a);
        });

        foreach ($files as $file) {
            $filename = basename($file);
            $size = filesize($file);
            $date = filemtime($file);

            $source = 'unknown';
            try {
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
            } catch (Exception $e) {}

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
        if (!$isSystemAction && $this->checkRateLimit('backup_create', 5, 60)) {
            return ['success' => false, 'message' => 'Límite de copias de seguridad excedido.'];
        }

        if ($this->redis) {
            try {
                $jobData = [
                    'task' => 'create_backup',
                    'payload' => [
                        'requested_by' => $this->userId,
                        'is_system' => $isSystemAction,
                        'timestamp' => time()
                    ]
                ];

                $this->redis->rpush('aurora_task_queue', json_encode($jobData));

                return [
                    'success' => true, 
                    'message' => 'Solicitud de backup encolada. Recibirás una notificación al finalizar.',
                    'queued' => true
                ];

            } catch (Exception $e) {
                return ['success' => false, 'message' => 'Error al conectar con el gestor de tareas (Redis).'];
            }
        } else {
            return $this->createBackupLegacy($isSystemAction);
        }
    }

    private function createBackupLegacy($isSystemAction) {
        $host = getenv('DB_HOST') ?: 'localhost';
        $user = getenv('DB_USER') ?: 'root';
        $pass = getenv('DB_PASS') ?: '';
        $name = getenv('DB_NAME') ?: 'project_aurora_db';

        $filename = 'backup_' . date('Y-m-d_H-i-s') . '_' . substr(md5(time()), 0, 6) . '.sql';
        $filePath = $this->backupDir . '/' . $filename;
        $dumpPath = $this->getExecutablePath('mysqldump');

        $cmd = sprintf(
            '"%s" --host=%s --user=%s --password=%s %s > %s 2>&1',
            $dumpPath, escapeshellarg($host), escapeshellarg($user), escapeshellarg($pass), escapeshellarg($name), escapeshellarg($filePath)
        );

        $output = [];
        $returnVar = null;
        exec($cmd, $output, $returnVar);

        if ($returnVar === 0 && file_exists($filePath) && filesize($filePath) > 0) {
            $this->logAction('backup_create', "Created: $filename", $isSystemAction);
            if ($isSystemAction) $this->enforceRetentionPolicy();
            return ['success' => true, 'message' => 'Copia de seguridad creada (Modo síncrono).', 'filename' => $filename];
        } else {
            if (file_exists($filePath)) @unlink($filePath);
            return ['success' => false, 'message' => "Error al generar backup síncrono."];
        }
    }

    public function restoreBackup($filename) {
        if (!$this->isValidFilename($filename)) {
            return ['success' => false, 'message' => 'Nombre de archivo inválido.'];
        }

        $filePath = $this->backupDir . '/' . $filename;
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

    public function deleteBackup($filenames) {
        $filesToDelete = is_array($filenames) ? $filenames : explode(',', $filenames);
        $deletedCount = 0;
        foreach ($filesToDelete as $filename) {
            $filename = trim($filename);
            if (!$this->isValidFilename($filename)) continue;
            $filePath = $this->backupDir . '/' . $filename;
            if (file_exists($filePath) && unlink($filePath)) {
                $this->logAction('backup_delete', "Deleted: $filename");
                $deletedCount++;
            }
        }
        return ($deletedCount > 0) 
            ? ['success' => true, 'message' => "Se eliminaron $deletedCount archivos."]
            : ['success' => false, 'message' => 'No se pudieron eliminar los archivos.'];
    }

    public function getBackupContent($filenames) {
        $filesToRead = is_array($filenames) ? $filenames : explode(',', $filenames);
        $results = [];

        foreach ($filesToRead as $filename) {
            $filename = trim($filename);
            if (!$this->isValidFilename($filename)) {
                $results[] = ['filename' => $filename, 'error' => 'Nombre inválido', 'content' => ''];
                continue;
            }
            $fullPath = $this->backupDir . '/' . $filename;
            if (file_exists($fullPath)) {
                $size = filesize($fullPath);
                $content = '';
                $isTruncated = false;
                if ($size > 1048576) {
                    $handle = fopen($fullPath, 'r');
                    $head = fread($handle, 20000); 
                    fseek($handle, -20000, SEEK_END);
                    $tail = fread($handle, 20000); 
                    fclose($handle);
                    $content = $head . "\n\n... [CONTENIDO TRUNCADO POR TAMAÑO] ...\n\n" . $tail;
                    $isTruncated = true;
                } else {
                    $content = file_get_contents($fullPath);
                }
                $results[] = [
                    'filename' => $filename,
                    'path' => $filename, 
                    'content' => $content,
                    'size' => $this->formatSize($size),
                    'is_truncated' => $isTruncated
                ];
            } else {
                $results[] = ['filename' => $filename, 'error' => 'No encontrado', 'content' => ''];
            }
        }
        return ['success' => true, 'files' => $results];
    }

    public function getAutoConfig() {
        try {
            // Obtener configuración base
            $stmt = $this->pdo->prepare("SELECT config_key, config_value FROM server_config WHERE config_key LIKE 'auto_backup_%'");
            $stmt->execute();
            $config = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
            
            $enabled = ($config['auto_backup_enabled'] ?? '0') === '1';
            $frequency = (int)($config['auto_backup_frequency'] ?? 24);
            $retention = (int)($config['auto_backup_retention'] ?? 10);

            // Calcular datos en vivo (Próxima ejecución)
            $lastRun = null;
            $nextRunEstimate = null;
            $secondsRemaining = 0;

            // Buscar último log de backup
            $stmtLog = $this->pdo->prepare("SELECT created_at FROM security_logs WHERE action_type = 'backup_create' ORDER BY id DESC LIMIT 1");
            $stmtLog->execute();
            $lastRunStr = $stmtLog->fetchColumn();

            if ($lastRunStr) {
                $lastRun = $lastRunStr;
                if ($enabled) {
                    $lastTimestamp = strtotime($lastRunStr);
                    $nextTimestamp = $lastTimestamp + ($frequency * 3600);
                    $secondsRemaining = $nextTimestamp - time();
                    
                    if ($secondsRemaining < 0) $secondsRemaining = 0;
                    $nextRunEstimate = date('Y-m-d H:i:s', $nextTimestamp);
                }
            }

            return [
                'success' => true,
                'enabled' => $enabled,
                'frequency' => $frequency,
                'retention' => $retention,
                'meta' => [
                    'last_run' => $lastRun,
                    'next_run_estimate' => $nextRunEstimate,
                    'seconds_remaining' => $secondsRemaining
                ]
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
            return ['success' => true, 'message' => 'Configuración guardada.'];
        } catch (Exception $e) {
            $this->pdo->rollBack();
            return ['success' => false, 'message' => 'Error guardando configuración.'];
        }
    }

    private function enforceRetentionPolicy() {
        $limit = (int)Utils::getServerConfig($this->pdo, 'auto_backup_retention', '10');
        if ($limit <= 0) return;
        $files = glob($this->backupDir . '/*.sql');
        if (count($files) <= $limit) return;
        usort($files, function($a, $b) { return filemtime($b) - filemtime($a); });
        for ($i = $limit; $i < count($files); $i++) { @unlink($files[$i]); }
        $deletedCount = count($files) - $limit;
        $this->logAction('backup_auto_cleanup', "Cleaned $deletedCount old backups", true);
    }

    private function getExecutablePath($binary) {
        $configKey = 'sys_' . $binary . '_path';
        $configPath = Utils::getServerConfig($this->pdo, $configKey, '');
        if (!empty($configPath)) return $configPath;
        $commonPaths = [
            'C:/xampp/mysql/bin/' . $binary . '.exe',
            '/usr/bin/' . $binary,
            '/usr/local/bin/' . $binary
        ];
        foreach ($commonPaths as $path) { if (file_exists($path)) return $path; }
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
        // [MODIFICADO] Usar Utils::getClientIp
        $ip = Utils::getClientIp();
        $stmt = $this->pdo->prepare("INSERT INTO security_logs (user_identifier, action_type, ip_address) VALUES (?, ?, ?)");
        $identifier = $isSystem ? "System | $details" : "Admin:{$this->userId} | $details"; 
        $stmt->execute([$identifier, $actionType, $ip]);
    }

    private function checkRateLimit($actionType, $limit, $minutes) {
        // [MODIFICADO] Usar Utils::getClientIp
        $ip = Utils::getClientIp();
        $sql = "SELECT COUNT(*) FROM security_logs WHERE action_type = ? AND ip_address = ? AND created_at > (NOW() - INTERVAL $minutes MINUTE)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$actionType, $ip]);
        return ($stmt->fetchColumn() >= $limit);
    }
}
?>