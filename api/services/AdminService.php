<?php
// api/services/AdminService.php

require_once __DIR__ . '/../../includes/libs/Utils.php'; // Aseguramos que Utils esté disponible

class AdminService {
    private $pdo;
    private $i18n;
    private $requestingUserId;
    private $redis; 

    public function __construct($pdo, $i18n, $userId, $redis = null) {
        $this->pdo = $pdo;
        $this->i18n = $i18n;
        $this->requestingUserId = $userId;
        $this->redis = $redis;
    }

    // === GENERACIÓN DE TOKEN DE DESCARGA (Soporte ZIP y Subcarpetas) ===
    public function requestDownloadToken($inputFiles, $type) {
        if (!$this->isAdmin()) {
            return ['success' => false, 'message' => $this->i18n->t('errors.access_denied')];
        }

        if (!$this->redis) {
            return ['success' => false, 'message' => 'Redis no disponible para generar tokens seguros.'];
        }

        // 1. Convertir entrada a array siempre
        $filesToProcess = is_array($inputFiles) ? $inputFiles : explode(',', $inputFiles);
        $filesToProcess = array_map('trim', $filesToProcess);
        $filesToProcess = array_filter($filesToProcess); 

        if (empty($filesToProcess)) {
            return ['success' => false, 'message' => 'No se seleccionaron archivos.'];
        }

        // 2. Definir directorios base (Sandbox)
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

        // 3. Validar TODOS los archivos antes de procesar
        $validPaths = [];
        foreach ($filesToProcess as $relativePath) {
            // Permitimos '/' y '\' para subcarpetas (logs/app/...), pero eliminamos '..'
            $cleanPath = str_replace('..', '', $relativePath);
            
            // Limpiamos barras al inicio para evitar rutas absolutas confusas
            $cleanPath = ltrim($cleanPath, '/\\');
            
            $fullPath = $baseDir . '/' . $cleanPath;
            $realPath = realpath($fullPath);
            
            // Verificamos que el archivo exista y que su ruta real siga estando dentro del baseDir (Sandbox)
            if ($realPath && file_exists($realPath) && strpos($realPath, $baseDir) === 0) {
                $validPaths[] = [
                    'full' => $realPath,
                    'name' => $cleanPath // Usamos la ruta relativa limpia como nombre (mantiene estructura carpetas en ZIP)
                ];
            }
        }

        if (empty($validPaths)) {
            return ['success' => false, 'message' => 'Ninguno de los archivos solicitados es válido o existe.'];
        }

        // 4. Lógica: 1 Archivo vs Múltiples (ZIP)
        $targetFile = '';
        $targetName = '';
        $isTempFile = false; // Flag para borrar después de descargar

        if (count($validPaths) === 1) {
            // CASO INDIVIDUAL
            $targetFile = $validPaths[0]['full'];
            $targetName = basename($validPaths[0]['name']); // Al descargar 1 solo, mejor nombre corto
        } else {
            // CASO MÚLTIPLE -> CREAR ZIP
            if (!extension_loaded('zip')) {
                return ['success' => false, 'message' => 'La extensión ZIP de PHP no está habilitada en el servidor.'];
            }

            $zipName = 'aurora_batch_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.zip';
            $tempDir = __DIR__ . '/../../storage/temp';
            if (!is_dir($tempDir)) mkdir($tempDir, 0755, true);
            
            $zipPath = $tempDir . '/' . $zipName;
            
            $zip = new ZipArchive();
            if ($zip->open($zipPath, ZipArchive::CREATE) !== TRUE) {
                return ['success' => false, 'message' => 'No se pudo crear el archivo temporal ZIP.'];
            }

            foreach ($validPaths as $fileInfo) {
                // Agregamos al ZIP manteniendo su estructura relativa (ej: app/log.txt)
                $zip->addFile($fileInfo['full'], $fileInfo['name']);
            }
            $zip->close();

            $targetFile = $zipPath;
            $targetName = $zipName;
            $isTempFile = true; // Importante: Marcar para borrado automático
        }

        // 5. Generar Token y Guardar en Redis
        $token = bin2hex(random_bytes(32));
        
        $data = [
            'filepath' => $targetFile,
            'filename' => $targetName,
            'is_temp'  => $isTempFile,
            'user_id'  => $this->requestingUserId,
            'ip'       => $_SERVER['REMOTE_ADDR'] ?? 'Unknown'
        ];

        try {
            // "download:token:XYZ" expira en 60s
            $this->redis->setex("download:token:$token", 60, json_encode($data));
            
            // Log de auditoría
            $actionDetails = count($validPaths) > 1 
                ? ['zip_mode' => true, 'count' => count($validPaths)] 
                : ['file' => $targetName];
                
            $this->logAudit('download', $type, 'DOWNLOAD_REQUEST', $actionDetails);

            return [
                'success' => true,
                'download_url' => "download.php?token=$token"
            ];

        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Error Redis: ' . $e->getMessage()];
        }
    }

    // === GESTIÓN DE AUDITORÍA ===

    private function logAudit($targetType, $targetId, $action, $changes = []) {
        try {
            $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
            $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
            $changesJson = !empty($changes) ? json_encode($changes) : null;

            $stmt = $this->pdo->prepare("INSERT INTO audit_logs (admin_id, target_type, target_id, action, changes, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$this->requestingUserId, $targetType, $targetId, $action, $changesJson, $ip, $ua]);
        } catch (Exception $e) {
            error_log("Error escribiendo Audit Log: " . $e->getMessage());
        }
    }

    public function getDashboardStats() {
        if (!$this->isAdmin()) return ['success' => false, 'message' => $this->i18n->t('errors.access_denied')];

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
            return ['success' => false, 'message' => 'Error calculando estadísticas: ' . $e->getMessage()];
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
        if (!$this->isAdmin()) return ['success' => false, 'message' => $this->i18n->t('errors.access_denied')];

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
        if (!$this->isAdmin()) {
            return ['success' => false, 'message' => $this->i18n->t('errors.access_denied')];
        }

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
                $user['avatar_src'] = $this->resolveAvatarSrc($user['avatar_path'], $user['username']);
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
        if (!$this->isAdmin()) return ['success' => false, 'message' => $this->i18n->t('errors.access_denied')];

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
                'avatar_src' => $this->resolveAvatarSrc($user['avatar_path'], $user['username']),
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
        if (!$this->isAdmin()) return ['success' => false, 'message' => $this->i18n->t('errors.access_denied')];
        
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
        if (!$this->isAdmin()) return ['success' => false, 'message' => $this->i18n->t('errors.access_denied')];

        $allowedRoles = ['user', 'moderator', 'administrator'];
        if (!in_array($newRole, $allowedRoles)) {
            return ['success' => false, 'message' => 'Rol inválido o acción no permitida.'];
        }

        try {
            $checkStmt = $this->pdo->prepare("SELECT role FROM users WHERE id = ?");
            $checkStmt->execute([$targetId]);
            $currentRole = $checkStmt->fetchColumn();

            if ($currentRole === 'founder') {
                return ['success' => false, 'message' => 'No se puede modificar el rol de un usuario Founder.'];
            }
            
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
        if (!$this->isAdmin()) return ['success' => false, 'message' => $this->i18n->t('errors.access_denied')];

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
        if (!$this->isAdmin()) return ['success' => false, 'message' => $this->i18n->t('errors.access_denied')];

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
        if (!$this->isAdmin()) return ['success' => false, 'message' => $this->i18n->t('errors.access_denied')];
        
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
        $baseDir = __DIR__ . '/../../storage/profilePicture/custom/';
        if (!is_dir($baseDir)) mkdir($baseDir, 0755, true);
        
        $targetPath = $baseDir . $newFileName;
        $dbPath = 'storage/profilePicture/custom/' . $newFileName;

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
        if (!$this->isAdmin()) return ['success' => false, 'message' => $this->i18n->t('errors.access_denied')];

        $stmt = $this->pdo->prepare("SELECT avatar_path, username, uuid FROM users WHERE id = ?");
        $stmt->execute([$targetId]);
        $targetUser = $stmt->fetch();
        
        $oldPath = $targetUser['avatar_path'];
        $username = $targetUser['username'];
        $uuid = $targetUser['uuid'];

        // [MODIFICADO] Usar Utils::generateDefaultProfilePicture
        $newFileName = $uuid . '-' . time() . '.png';
        $dbPath = 'storage/profilePicture/default/' . $newFileName;
        $absolutePath = __DIR__ . '/../../' . $dbPath;

        // Llamada centralizada que usa la paleta de colores y el length=1
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

            // Devolver imagen generada
            $imageContent = file_get_contents($absolutePath);
            $base64Image = 'data:image/png;base64,' . base64_encode($imageContent);
            
            return ['success' => true, 'message' => $this->i18n->t('api.pic_deleted'), 'new_src' => $base64Image];
        }
        
        return ['success' => false, 'message' => $this->i18n->t('api.pic_gen_error')];
    }

    public function updateUserStatus($targetId, $statusData) {
        if (!$this->isAdmin()) return ['success' => false, 'message' => $this->i18n->t('errors.access_denied')];

        $newStatus = $statusData['status'] ?? 'active';
        $reason = $statusData['reason'] ?? null;
        $suspensionType = $statusData['suspension_type'] ?? null; 
        $durationDays = $statusData['duration_days'] ?? 0;
        $deletionSource = $statusData['deletion_source'] ?? null;

        if (!in_array($newStatus, ['active', 'suspended', 'deleted'])) {
            return ['success' => false, 'message' => 'Estado inválido'];
        }

        $checkStmt = $this->pdo->prepare("SELECT role, account_status, suspension_ends_at, status_reason FROM users WHERE id = ?");
        $checkStmt->execute([$targetId]);
        $userData = $checkStmt->fetch();
        
        if ($userData['role'] === 'founder') {
            return ['success' => false, 'message' => 'No se puede alterar el estado de un Founder.'];
        }

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
            }

            return ['success' => true, 'message' => 'Estado de cuenta actualizado correctamente.'];

        } catch (Exception $e) {
            return ['success' => false, 'message' => $this->i18n->t('api.update_error')];
        }
    }

    public function getServerConfigAll() {
        if (!$this->isAdmin()) return ['success' => false, 'message' => $this->i18n->t('errors.access_denied')];
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
        if (!$this->isAdmin()) return ['success' => false, 'message' => $this->i18n->t('errors.access_denied')];
        
        $allowedKeys = [
            'maintenance_mode', 'allow_registrations', 'allow_login',
            'password_min_length', 'username_min_length', 'username_max_length',
            'email_min_prefix_length', 'email_allowed_domains',
            'upload_avatar_max_size', 'upload_avatar_max_dim',
            'security_login_max_attempts', 'security_block_duration', 'security_general_rate_limit',
            'auth_verification_code_expiry', 'auth_reset_token_expiry',
            'sys_mysqldump_path', 'sys_mysql_path' 
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
                // [MODIFICADO] Envío correcto de señal de mantenimiento
                $this->notifyWebSocketServer('maintenance_start', ['text' => 'El sistema ha entrado en mantenimiento.']);
                $this->logAudit('system', 'global', 'MAINTENANCE_STARTED', []);
            }

            return ['success' => true, 'message' => 'Configuración del servidor guardada exitosamente.'];
        } catch (Exception $e) {
            $this->pdo->rollBack();
            return ['success' => false, 'message' => 'Error al guardar configuración.'];
        }
    }

    private function isAdmin() {
        $stmt = $this->pdo->prepare("SELECT role FROM users WHERE id = ?");
        $stmt->execute([$this->requestingUserId]);
        $role = $stmt->fetchColumn();
        return in_array($role, ['founder', 'administrator']);
    }

    private function resolveAvatarSrc($path, $username) {
        if (!empty($path) && file_exists(__DIR__ . '/../../' . $path)) {
            $mime = mime_content_type(__DIR__ . '/../../' . $path);
            $content = file_get_contents(__DIR__ . '/../../' . $path);
            return 'data:' . $mime . ';base64,' . base64_encode($content);
        } else {
            $name = urlencode($username);
            // [MODIFICADO] Agregar length=1 para consistencia
            return "https://ui-avatars.com/api/?name={$name}&background=random&color=fff&size=128&length=1";
        }
    }

   private function notifyWebSocketServer($messageType, $payload = []) {
        if ($this->redis) {
            try {
                // Estructura compatible con server.py
                $msg = [
                    'cmd' => 'BROADCAST',
                    'msg_type' => $messageType,
                    'message' => $payload
                ];
                $this->redis->publish('aurora_ws_control', json_encode($msg));
            } catch (Exception $e) {
                error_log("Error publicando en Redis (WS Notify): " . $e->getMessage());
            }
        }
    }
}
?>