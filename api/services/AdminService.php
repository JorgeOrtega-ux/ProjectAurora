<?php
// api/services/AdminService.php

namespace Aurora\Services;

use Aurora\Libs\Utils;
use Aurora\Libs\Logger;
use PDO;
use Exception;
use finfo; 

class AdminService {
    private $pdo;
    private $i18n;
    private $requestingUserId;
    private $redis; 

    // [REFACTOR] Eliminada la propiedad $requesterRoleCache

    public function __construct($pdo, $i18n, $userId, $redis = null) {
        $this->pdo = $pdo;
        $this->i18n = $i18n;
        $this->requestingUserId = $userId;
        $this->redis = $redis;
    }

    // ===============================================================================================
    // [NUEVO] LOGICA DEL MODO PÁNICO
    // ===============================================================================================

    public function togglePanicMode($activate, AlertService $alertService) {
        // [REFACTOR] Usando Utils::checkUserPrivileges
        $privCheck = Utils::checkUserPrivileges($this->pdo, $this->requestingUserId, ['founder', 'administrator'], true);
        if (!$privCheck['allowed']) {
            return ['success' => false, 'message' => $this->i18n->t('errors.access_denied')];
        }

        if (!$this->redis) {
            return ['success' => false, 'message' => 'Redis es requerido para ejecutar acciones de emergencia.'];
        }

        try {
            $this->pdo->beginTransaction();

            if ($activate) {
                // === ACTIVAR PÁNICO ===
                
                // 1. SQL: Bloquear registros y activar bandera
                $stmt = $this->pdo->prepare("
                    INSERT INTO server_config (config_key, config_value) 
                    VALUES ('allow_registrations', '0'), ('security_panic_mode', '1') 
                    ON DUPLICATE KEY UPDATE config_value = VALUES(config_value)
                ");
                $stmt->execute();

                // 2. Redis: Activar Firewall estricto (para app-setup.php)
                $this->redis->set('firewall:strict', 'true');

                // 3. AlertService: Crear alerta visual roja
                $alertData = [
                    'type' => 'performance',
                    'meta' => ['code' => 'overload'] 
                ];
                $alertService->createAlert($alertData);

                // 4. Redis PubSub: Ordenar a Python que mate los invitados
                $wsMessage = [
                    'cmd' => 'DROP_GUESTS'
                ];
                $this->redis->publish('aurora_ws_control', json_encode($wsMessage));

                $this->logAudit('system', 'global', 'PANIC_MODE_ACTIVATED', ['reason' => 'Admin manual trigger']);
                
                $message = 'MODO PÁNICO ACTIVADO. Tráfico de invitados cortado y registros bloqueados.';

            } else {
                // === DESACTIVAR PÁNICO ===

                // 1. SQL: Restaurar registros y quitar bandera
                $stmt = $this->pdo->prepare("
                    INSERT INTO server_config (config_key, config_value) 
                    VALUES ('allow_registrations', '1'), ('security_panic_mode', '0') 
                    ON DUPLICATE KEY UPDATE config_value = VALUES(config_value)
                ");
                $stmt->execute();

                // 2. Redis: Eliminar Firewall estricto
                $this->redis->del('firewall:strict');

                // 3. AlertService: Quitar alerta
                $alertService->deactivateAlert();

                $this->logAudit('system', 'global', 'PANIC_MODE_DEACTIVATED');

                $message = 'Modo Pánico desactivado. Sistema restaurado a la normalidad.';
            }

            // Invalidar caché de configuración global para que PHP lea los nuevos valores DB
            if ($this->redis) {
                $this->redis->del('server:config:all');
            }

            $this->pdo->commit();

            return ['success' => true, 'message' => $message];

        } catch (Exception $e) {
            $this->pdo->rollBack();
            Logger::security('Panic Mode Error', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => 'Error crítico al cambiar estado de emergencia.'];
        }
    }

    // ===============================================================================================
    // SISTEMA DE SEGURIDAD JERÁRQUICA (CORE)
    // ===============================================================================================

    /**
     * Verifica si el usuario solicitante tiene autoridad jerárquica sobre el objetivo.
     * [REFACTOR] Ahora delega la lógica a Utils::checkHierarchicalAccess
     */
    private function checkHierarchicalPermission($targetUserId) {
        $result = Utils::checkHierarchicalAccess($this->pdo, $this->requestingUserId, $targetUserId);
        
        if (!$result['allowed']) {
            // Personalizamos el mensaje si es un intento de admin vs admin
            // (Aunque Utils ya devuelve un mensaje genérico, podemos enriquecerlo si tenemos el rol destino)
            return ['allowed' => false, 'message' => $result['message'] ?? 'No tienes autoridad suficiente para modificar a este usuario.'];
        }
        
        return $result;
    }

    // [REFACTOR] Eliminado getRoleLevel() - Ahora en Utils
    // [REFACTOR] Eliminado getRequesterRole() y caché asociada

    private function shouldHideEmail($targetRole) {
        // Obtenemos rol directamente ya que no hay caché local
        $stmt = $this->pdo->prepare("SELECT role FROM users WHERE id = ?");
        $stmt->execute([$this->requestingUserId]);
        $requesterRole = $stmt->fetchColumn();
        
        // El Founder ve todo
        if ($requesterRole === 'founder') {
            return false;
        }

        // Si el objetivo es Admin o Founder, y el que pide NO es Founder (es Admin/Mod), se oculta.
        if ($targetRole === 'founder' || $targetRole === 'administrator') {
            return true;
        }

        return false;
    }

    // ===============================================================================================
    // FUNCIONALIDADES
    // ===============================================================================================

    // === GENERACIÓN DE TOKEN DE DESCARGA ===
    public function requestDownloadToken($inputFiles, $type) {
        $privCheck = Utils::checkUserPrivileges($this->pdo, $this->requestingUserId, ['founder', 'administrator'], true);
        if (!$privCheck['allowed']) return ['success' => false, 'message' => $this->i18n->t('errors.access_denied')];

        if (!$this->redis) {
            return ['success' => false, 'message' => 'Redis no disponible para generar tokens seguros.'];
        }

        $filesToProcess = is_array($inputFiles) ? $inputFiles : explode(',', $inputFiles);
        $filesToProcess = array_map('trim', $filesToProcess);
        $filesToProcess = array_filter($filesToProcess); 

        if (empty($filesToProcess)) {
            return ['success' => false, 'message' => 'No se seleccionaron archivos.'];
        }

        $baseDir = '';
        if ($type === 'backup') {
            $baseDir = realpath(__DIR__ . '/../../storage/backups');
        } elseif ($type === 'log') {
            $baseDir = realpath(__DIR__ . '/../../logs');
        } else {
            return ['success' => false, 'message' => 'Tipo de archivo inválido.'];
        }

        if (!$baseDir) {
             return ['success' => false, 'message' => 'El directorio base no existe en el servidor.'];
        }

        $validPaths = [];
        $relativePaths = []; 

        foreach ($filesToProcess as $relativePath) {
            $cleanPath = str_replace('..', '', $relativePath);
            $cleanPath = ltrim($cleanPath, '/\\');
            
            $fullPath = $baseDir . '/' . $cleanPath;
            $realPath = realpath($fullPath);
            
            if ($realPath && file_exists($realPath) && strpos($realPath, $baseDir) === 0) {
                $validPaths[] = [
                    'full' => $realPath,
                    'name' => $cleanPath 
                ];
                $relativePaths[] = $cleanPath;
            }
        }

        if (empty($validPaths)) {
            return ['success' => false, 'message' => 'Ninguno de los archivos solicitados es válido o existe.'];
        }

        // CASO 1: Archivo Único (Síncrono)
        if (count($validPaths) === 1) {
            $targetFile = $validPaths[0]['full'];
            $targetName = basename($validPaths[0]['name']); 
            
            $token = bin2hex(random_bytes(32));
            
            $data = [
                'filepath' => $targetFile,
                'filename' => $targetName,
                'is_temp'  => false,
                'user_id'  => $this->requestingUserId,
                'ip'       => Utils::getClientIp()
            ];

            try {
                $this->redis->setex("download:token:$token", 60, json_encode($data));
                $this->logAudit('download', $type, 'DOWNLOAD_REQUEST', ['file' => $targetName]);

                return [
                    'success' => true,
                    'download_url' => "download.php?token=$token"
                ];

            } catch (Exception $e) {
                Logger::app('Admin Download Token Error', ['error' => $e->getMessage()]);
                return ['success' => false, 'message' => 'Error interno al generar token de descarga.'];
            }
        } 
        
        // CASO 2: Múltiples Archivos (Asíncrono - Worker)
        else {
            try {
                $jobData = [
                    'task' => 'create_zip',
                    'payload' => [
                        'files' => $relativePaths,
                        'type' => $type,
                        'requested_by' => $this->requestingUserId
                    ]
                ];

                $this->redis->rpush('aurora_task_queue', json_encode($jobData));
                $this->logAudit('download', $type, 'ZIP_REQUEST_QUEUED', ['count' => count($validPaths)]);

                return [
                    'success' => true,
                    'queued' => true,
                    'message' => 'Tu descarga se está generando en segundo plano. Te avisaremos cuando esté lista.'
                ];

            } catch (Exception $e) {
                Logger::app('Admin Download Queue Error', ['error' => $e->getMessage()]);
                return ['success' => false, 'message' => 'Error al conectar con el gestor de tareas.'];
            }
        }
    }

    // === GESTIÓN DE AUDITORÍA ===

    private function logAudit($targetType, $targetId, $action, $changes = []) {
        try {
            $ip = Utils::getClientIp();
            $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
            $changesJson = !empty($changes) ? json_encode($changes) : null;

            $stmt = $this->pdo->prepare("INSERT INTO audit_logs (admin_id, target_type, target_id, action, changes, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$this->requestingUserId, $targetType, $targetId, $action, $changesJson, $ip, $ua]);
        } catch (Exception $e) {
            error_log("Error escribiendo Audit Log: " . $e->getMessage());
        }
    }

    public function getDashboardStats() {
        $privCheck = Utils::checkUserPrivileges($this->pdo, $this->requestingUserId, ['founder', 'administrator'], true);
        if (!$privCheck['allowed']) return ['success' => false, 'message' => $this->i18n->t('errors.access_denied')];

        try {
            $onlineUsers = 0;
            $onlineGuests = 0;
            
            if ($this->redis) {
                $realtimeStats = $this->redis->hgetall('aurora:stats:realtime');
                $onlineUsers = (int)($realtimeStats['online_users'] ?? 0);
                $onlineGuests = (int)($realtimeStats['online_guests'] ?? 0);
            }

            $today = date('Y-m-d');
            $yesterday = date('Y-m-d', strtotime('-1 day'));

            $stmtToday = $this->pdo->prepare("SELECT COUNT(*) FROM users WHERE DATE(created_at) = ?");
            $stmtToday->execute([$today]);
            $usersToday = $stmtToday->fetchColumn();

            $stmtYest = $this->pdo->prepare("SELECT COUNT(*) FROM users WHERE DATE(created_at) = ?");
            $stmtYest->execute([$yesterday]);
            $usersYesterday = $stmtYest->fetchColumn();

            $usersTrend = $this->calculateTrend($usersToday, $usersYesterday);

            $stmtTotal = $this->pdo->query("SELECT COUNT(*) FROM users");
            $totalUsers = $stmtTotal->fetchColumn();

            $date30DaysAgo = date('Y-m-d H:i:s', strtotime('-30 days'));
            $stmtTotalOld = $this->pdo->prepare("SELECT COUNT(*) FROM users WHERE created_at < ?");
            $stmtTotalOld->execute([$date30DaysAgo]);
            $totalUsersOld = $stmtTotalOld->fetchColumn();

            $totalTrend = $this->calculateTrend($totalUsers, $totalUsersOld);

            $stmtLogs = $this->pdo->prepare("SELECT COUNT(*) FROM security_logs WHERE DATE(created_at) = ?");
            $stmtLogs->execute([$today]);
            $logsToday = $stmtLogs->fetchColumn();

            return [
                'success' => true,
                'stats' => [
                    'online_total' => $onlineUsers + $onlineGuests,
                    'online_users' => $onlineUsers,
                    'online_guests' => $onlineGuests,
                    'new_users_today' => $usersToday,
                    'new_users_trend' => $usersTrend, 
                    'total_users' => $totalUsers,
                    'total_users_trend' => $totalTrend,
                    'system_activity' => $logsToday
                ]
            ];

        } catch (Exception $e) {
            Logger::app('Dashboard Stats Error', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => 'Error calculando estadísticas.'];
        }
    }

    private function calculateTrend($current, $previous) {
        if ($previous == 0) {
            return [
                'value' => $current > 0 ? 100 : 0,
                'direction' => $current > 0 ? 'up' : 'neutral',
                'infinite' => $current > 0
            ];
        }
        $diff = $current - $previous;
        $percent = ($diff / $previous) * 100;
        return [
            'value' => abs(round($percent, 1)),
            'direction' => $percent > 0 ? 'up' : ($percent < 0 ? 'down' : 'neutral')
        ];
    }

    public function getAuditLogs($page = 1, $limit = 50, $filters = []) {
        $privCheck = Utils::checkUserPrivileges($this->pdo, $this->requestingUserId, ['founder', 'administrator'], true);
        if (!$privCheck['allowed']) return ['success' => false, 'message' => $this->i18n->t('errors.access_denied')];

        $offset = ($page - 1) * $limit;
        $params = [];
        $whereClause = "WHERE 1=1";

        if (!empty($filters['target_type'])) {
            $whereClause .= " AND a.target_type = ?";
            $params[] = $filters['target_type'];
        }
        if (!empty($filters['target_id'])) {
            $whereClause .= " AND a.target_id = ?";
            $params[] = $filters['target_id'];
        }
        if (!empty($filters['admin_id'])) {
            $whereClause .= " AND a.admin_id = ?";
            $params[] = $filters['admin_id'];
        }

        try {
            $countStmt = $this->pdo->prepare("SELECT COUNT(*) FROM audit_logs a $whereClause");
            $countStmt->execute($params);
            $total = $countStmt->fetchColumn();

            $sql = "SELECT a.*, u.username as admin_name, u.role as admin_role 
                    FROM audit_logs a 
                    LEFT JOIN users u ON a.admin_id = u.id 
                    $whereClause 
                    ORDER BY a.created_at DESC 
                    LIMIT $limit OFFSET $offset";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $logs = $stmt->fetchAll();

            foreach ($logs as &$log) {
                if ($log['changes']) {
                    $log['changes'] = json_decode($log['changes'], true);
                }
                $log['admin_avatar_src'] = $this->resolveAvatarSrc($log['admin_id'], $log['admin_name']);
            }

            return [
                'success' => true, 
                'logs' => $logs, 
                'pagination' => [
                    'current' => $page,
                    'total_pages' => ceil($total / $limit),
                    'total_items' => $total
                ]
            ];

        } catch (Exception $e) {
            return ['success' => false, 'message' => $this->i18n->t('api.db_error')];
        }
    }

    public function getAllUsers($page = 1, $limit = 20, $search = '') {
        $privCheck = Utils::checkUserPrivileges($this->pdo, $this->requestingUserId, ['founder', 'administrator'], true);
        if (!$privCheck['allowed']) return ['success' => false, 'message' => $this->i18n->t('errors.access_denied')];

        $offset = ($page - 1) * $limit;
        $params = [];
        $whereClause = "WHERE 1=1";

        if (!empty($search)) {
            $whereClause .= " AND (username LIKE ? OR email LIKE ? OR uuid LIKE ?)";
            $searchTerm = "%$search%";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }

        try {
            $countStmt = $this->pdo->prepare("SELECT COUNT(*) FROM users $whereClause");
            $countStmt->execute($params);
            $totalItems = $countStmt->fetchColumn();

            $sql = "SELECT id, uuid, username, email, role, avatar_path, account_status, suspension_ends_at, created_at 
                    FROM users 
                    $whereClause
                    ORDER BY created_at DESC 
                    LIMIT ? OFFSET ?";
            
            $params[] = $limit;
            $params[] = $offset;

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $users = $stmt->fetchAll();

            $formattedUsers = [];
            foreach ($users as $user) {
                // [MODIFICADO] Protección de correo
                if ($this->shouldHideEmail($user['role'])) {
                    $user['email'] = $this->i18n->t('admin.email_protected') ?: 'Correo Protegido';
                }

                $user['avatar_src'] = $this->resolveAvatarSrcPath($user['avatar_path'], $user['username']);
                $formattedUsers[] = $user;
            }

            return [
                'success' => true, 
                'users' => $formattedUsers,
                'pagination' => [
                    'current' => (int)$page,
                    'total_pages' => ceil($totalItems / $limit),
                    'total_items' => (int)$totalItems,
                    'limit' => (int)$limit
                ]
            ];

        } catch (Exception $e) {
            return ['success' => false, 'message' => $this->i18n->t('api.db_error')];
        }
    }

    public function getUserDetails($targetId) {
        $privCheck = Utils::checkUserPrivileges($this->pdo, $this->requestingUserId, ['founder', 'administrator'], true);
        if (!$privCheck['allowed']) return ['success' => false, 'message' => $this->i18n->t('errors.access_denied')];

        try {
            $sql = "SELECT u.id, u.uuid, u.username, u.email, u.role, u.avatar_path, 
                           u.account_status, u.suspension_ends_at, u.status_reason, u.two_factor_enabled,
                           p.language, p.theme, p.open_links_new_tab, p.extended_toast
                    FROM users u
                    LEFT JOIN user_preferences p ON u.id = p.user_id
                    WHERE u.id = ?";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$targetId]);
            $user = $stmt->fetch();

            if (!$user) return ['success' => false, 'message' => $this->i18n->t('api.id_invalid')];

            // [MODIFICADO] Protección de correo
            if ($this->shouldHideEmail($user['role'])) {
                $user['email'] = $this->i18n->t('admin.email_protected') ?: 'Correo Protegido';
            }

            $response = [
                'id' => $user['id'],
                'uuid' => $user['uuid'],
                'username' => $user['username'],
                'email' => $user['email'],
                'role' => $user['role'],
                'account_status' => $user['account_status'],
                'suspension_ends_at' => $user['suspension_ends_at'],
                'status_reason' => $user['status_reason'],
                'two_factor_enabled' => (int)$user['two_factor_enabled'],
                'avatar_src' => $this->resolveAvatarSrcPath($user['avatar_path'], $user['username']),
                'is_custom_avatar' => (strpos($user['avatar_path'] ?? '', 'custom/') !== false),
                'preferences' => [
                    'language' => $user['language'] ?? 'es-latam',
                    'theme' => $user['theme'] ?? 'sync',
                    'open_links_new_tab' => (bool)($user['open_links_new_tab'] ?? 1),
                    'extended_toast' => (bool)($user['extended_toast'] ?? 0)
                ]
            ];

            return ['success' => true, 'user' => $response];

        } catch (Exception $e) {
            return ['success' => false, 'message' => $this->i18n->t('api.db_error')];
        }
    }

    public function updateUserProfile($targetId, $field, $value) {
        $privCheck = Utils::checkUserPrivileges($this->pdo, $this->requestingUserId, ['founder', 'administrator'], true);
        if (!$privCheck['allowed']) return ['success' => false, 'message' => $this->i18n->t('errors.access_denied')];
        
        // [FIX] Bloquear auto-edición insegura
        if ($targetId == $this->requestingUserId) {
            return ['success' => false, 'message' => 'Para editar tu propio perfil, ve a "Tu Perfil".'];
        }

        $permCheck = $this->checkHierarchicalPermission($targetId);
        if (!$permCheck['allowed']) return ['success' => false, 'message' => $permCheck['message']];

        if (!in_array($field, ['username', 'email'])) return ['success' => false, 'message' => $this->i18n->t('api.field_invalid')];
        
        if ($field === 'username') {
            if (strlen($value) < 4 || strlen($value) > 20) return ['success' => false, 'message' => $this->i18n->t('api.username_bounds', [4, 20])];
        }
        if ($field === 'email') {
             if (!filter_var($value, FILTER_VALIDATE_EMAIL)) return ['success' => false, 'message' => $this->i18n->t('api.email_invalid')];
        }

        $stmt = $this->pdo->prepare("SELECT id FROM users WHERE $field = ? AND id != ?");
        $stmt->execute([$value, $targetId]);
        if ($stmt->fetch()) return ['success' => false, 'message' => $this->i18n->t('api.field_in_use')];

        try {
            $stmtOld = $this->pdo->prepare("SELECT $field FROM users WHERE id = ?");
            $stmtOld->execute([$targetId]);
            $oldValue = $stmtOld->fetchColumn();

            if ($oldValue === $value) return ['success' => true, 'message' => $this->i18n->t('api.field_updated')];

            $update = $this->pdo->prepare("UPDATE users SET $field = ? WHERE id = ?");
            $update->execute([$value, $targetId]);

            $this->logAudit('user', $targetId, 'UPDATE_PROFILE', [
                'field' => $field,
                'old' => $oldValue,
                'new' => $value
            ]);

            return ['success' => true, 'message' => $this->i18n->t('api.field_updated')];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $this->i18n->t('api.update_error')];
        }
    }

    public function updateUserRole($targetId, $newRole) {
        $privCheck = Utils::checkUserPrivileges($this->pdo, $this->requestingUserId, ['founder', 'administrator'], true);
        if (!$privCheck['allowed']) return ['success' => false, 'message' => $this->i18n->t('errors.access_denied')];

        // [FIX] Bloquear auto-democión
        if ($targetId == $this->requestingUserId) {
            return ['success' => false, 'message' => 'No puedes cambiar tu propio rol. Contacta a otro administrador.'];
        }

        $permCheck = $this->checkHierarchicalPermission($targetId);
        if (!$permCheck['allowed']) return ['success' => false, 'message' => $permCheck['message']];

        $allowedRoles = ['user', 'moderator', 'administrator'];
        
        if (!in_array($newRole, $allowedRoles)) {
            return ['success' => false, 'message' => 'Rol inválido o acción no permitida.'];
        }

        // [REFACTOR] Obtener roles y niveles usando Utils
        $stmt = $this->pdo->prepare("SELECT role FROM users WHERE id = ?");
        $stmt->execute([$this->requestingUserId]);
        $myRole = $stmt->fetchColumn();

        $myLevel = Utils::getRoleLevel($myRole);
        $newRoleLevel = Utils::getRoleLevel($newRole);

        if ($myRole !== 'founder' && $newRoleLevel >= $myLevel) {
             return ['success' => false, 'message' => 'No tienes permisos para otorgar este nivel de autoridad.'];
        }

        try {
            $currentRole = $permCheck['role']; 
            
            if ($currentRole === $newRole) return ['success' => true, 'message' => 'Sin cambios'];

            $update = $this->pdo->prepare("UPDATE users SET role = ? WHERE id = ?");
            $update->execute([$newRole, $targetId]);

            $this->logAudit('user', $targetId, 'UPDATE_ROLE', [
                'old' => $currentRole,
                'new' => $newRole
            ]);

            return ['success' => true, 'message' => 'Rol de usuario actualizado correctamente.'];

        } catch (Exception $e) {
            return ['success' => false, 'message' => $this->i18n->t('api.update_error')];
        }
    }

    public function disableUser2FA($targetId) {
        $privCheck = Utils::checkUserPrivileges($this->pdo, $this->requestingUserId, ['founder', 'administrator'], true);
        if (!$privCheck['allowed']) return ['success' => false, 'message' => $this->i18n->t('errors.access_denied')];

        // [FIX] Bloquear desactivación propia de 2FA insegura
        if ($targetId == $this->requestingUserId) {
            return ['success' => false, 'message' => 'Por seguridad, usa la sección "Seguridad" en tu configuración para gestionar tu 2FA.'];
        }

        $permCheck = $this->checkHierarchicalPermission($targetId);
        if (!$permCheck['allowed']) return ['success' => false, 'message' => $permCheck['message']];

        try {
            $stmt = $this->pdo->prepare("SELECT two_factor_enabled FROM users WHERE id = ?");
            $stmt->execute([$targetId]);
            $enabled = $stmt->fetchColumn();

            if (!$enabled) return ['success' => false, 'message' => '2FA ya está desactivado.'];

            $update = $this->pdo->prepare("UPDATE users SET two_factor_enabled = 0, two_factor_secret = NULL, two_factor_recovery_codes = NULL WHERE id = ?");
            $update->execute([$targetId]);

            $this->logAudit('user', $targetId, 'DISABLE_2FA', [
                'action' => 'admin_override'
            ]);

            return ['success' => true, 'message' => 'Autenticación en dos pasos desactivada.'];

        } catch (Exception $e) {
            return ['success' => false, 'message' => $this->i18n->t('api.update_error')];
        }
    }

    public function updateUserPreference($targetId, $key, $value) {
        $privCheck = Utils::checkUserPrivileges($this->pdo, $this->requestingUserId, ['founder', 'administrator'], true);
        if (!$privCheck['allowed']) return ['success' => false, 'message' => $this->i18n->t('errors.access_denied')];

        $permCheck = $this->checkHierarchicalPermission($targetId);
        if (!$permCheck['allowed']) return ['success' => false, 'message' => $permCheck['message']];

        $allowedKeys = ['language', 'open_links_new_tab', 'theme', 'extended_toast'];
        if (!in_array($key, $allowedKeys)) return ['success' => false, 'message' => $this->i18n->t('api.pref_invalid')];

        $dbValue = $value;
        if ($key === 'open_links_new_tab' || $key === 'extended_toast') {
            $dbValue = ($value === 'true' || $value === '1' || $value === true) ? 1 : 0;
        }

        try {
            $stmtCheck = $this->pdo->prepare("SELECT $key FROM user_preferences WHERE user_id = ?");
            $stmtCheck->execute([$targetId]);
            $oldValue = $stmtCheck->fetchColumn(); 

            $sql = "INSERT INTO user_preferences (user_id, $key) VALUES (?, ?) ON DUPLICATE KEY UPDATE $key = VALUES($key)";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$targetId, $dbValue]);

            $this->logAudit('user', $targetId, 'UPDATE_PREFERENCE', [
                'key' => $key,
                'old' => ($oldValue === false) ? 'NULL' : (string)$oldValue,
                'new' => (string)$dbValue
            ]);

            return ['success' => true, 'message' => $this->i18n->t('api.pref_saved')];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $this->i18n->t('api.pref_save_error')];
        }
    }

   public function uploadUserAvatar($targetId, $files) {
        $privCheck = Utils::checkUserPrivileges($this->pdo, $this->requestingUserId, ['founder', 'administrator'], true);
        if (!$privCheck['allowed']) return ['success' => false, 'message' => $this->i18n->t('errors.access_denied')];
        
        $permCheck = $this->checkHierarchicalPermission($targetId);
        if (!$permCheck['allowed']) return ['success' => false, 'message' => $permCheck['message']];

        if (!isset($files['avatar']) || $files['avatar']['error'] !== UPLOAD_ERR_OK) { 
            return ['success' => false, 'message' => $this->i18n->t('api.no_image')]; 
        }
        
        $file = $files['avatar'];
        $stmt = $this->pdo->prepare("SELECT uuid, avatar_path FROM users WHERE id = ?");
        $stmt->execute([$targetId]);
        $targetUser = $stmt->fetch();
        if (!$targetUser) return ['success' => false, 'message' => $this->i18n->t('api.id_invalid')];

        $allowedTypes = ['image/jpeg' => 'jpg', 'image/png'  => 'png', 'image/webp' => 'webp'];
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']);
        
        if (!array_key_exists($mime, $allowedTypes)) return ['success' => false, 'message' => $this->i18n->t('api.image_format')];

        $extension = $allowedTypes[$mime];
        $newFileName = $targetUser['uuid'] . '-' . time() . '.' . $extension;
        
        // [MODIFICADO] Guardar en carpeta PÚBLICA (storage/custom)
        $baseDir = __DIR__ . '/../../public/storage/profilePicture/custom/';
        
        if (!is_dir($baseDir)) mkdir($baseDir, 0755, true);
        
        $targetPath = $baseDir . $newFileName;
        
        // [MODIFICADO] Ruta relativa para BD (storage/custom)
        $dbPath = 'public/storage/profilePicture/custom/' . $newFileName;

        $imageSaved = false;
        if (extension_loaded('gd')) {
            switch ($mime) {
                case 'image/jpeg': $img = @imagecreatefromjpeg($file['tmp_name']); if ($img) { $imageSaved = imagejpeg($img, $targetPath, 90); imagedestroy($img); } break;
                case 'image/png': $img = @imagecreatefrompng($file['tmp_name']); if ($img) { imagepalettetotruecolor($img); imagealphablending($img, true); imagesavealpha($img, true); $imageSaved = imagepng($img, $targetPath, 9); imagedestroy($img); } break;
                case 'image/webp': $img = @imagecreatefromwebp($file['tmp_name']); if ($img) { $imageSaved = imagewebp($img, $targetPath, 90); imagedestroy($img); } break;
            }
        }

        if ($imageSaved) {
            $oldPath = $targetUser['avatar_path'];
            $update = $this->pdo->prepare("UPDATE users SET avatar_path = ? WHERE id = ?");
            
            if ($update->execute([$dbPath, $targetId])) {
                if (!empty($oldPath) && file_exists(__DIR__ . '/../../' . $oldPath)) {
                    @unlink(__DIR__ . '/../../' . $oldPath);
                }
                
                $this->logAudit('user', $targetId, 'UPDATE_AVATAR', [
                    'old' => $oldPath,
                    'new' => $dbPath
                ]);

                $newContent = file_get_contents($targetPath);
                $base64 = 'data:' . $mime . ';base64,' . base64_encode($newContent);

                return ['success' => true, 'message' => $this->i18n->t('api.pic_updated'), 'new_src' => $base64];
            }
        }
        return ['success' => false, 'message' => $this->i18n->t('api.pic_move_error')];
    }
   public function deleteUserAvatar($targetId) {
        $privCheck = Utils::checkUserPrivileges($this->pdo, $this->requestingUserId, ['founder', 'administrator'], true);
        if (!$privCheck['allowed']) return ['success' => false, 'message' => $this->i18n->t('errors.access_denied')];

        $permCheck = $this->checkHierarchicalPermission($targetId);
        if (!$permCheck['allowed']) return ['success' => false, 'message' => $permCheck['message']];

        $stmt = $this->pdo->prepare("SELECT avatar_path, username, uuid FROM users WHERE id = ?");
        $stmt->execute([$targetId]);
        $targetUser = $stmt->fetch();
        
        $oldPath = $targetUser['avatar_path'];
        $username = $targetUser['username'];
        $uuid = $targetUser['uuid'];

        $newFileName = $uuid . '-' . time() . '.png';
        
        // [MODIFICADO] Ruta pública para avatares por defecto (storage/default)
        $dbPath = 'public/storage/profilePicture/default/' . $newFileName;
        $absolutePath = __DIR__ . '/../../' . $dbPath;

        if (Utils::generateDefaultProfilePicture($username, $absolutePath)) {
            
            $update = $this->pdo->prepare("UPDATE users SET avatar_path = ? WHERE id = ?");
            $update->execute([$dbPath, $targetId]);
            
            if (!empty($oldPath) && file_exists(__DIR__ . '/../../' . $oldPath)) {
                @unlink(__DIR__ . '/../../' . $oldPath);
            }
            
            $this->logAudit('user', $targetId, 'DELETE_AVATAR', [
                'old' => $oldPath,
                'new' => 'default_generated'
            ]);

            $imageContent = file_get_contents($absolutePath);
            $base64Image = 'data:image/png;base64,' . base64_encode($imageContent);
            
            return ['success' => true, 'message' => $this->i18n->t('api.pic_deleted'), 'new_src' => $base64Image];
        }
        
        return ['success' => false, 'message' => $this->i18n->t('api.pic_gen_error')];
    }

    public function updateUserStatus($targetId, $statusData) {
        $privCheck = Utils::checkUserPrivileges($this->pdo, $this->requestingUserId, ['founder', 'administrator'], true);
        if (!$privCheck['allowed']) return ['success' => false, 'message' => $this->i18n->t('errors.access_denied')];

        $permCheck = $this->checkHierarchicalPermission($targetId);
        if (!$permCheck['allowed']) return ['success' => false, 'message' => $permCheck['message']];

        $newStatus = $statusData['status'] ?? 'active';
        $reason = $statusData['reason'] ?? null;
        $suspensionType = $statusData['suspension_type'] ?? null; 
        $durationDays = $statusData['duration_days'] ?? 0;
        $deletionSource = $statusData['deletion_source'] ?? null;

        // [FIX] Bloquear auto-sanción
        if ($targetId == $this->requestingUserId && ($newStatus === 'suspended' || $newStatus === 'deleted')) {
            return ['success' => false, 'message' => 'No puedes suspender o eliminar tu propia cuenta.'];
        }

        if (!in_array($newStatus, ['active', 'suspended', 'deleted'])) {
            return ['success' => false, 'message' => 'Estado inválido'];
        }

        $checkStmt = $this->pdo->prepare("SELECT role, account_status, suspension_ends_at, status_reason FROM users WHERE id = ?");
        $checkStmt->execute([$targetId]);
        $userData = $checkStmt->fetch();
        
        try {
            $suspensionEndsAt = null;
            $finalReason = $reason;

            if ($newStatus === 'active') {
                $finalReason = null;
                $suspensionEndsAt = null;
            } 
            elseif ($newStatus === 'suspended') {
                if ($suspensionType === 'temp') {
                    if ($durationDays <= 0) return ['success' => false, 'message' => 'Días de suspensión inválidos'];
                    $suspensionEndsAt = date('Y-m-d H:i:s', strtotime("+$durationDays days"));
                    $finalReason = "[Suspensión Temporal ($durationDays días)]: " . $reason;
                } else {
                    $finalReason = "[Suspensión Permanente]: " . $reason;
                }
            } 
            elseif ($newStatus === 'deleted') {
                $prefix = ($deletionSource === 'user') ? 'Solicitud Usuario' : 'Decisión Administrativa';
                $finalReason = "[$prefix]: " . $reason;
            }

            $sql = "UPDATE users SET 
                    account_status = ?, 
                    suspension_ends_at = ?, 
                    status_reason = ? 
                    WHERE id = ?";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$newStatus, $suspensionEndsAt, $finalReason, $targetId]);

            if ($userData['account_status'] !== $newStatus || $userData['status_reason'] !== $finalReason) {
                $this->logAudit('user', $targetId, 'UPDATE_STATUS', [
                    'old_status' => $userData['account_status'],
                    'new_status' => $newStatus,
                    'old_reason' => $userData['status_reason'],
                    'new_reason' => $finalReason
                ]);

                if ($newStatus === 'suspended' || $newStatus === 'deleted') {
                    Utils::notifyWebSocket('KICK_ALL', [
                        'user_id' => $targetId,
                        'reason'  => $finalReason 
                    ]);
                }
            }

            return ['success' => true, 'message' => 'Estado de cuenta actualizado correctamente.'];

        } catch (Exception $e) {
            return ['success' => false, 'message' => $this->i18n->t('api.update_error')];
        }
    }

    public function getServerConfigAll() {
        $privCheck = Utils::checkUserPrivileges($this->pdo, $this->requestingUserId, ['founder', 'administrator'], true);
        if (!$privCheck['allowed']) return ['success' => false, 'message' => $this->i18n->t('errors.access_denied')];
        try {
            $stmt = $this->pdo->prepare("SELECT config_key, config_value FROM server_config");
            $stmt->execute();
            $results = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
            return ['success' => true, 'config' => $results];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $this->i18n->t('api.db_error')];
        }
    }

    public function updateServerConfig($data) {
        $privCheck = Utils::checkUserPrivileges($this->pdo, $this->requestingUserId, ['founder', 'administrator'], true);
        if (!$privCheck['allowed']) return ['success' => false, 'message' => $this->i18n->t('errors.access_denied')];
        
       $allowedKeys = [
            'maintenance_mode', 'allow_registrations', 'allow_login',
            'password_min_length', 'username_min_length', 'username_max_length',
            'email_min_prefix_length', 'email_allowed_domains',
            'upload_avatar_max_size', 'upload_avatar_max_dim',
            'security_login_max_attempts', 'security_block_duration', 'security_general_rate_limit',
            'auth_verification_code_expiry', 'auth_reset_token_expiry',
            'sys_mysqldump_path', 'sys_mysql_path',
            
            // [NUEVO] Agregamos la clave aquí
            'security_admin_require_2fa'
        ];

        try {
            $currentConfig = $this->getServerConfigAll()['config'] ?? [];

            $this->pdo->beginTransaction();
            $stmt = $this->pdo->prepare("INSERT INTO server_config (config_key, config_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE config_value = VALUES(config_value)");

            $maintenanceActivated = false;
            $hasChanges = false;

            foreach ($data as $key => $value) {
                if (in_array($key, $allowedKeys)) {
                    if ($value === true || $value === 'true') $value = '1';
                    if ($value === false || $value === 'false') $value = '0';
                    
                    $strValue = (string)$value;
                    $oldValue = $currentConfig[$key] ?? null;

                    if ($oldValue !== $strValue) {
                        $stmt->execute([$key, $strValue]);
                        
                        $this->logAudit('server_config', $key, 'UPDATE_CONFIG', [
                            'old' => $oldValue,
                            'new' => $strValue
                        ]);

                        if ($key === 'maintenance_mode' && $strValue === '1') {
                            $maintenanceActivated = true;
                        }
                        $hasChanges = true;
                    }
                }
            }
            $this->pdo->commit();

            if ($hasChanges && $this->redis) {
                try {
                    $this->redis->del('server:config:all');
                } catch (Exception $e) {
                    error_log("Error invalidando caché de config: " . $e->getMessage());
                }
            }

            if ($maintenanceActivated) {
                Utils::notifyWebSocket('maintenance_start', ['text' => 'El sistema ha entrado en mantenimiento.']);
                $this->logAudit('system', 'global', 'MAINTENANCE_STARTED', []);
            }

            return ['success' => true, 'message' => 'Configuración del servidor guardada exitosamente.'];
        } catch (Exception $e) {
            $this->pdo->rollBack();
            return ['success' => false, 'message' => 'Error al guardar configuración.'];
        }
    }

    // [REFACTOR] Eliminado isAdmin() - Ahora en Utils::checkUserPrivileges

    private function resolveAvatarSrc($userId, $username) {
        $stmt = $this->pdo->prepare("SELECT avatar_path FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $path = $stmt->fetchColumn();
        return $this->resolveAvatarSrcPath($path, $username);
    }

    private function resolveAvatarSrcPath($path, $username) {
        if (!empty($path) && file_exists(__DIR__ . '/../../' . $path)) {
            $mime = mime_content_type(__DIR__ . '/../../' . $path);
            $content = file_get_contents(__DIR__ . '/../../' . $path);
            return 'data:' . $mime . ';base64,' . base64_encode($content);
        } else {
            $name = urlencode($username);
            return "https://ui-avatars.com/api/?name={$name}&background=random&color=fff&size=128&length=1";
        }
    }
}
?>