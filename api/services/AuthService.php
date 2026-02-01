<?php
// api/services/AuthService.php

use Google\Authenticator\GoogleAuthenticator;

require_once __DIR__ . '/../../includes/libs/MailService.php';
require_once __DIR__ . '/../../includes/libs/Utils.php'; // Aseguramos que Utils esté incluido
require_once __DIR__ . '/../../includes/libs/EmailTemplates.php'; // Importamos las plantillas

class AuthService {
    private $pdo;
    private $i18n;
    private $redis; // Propiedad Redis
    private $turnstileSecret;

    // Constructor con inyección de Redis
    public function __construct($pdo, $i18n, $redis) {
        $this->pdo = $pdo;
        $this->i18n = $i18n;
        $this->redis = $redis;
        
        $secret = $_ENV['TURNSTILE_SECRET_KEY'] ?? null;
        if (empty($secret)) {
            $secret = getenv('TURNSTILE_SECRET_KEY');
        }
        $this->turnstileSecret = !empty($secret) ? $secret : '1x0000000000000000000000000000000AA';
    }

    private function checkRegistrationStatus() {
        $allowed = Utils::getServerConfig($this->pdo, 'allow_registrations', '1');
        if ($allowed === '0') {
            return ['success' => false, 'message' => 'El registro de nuevos usuarios está deshabilitado temporalmente.'];
        }
        return ['success' => true];
    }

    // [NUEVO] Obtener estado del cooldown de registro
    public function getRegistrationStatus($email) {
        if (empty($email)) return ['success' => false];

        // Misma clave de cooldown usada en initiateVerification
        $cooldownKey = "verify:cooldown:activation:{$email}";
        
        // Consultar tiempo restante en Redis
        $ttl = 0;
        if ($this->redis) {
            $ttl = $this->redis->ttl($cooldownKey);
        }

        // Si ttl es -2 (no existe) o -1 (infinito), lo tratamos como 0
        if ($ttl < 0) $ttl = 0;

        return [
            'success' => true,
            'cooldown' => $ttl
        ];
    }

    public function registerStep1($email, $password, $turnstileToken) {
        $configCheck = $this->checkRegistrationStatus();
        if (!$configCheck['success']) return $configCheck;

        $turnstileCheck = $this->verifyTurnstile($turnstileToken);
        if (!$turnstileCheck['success']) {
            return ['success' => false, 'message' => 'Error de seguridad (Captcha): ' . $turnstileCheck['message']];
        }

        $limit = (int)Utils::getServerConfig($this->pdo, 'security_register_max_attempts', '10');
        $duration = (int)Utils::getServerConfig($this->pdo, 'security_block_duration', '15');

        // Verificación optimizada con Redis
        if ($this->checkSecurityBlock('register_attempt', $limit, $duration)) {
            return ['success' => false, 'message' => $this->i18n->t('api.pref_rate_limit')];
        }
        
        $this->logSecurityEvent($email, 'register_attempt', $duration);

        if (empty($email) || empty($password)) {
            return ['success' => false, 'message' => $this->i18n->t('api.fill_all')];
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => $this->i18n->t('api.email_invalid')];
        }

        $minPassLen = (int)Utils::getServerConfig($this->pdo, 'password_min_length', '6');
        if (strlen($password) < $minPassLen) {
            return ['success' => false, 'message' => $this->i18n->t('api.pass_short', [$minPassLen])];
        }

        $minPrefixLen = (int)Utils::getServerConfig($this->pdo, 'email_min_prefix_length', '3');
        $parts = explode('@', $email);
        $prefix = $parts[0] ?? '';
        
        if (strlen($prefix) < $minPrefixLen) {
            return ['success' => false, 'message' => $this->i18n->t('api.email_prefix_short', [$minPrefixLen])];
        }

        $allowedDomainsStr = Utils::getServerConfig($this->pdo, 'email_allowed_domains', '*');
        if ($allowedDomainsStr !== '*' && trim($allowedDomainsStr) !== '') {
            $allowedDomains = array_map('trim', explode(',', strtolower($allowedDomainsStr)));
            $domain = strtolower($parts[1] ?? '');
            
            if (!in_array($domain, $allowedDomains)) {
                return ['success' => false, 'message' => $this->i18n->t('api.email_domain_not_allowed', [$domain])];
            }
        }

        $stmt = $this->pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            return ['success' => false, 'message' => $this->i18n->t('api.email_taken')];
        }

        $_SESSION['temp_register'] = [
            'email' => $email,
            'password' => $password
        ];

        return ['success' => true, 'message' => $this->i18n->t('api.step_1_ok'), 'next_url' => 'register/aditional-data'];
    }

    public function initiateVerification($username) {
        $configCheck = $this->checkRegistrationStatus();
        if (!$configCheck['success']) return $configCheck;

        if (!isset($_SESSION['temp_register']['email'])) {
            return ['success' => false, 'message' => $this->i18n->t('api.register_expired')];
        }
        
        $email = $_SESSION['temp_register']['email'];
        
        // Bloqueo general de seguridad
        if ($this->checkSecurityBlock('register_code_req', 3, 15, $email)) {
            return ['success' => false, 'message' => $this->i18n->t('api.wait_resend')];
        }

        $password = $_SESSION['temp_register']['password'];

        if (empty($username)) {
            return ['success' => false, 'message' => $this->i18n->t('api.choose_user')];
        }

        $minUserLen = (int)Utils::getServerConfig($this->pdo, 'username_min_length', '4');
        $maxUserLen = (int)Utils::getServerConfig($this->pdo, 'username_max_length', '20');
        
        if (strlen($username) < $minUserLen || strlen($username) > $maxUserLen) {
            return ['success' => false, 'message' => $this->i18n->t('api.username_bounds', [$minUserLen, $maxUserLen])];
        }

        $stmt = $this->pdo->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$username]);
        if ($stmt->fetch()) {
            return ['success' => false, 'message' => $this->i18n->t('api.user_taken')];
        }

        // [MODIFICADO] Verificación de Cooldown (60s) usando Redis
        // Clave: verify:cooldown:activation:{email}
        $cooldownKey = "verify:cooldown:activation:{$email}";
        if ($this->redis->exists($cooldownKey)) {
            return ['success' => false, 'message' => $this->i18n->t('api.wait_resend')];
        }

        $_SESSION['temp_register']['username'] = $username;
        
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        
        // Datos a guardar en Redis
        $code = str_pad(mt_rand(0, 999999), 6, '0', STR_PAD_LEFT);
        $payload = json_encode([
            'username' => $username,
            'email' => $email,
            'password_hash' => $passwordHash,
            'code' => $code // Guardamos el código dentro del JSON para validarlo luego
        ]);

        $expiryMinutes = (int)Utils::getServerConfig($this->pdo, 'auth_verification_code_expiry', '15');
        
        // Clave principal: verify:activation:{email}
        $mainKey = "verify:activation:{$email}";

        try {
            // 1. Guardar datos principales (Expiran en X minutos)
            $this->redis->setex($mainKey, $expiryMinutes * 60, $payload);
            
            // 2. Establecer Cooldown (Expira en 60 segundos)
            $this->redis->setex($cooldownKey, 60, '1');
            
            // Log de seguridad
            $this->logSecurityEvent($email, 'register_code_req', 15);

            $_SESSION['pending_verification_email'] = $email;

            $subject = "Verifica tu cuenta - Project Aurora";
            
            // [MODIFICADO] Uso de EmailTemplates
            $body = EmailTemplates::verificationCode($username, $code, $expiryMinutes);

            $emailResult = MailService::send($email, $subject, $body);

            if (!$emailResult['success']) {
                return ['success' => false, 'message' => 'Error al enviar el correo: ' . $emailResult['message']];
            }
            
            return [
                'success' => true, 
                'message' => $this->i18n->t('api.code_sent'), 
                'next_url' => 'register/verification-account'
            ]; 
        } catch (Exception $e) {
            return ['success' => false, 'message' => $this->i18n->t('api.internal_error') . ': ' . $e->getMessage()];
        }
    }

    public function resendCode() {
        $email = $_SESSION['pending_verification_email'] ?? '';

        if (empty($email)) {
            return ['success' => false, 'message' => $this->i18n->t('api.no_verification')];
        }
        
        $configCheck = $this->checkRegistrationStatus();
        if (!$configCheck['success']) return $configCheck;
        
        // Check de seguridad general (intentos excesivos)
        if ($this->checkSecurityBlock('resend_code_req', 3, 10, $email)) {
             return ['success' => false, 'message' => $this->i18n->t('api.wait_resend')];
        }

        // [MODIFICADO] Check de Cooldown en Redis
        $cooldownKey = "verify:cooldown:activation:{$email}";
        if ($this->redis->exists($cooldownKey)) {
            return ['success' => false, 'message' => $this->i18n->t('api.wait_resend')];
        }

        // [MODIFICADO] Obtener datos anteriores desde Redis
        $mainKey = "verify:activation:{$email}";
        $existingDataJson = $this->redis->get($mainKey);

        if (!$existingDataJson) {
            return ['success' => false, 'message' => $this->i18n->t('api.no_data')];
        }

        $data = json_decode($existingDataJson, true);
        
        // Generar nuevo código
        $newCode = str_pad(mt_rand(0, 999999), 6, '0', STR_PAD_LEFT);
        $data['code'] = $newCode; // Actualizar código en el payload
        
        $expiryMinutes = (int)Utils::getServerConfig($this->pdo, 'auth_verification_code_expiry', '15');

        try {
            // Actualizar Redis (Resetea el TTL a 15 min)
            $this->redis->setex($mainKey, $expiryMinutes * 60, json_encode($data));
            
            // Poner nuevo cooldown
            $this->redis->setex($cooldownKey, 60, '1');
            
            $this->logSecurityEvent($email, 'resend_code_req', 10);

            // [MODIFICADO] Recuperar nombre de usuario y usar template
            $username = $data['username'] ?? 'Usuario';
            $subject = "Nuevo código de verificación - Project Aurora";
            $body = EmailTemplates::verificationCode($username, $newCode, $expiryMinutes);
            
            MailService::send($email, $subject, $body);
            
            return ['success' => true, 'message' => $this->i18n->t('api.code_generated')];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $this->i18n->t('api.code_save_error') . ': ' . $e->getMessage()];
        }
    }

    public function completeRegister($code) {
        $configCheck = $this->checkRegistrationStatus();
        if (!$configCheck['success']) return $configCheck;

        $email = $_SESSION['pending_verification_email'] ?? '';

        if (empty($code) || empty($email)) {
            return ['success' => false, 'message' => $this->i18n->t('api.missing_data')];
        }
        
        $limit = (int)Utils::getServerConfig($this->pdo, 'security_login_max_attempts', '5'); 
        $duration = (int)Utils::getServerConfig($this->pdo, 'security_block_duration', '15');

        if ($this->checkSecurityBlock('register_verify_fail', $limit, $duration, $email)) {
            return ['success' => false, 'message' => $this->i18n->t('api.login_block', [$duration])];
        }

        // [MODIFICADO] Validar código contra Redis
        $mainKey = "verify:activation:{$email}";
        $dataJson = $this->redis->get($mainKey);

        if (!$dataJson) {
            $this->logSecurityEvent($email, 'register_verify_fail', $duration);
            return ['success' => false, 'message' => $this->i18n->t('api.code_invalid_expired')];
        }

        $data = json_decode($dataJson, true);
        $storedCode = $data['code'] ?? '';

        // Comparación estricta
        if ($storedCode !== $code) {
            $this->logSecurityEvent($email, 'register_verify_fail', $duration);
            return ['success' => false, 'message' => $this->i18n->t('api.code_invalid')];
        }

        // Extraer datos para crear usuario
        $username = $data['username'];
        $passwordHash = $data['password_hash']; 
        $uuid = $this->generateUuid();

        // -----------------------------------------------------
        // [MODIFICADO] Integración de Utils::generateDefaultProfilePicture
        // -----------------------------------------------------
        $fileName = $uuid . '.png';
        // Ruta relativa para la BD
        $dbPath = 'storage/profilePicture/default/' . $fileName;
        // Ruta absoluta para guardar el archivo
        $absolutePath = __DIR__ . '/../../' . $dbPath;

        // Intentamos generar el avatar con los colores permitidos definidos en Utils
        $avatarGenerated = Utils::generateDefaultProfilePicture($username, $absolutePath);
        
        // Si se generó correctamente, guardamos la ruta, si no, null
        $dbAvatarPath = $avatarGenerated ? $dbPath : null;
        // -----------------------------------------------------

        $this->pdo->beginTransaction();
        try {
            $insertUser = $this->pdo->prepare("INSERT INTO users (uuid, username, email, password, role, avatar_path) VALUES (?, ?, ?, ?, 'user', ?)");
            $insertUser->execute([$uuid, $username, $email, $passwordHash, $dbAvatarPath]);
            $newUserId = $this->pdo->lastInsertId();

            $detectedLang = $this->getBestMatchLanguage();
            $prefStmt = $this->pdo->prepare("INSERT INTO user_preferences (user_id, language, open_links_new_tab, theme, extended_toast) VALUES (?, ?, 1, 'sync', 0)");
            $prefStmt->execute([$newUserId, $detectedLang]);

            // [MODIFICADO] Eliminar datos de Redis tras éxito
            $this->redis->del($mainKey);
            $this->redis->del("verify:cooldown:activation:{$email}");

            $this->pdo->commit();

            $_SESSION['user_id'] = $newUserId;
            $_SESSION['username'] = $username;
            $_SESSION['role'] = 'user';
            $_SESSION['avatar'] = $dbAvatarPath;
            $_SESSION['email'] = $email;
            
            $_SESSION['preferences'] = [
                'language' => $detectedLang,
                'open_links_new_tab' => true,
                'theme' => 'sync',
                'extended_toast' => false
            ];
            
            $this->createPersistenceToken($newUserId);
            
            unset($_SESSION['temp_register']);
            unset($_SESSION['pending_verification_email']);

            return ['success' => true, 'message' => $this->i18n->t('api.register_success'), 'redirect' => '/ProjectAurora/'];

        } catch (Exception $e) {
            $this->pdo->rollBack();
            return ['success' => false, 'message' => $this->i18n->t('api.db_error') . ': ' . $e->getMessage()];
        }
    }

    public function login($email, $password, $turnstileToken) {
        $turnstileCheck = $this->verifyTurnstile($turnstileToken);
        if (!$turnstileCheck['success']) {
             return ['success' => false, 'message' => 'Error de seguridad (Captcha): ' . $turnstileCheck['message']];
        }
        
        $limit = (int)Utils::getServerConfig($this->pdo, 'security_login_max_attempts', '5');
        $duration = (int)Utils::getServerConfig($this->pdo, 'security_block_duration', '15');

        if ($this->checkSecurityBlock('login_fail', $limit, $duration, $email)) {
            return ['success' => false, 'message' => $this->i18n->t('api.login_block', [$duration])];
        }

        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            
            if ($user['account_status'] === 'deleted' || $user['account_status'] === 'suspended') {
                $isPermanent = empty($user['suspension_ends_at']);
                $isActiveSuspension = $isPermanent || (strtotime($user['suspension_ends_at']) > time());

                if ($user['account_status'] === 'deleted' || $isActiveSuspension) {
                    $_SESSION['account_status_data'] = [
                        'status' => $user['account_status'],
                        'reason' => $user['status_reason'],
                        'suspension_ends_at' => $user['suspension_ends_at']
                    ];
                    return [
                        'success' => true, 
                        'message' => 'Redirigiendo a estado de cuenta...',
                        'redirect' => '/ProjectAurora/account-status'
                    ];
                }
            }
            
            $allowLogin = Utils::getServerConfig($this->pdo, 'allow_login', '1');
            $userRole = $user['role'] ?? 'user';
            $staffRoles = ['founder', 'administrator', 'moderator'];

            if ($allowLogin === '0' && !in_array($userRole, $staffRoles)) {
                return ['success' => false, 'message' => 'El inicio de sesión está deshabilitado temporalmente.'];
            }

            if (isset($user['two_factor_enabled']) && $user['two_factor_enabled'] == 1) {
                $_SESSION['2fa_pending_user_id'] = $user['id'];
                return ['success' => true, 'message' => $this->i18n->t('api.credentials_ok_2fa'), 'require_2fa' => true];
            }

            return $this->completeLogin($user);
        } else {
            $this->logSecurityEvent($email, 'login_fail', $duration);
            return ['success' => false, 'message' => $this->i18n->t('api.credentials_invalid')];
        }
    }

    public function verify2faLogin($code) {
        if (!isset($_SESSION['2fa_pending_user_id'])) {
            return ['success' => false, 'message' => $this->i18n->t('api.session_expired')];
        }

        $userId = $_SESSION['2fa_pending_user_id'];
        
        $limit = (int)Utils::getServerConfig($this->pdo, 'security_login_max_attempts', '5');
        $duration = (int)Utils::getServerConfig($this->pdo, 'security_block_duration', '15');
        
        if ($this->checkSecurityBlock('2fa_login_fail', $limit, $duration, "user:$userId")) {
            return ['success' => false, 'message' => $this->i18n->t('api.login_block', [$duration])];
        }

        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();

        if (!$user) {
            return ['success' => false, 'message' => $this->i18n->t('api.user_not_found')];
        }

        if ($user['account_status'] === 'deleted' || $user['account_status'] === 'suspended') {
            $isPermanent = empty($user['suspension_ends_at']);
            $isActiveSuspension = $isPermanent || (strtotime($user['suspension_ends_at']) > time());

            if ($user['account_status'] === 'deleted' || $isActiveSuspension) {
                $_SESSION['account_status_data'] = [
                    'status' => $user['account_status'],
                    'reason' => $user['status_reason'],
                    'suspension_ends_at' => $user['suspension_ends_at']
                ];
                
                unset($_SESSION['2fa_pending_user_id']);
                
                return [
                    'success' => true, 
                    'message' => 'Redirigiendo a estado de cuenta...',
                    'redirect' => '/ProjectAurora/account-status'
                ];
            }
        }

        $isValid = false;
        
        $g = new GoogleAuthenticator();
        if ($g->checkCode($user['two_factor_secret'], $code)) {
            $isValid = true;
        } else {
            $recoveryHashes = json_decode($user['two_factor_recovery_codes'] ?? '[]', true);
            if (is_array($recoveryHashes)) {
                foreach ($recoveryHashes as $index => $hash) {
                    if (password_verify($code, $hash)) {
                        $isValid = true;
                        unset($recoveryHashes[$index]);
                        $newJson = json_encode(array_values($recoveryHashes)); 
                        $this->pdo->prepare("UPDATE users SET two_factor_recovery_codes = ? WHERE id = ?")->execute([$newJson, $userId]);
                        break;
                    }
                }
            }
        }

        if ($isValid) {
            unset($_SESSION['2fa_pending_user_id']);
            return $this->completeLogin($user);
        } else {
            $this->logSecurityEvent("user:$userId", '2fa_login_fail', $duration);
            return ['success' => false, 'message' => $this->i18n->t('api.code_invalid')];
        }
    }

    public function requestReset($email) {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => $this->i18n->t('api.email_invalid')];
        }

        $limit = (int)Utils::getServerConfig($this->pdo, 'security_login_max_attempts', '5');
        $duration = (int)Utils::getServerConfig($this->pdo, 'security_block_duration', '15');

        if ($this->checkSecurityBlock('recovery_fail', $limit, $duration, $email)) {
            return ['success' => false, 'message' => $this->i18n->t('api.recovery_limit')];
        }

        if ($this->checkSecurityBlock('recovery_success', $limit, 60)) {
            return ['success' => false, 'message' => $this->i18n->t('api.recovery_limit')];
        }

        $stmt = $this->pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        
        if (!$stmt->fetch()) {
            $this->logSecurityEvent($email, 'recovery_fail', $duration);
            return ['success' => false, 'message' => $this->i18n->t('api.email_not_found')];
        }

        $checkRecent = $this->pdo->prepare("SELECT created_at FROM password_resets WHERE email = ? AND created_at > (NOW() - INTERVAL 60 SECOND)");
        $checkRecent->execute([$email]);
        if ($checkRecent->fetch()) {
             return ['success' => false, 'message' => $this->i18n->t('api.wait_resend')];
        }

        $token = bin2hex(random_bytes(32));
        $expiryMinutes = (int)Utils::getServerConfig($this->pdo, 'auth_reset_token_expiry', '60');
        $expiresAt = date('Y-m-d H:i:s', strtotime("+$expiryMinutes minutes"));

        $this->pdo->prepare("DELETE FROM password_resets WHERE email = ?")->execute([$email]);
        
        $sql = "INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)";
        
        try {
            $this->pdo->prepare($sql)->execute([$email, $token, $expiresAt]);
            
            $this->logSecurityEvent($email, 'recovery_success', 60);

            $resetLink = "https://tudominio.com/ProjectAurora/reset-password?token=" . $token; 
            
            $subject = "Recuperar contraseña - Project Aurora";
            
            // [MODIFICADO] Uso de EmailTemplates
            $body = EmailTemplates::passwordReset($resetLink, $expiryMinutes);
            
            MailService::send($email, $subject, $body);
            
            return [
                'success' => true, 
                'message_user' => $this->i18n->t('api.message_email_sent')
            ];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $this->i18n->t('api.internal_error') . ': ' . $e->getMessage()];
        }
    }

    public function resetPassword($token, $newPassword) {
        if (empty($token) || empty($newPassword)) {
            return ['success' => false, 'message' => $this->i18n->t('api.missing_data')];
        }

        $minPassLen = (int)Utils::getServerConfig($this->pdo, 'password_min_length', '6');
        if (strlen($newPassword) < $minPassLen) {
            return ['success' => false, 'message' => $this->i18n->t('api.pass_short', [$minPassLen])];
        }

        $stmt = $this->pdo->prepare("SELECT email FROM password_resets WHERE token = ? AND expires_at > NOW() LIMIT 1");
        $stmt->execute([$token]);
        $resetRequest = $stmt->fetch();

        if (!$resetRequest) {
            return ['success' => false, 'message' => $this->i18n->t('api.link_invalid')];
        }

        $email = $resetRequest['email'];
        $newHash = password_hash($newPassword, PASSWORD_DEFAULT);

        $this->pdo->beginTransaction();
        try {
            $update = $this->pdo->prepare("UPDATE users SET password = ? WHERE email = ?");
            $update->execute([$newHash, $email]);

            $this->pdo->prepare("DELETE FROM password_resets WHERE email = ?")->execute([$email]);

            $stmtUser = $this->pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmtUser->execute([$email]);
            $uData = $stmtUser->fetch();
            if($uData) {
                // Borrar tokens de persistencia
                $this->pdo->prepare("DELETE FROM user_auth_tokens WHERE user_id = ?")->execute([$uData['id']]);
                
                // [IMPORTANTE] Desconectar websockets activos
                $this->redis->publish('aurora_ws_control', json_encode([
                    'cmd' => 'KICK_ALL',
                    'user_id' => $uData['id']
                ]));
            }

            $this->pdo->commit();
            return ['success' => true, 'message' => $this->i18n->t('api.pass_updated'), 'redirect' => '/ProjectAurora/login'];
        } catch (Exception $e) {
            $this->pdo->rollBack();
            return ['success' => false, 'message' => $this->i18n->t('api.update_error') . ': ' . $e->getMessage()];
        }
    }

    public function logout() {
        if (isset($_COOKIE['auth_persistence_token'])) {
            $parts = explode(':', $_COOKIE['auth_persistence_token']);
            if (count($parts) === 2) {
                $selector = $parts[0];
                $del = $this->pdo->prepare("DELETE FROM user_auth_tokens WHERE selector = ?");
                $del->execute([$selector]);
            }
        }
        
        // [MODIFICADO] Usar comando específico para Logout Manual
        if (isset($_SESSION['user_id']) && isset($_SESSION['current_token_id'])) {
             $this->redis->publish('aurora_ws_control', json_encode([
                'cmd' => 'LOGOUT_SESSION', // Antes KICK_SESSION
                'user_id' => $_SESSION['user_id'],
                'session_id' => $_SESSION['current_token_id']
            ]));
        }

        setcookie('auth_persistence_token', '', time() - 3600, '/', '', isset($_SERVER['HTTPS']), true);
        unset($_COOKIE['auth_persistence_token']);
        session_destroy();
        
        return ['success' => true, 'message' => $this->i18n->t('api.bye'), 'redirect' => '/ProjectAurora/login'];
    }

    public function attemptAutoLogin() {
        if (isset($_SESSION['user_id'])) return;
        if (!isset($_COOKIE['auth_persistence_token'])) return;

        $parts = explode(':', $_COOKIE['auth_persistence_token']);
        if (count($parts) !== 2) return;

        $selector = $parts[0];
        $validator = $parts[1];

        $stmt = $this->pdo->prepare("SELECT * FROM user_auth_tokens WHERE selector = ? AND expires_at > NOW() LIMIT 1");
        $stmt->execute([$selector]);
        $authToken = $stmt->fetch();

        if ($authToken && hash_equals($authToken['hashed_validator'], hash('sha256', $validator))) {
            $stmtUser = $this->pdo->prepare("SELECT * FROM users WHERE id = ?");
            $stmtUser->execute([$authToken['user_id']]);
            $user = $stmtUser->fetch();

            if ($user) {
                if ($user['account_status'] !== 'active') {
                    $isPermanent = empty($user['suspension_ends_at']);
                    $isActiveSuspension = $isPermanent || (strtotime($user['suspension_ends_at']) > time());
                    
                    if ($user['account_status'] === 'deleted' || $isActiveSuspension) {
                        $this->pdo->prepare("DELETE FROM user_auth_tokens WHERE id = ?")->execute([$authToken['id']]);
                        setcookie('auth_persistence_token', '', time() - 3600, '/', '', isset($_SERVER['HTTPS']), true);
                        return; 
                    }
                }

                $this->fillSession($user);
                
                $this->pdo->prepare("DELETE FROM user_auth_tokens WHERE id = ?")->execute([$authToken['id']]);
                $newTokenId = $this->createPersistenceToken($user['id']);
                
                $_SESSION['current_token_id'] = $newTokenId;
            }
        }
    }

    // =========================================================
    // NUEVA LÓGICA DE RATE LIMITING (HÍBRIDO REDIS/SQL)
    // =========================================================

    private function incrementRedisCounter($key, $minutes) {
        if (!$this->redis) return;
        try {
            $current = $this->redis->incr($key);
            if ($current === 1) {
                $this->redis->expire($key, $minutes * 60);
            }
        } catch (Exception $e) {
            error_log("Redis RateLimit Error (INCR): " . $e->getMessage());
        }
    }

    private function checkSecurityBlock($actionType, $limit, $minutes, $identifier = '') {
        if (!$this->redis) {
            return $this->checkSecurityBlockSQL($actionType, $limit, $minutes, $identifier);
        }

        $ip = $this->getClientIp();
        $ipKey = "rate_limit:{$actionType}:ip:{$ip}";
        $userKey = $identifier ? "rate_limit:{$actionType}:user:{$identifier}" : null;

        try {
            $ipCount = $this->redis->get($ipKey);
            if ($ipCount && (int)$ipCount >= $limit) return true;

            if ($userKey) {
                $userCount = $this->redis->get($userKey);
                if ($userCount && (int)$userCount >= $limit) return true;
            }
        } catch (Exception $e) {
            return $this->checkSecurityBlockSQL($actionType, $limit, $minutes, $identifier);
        }

        return false;
    }

    private function logSecurityEvent($identifier, $actionType, $minutes = 15) {
        $ip = $this->getClientIp();
        
        try {
            $stmt = $this->pdo->prepare("INSERT INTO security_logs (user_identifier, action_type, ip_address) VALUES (?, ?, ?)");
            $stmt->execute([$identifier, $actionType, $ip]);
        } catch (Exception $e) {
            error_log("Audit Log Error: " . $e->getMessage());
        }

        $ipKey = "rate_limit:{$actionType}:ip:{$ip}";
        $userKey = $identifier ? "rate_limit:{$actionType}:user:{$identifier}" : null;

        $this->incrementRedisCounter($ipKey, $minutes);
        if ($userKey) {
            $this->incrementRedisCounter($userKey, $minutes);
        }
    }

    private function checkSecurityBlockSQL($actionType, $limit, $minutes, $identifier = '') {
        $ip = $this->getClientIp();
        $sql = "SELECT COUNT(*) as failures FROM security_logs WHERE (ip_address = ? OR user_identifier = ?) AND action_type = ? AND created_at > (NOW() - INTERVAL $minutes MINUTE)";     
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$ip, $identifier, $actionType]);
        $result = $stmt->fetch();
        return ($result && $result['failures'] >= $limit);
    }

    // =========================================================

    private function verifyTurnstile($token) {
        if (empty($token)) return ['success' => false, 'message' => 'Por favor completa el captcha.'];

        $url = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';
        $ip = $this->getClientIp();

        $data = [
            'secret' => $this->turnstileSecret,
            'response' => $token,
            'remoteip' => $ip
        ];

        $options = [
            'http' => [
                'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
                'method'  => 'POST',
                'content' => http_build_query($data)
            ]
        ];

        $context  = stream_context_create($options);
        $result = @file_get_contents($url, false, $context);

        if ($result === FALSE) return ['success' => false, 'message' => 'No se pudo conectar con el servidor de validación.'];

        $response = json_decode($result, true);

        if ($response['success']) return ['success' => true];
        else return ['success' => false, 'message' => 'Validación fallida. Inténtalo de nuevo.'];
    }

    private function generateUuid() {
        return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000, mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }

    private function getBestMatchLanguage() {
        $available_langs = ['es-latam', 'es-mx', 'en-us', 'en-gb', 'fr-fr'];
        $default_lang = 'es-latam';
        $http_accept_language = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';
        
        if (empty($http_accept_language)) return $default_lang;

        preg_match_all('/([a-z]{1,8}(-[a-z]{1,8})?)\s*(;\s*q\s*=\s*(1|0\.[0-9]+))?/i', $http_accept_language, $matches);

        if (count($matches[1])) {
            $langs = array_combine($matches[1], $matches[4]);
            $langs = array_change_key_case($langs, CASE_LOWER);
            foreach ($langs as $lang => $val) {
                if ($val === '') $langs[$lang] = 1;
            }
            arsort($langs, SORT_NUMERIC);
            foreach ($langs as $lang => $val) {
                if (in_array($lang, $available_langs)) return $lang;
                $prefix = substr($lang, 0, 2); 
                if ($prefix === 'es') return 'es-latam';
                if ($prefix === 'en') return 'en-us'; 
                if ($prefix === 'fr') return 'fr-fr';
            }
        }
        return $default_lang;
    }

    private function getClientIp() {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
    }

    private function createPersistenceToken($userId) {
        $selector = bin2hex(random_bytes(12)); 
        $validator = bin2hex(random_bytes(32));
        $hashedValidator = hash('sha256', $validator);
        $expiresAt = date('Y-m-d H:i:s', time() + (86400 * 30)); 
        $ip = $this->getClientIp();
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';

        $sql = "INSERT INTO user_auth_tokens (user_id, selector, hashed_validator, ip_address, user_agent, expires_at) VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$userId, $selector, $hashedValidator, $ip, $userAgent, $expiresAt]);
        
        $tokenId = $this->pdo->lastInsertId();

        $cookieName = 'auth_persistence_token';
        $cookieValue = "$selector:$validator";
        $isSecure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on'; 
        
        setcookie($cookieName, $cookieValue, [
            'expires' => time() + (86400 * 30),
            'path' => '/',
            'domain' => '', 
            'secure' => $isSecure, 
            'httponly' => true,
            'samesite' => 'Strict'
        ]);
        
        return $tokenId;
    }

    private function fillSession($user) {
        session_regenerate_id(true);

        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['avatar'] = $user['avatar_path'];
        $_SESSION['email'] = $user['email'];

        $prefStmt = $this->pdo->prepare("SELECT language, open_links_new_tab, theme, extended_toast FROM user_preferences WHERE user_id = ?");
        $prefStmt->execute([$user['id']]);
        $prefs = $prefStmt->fetch();

        $_SESSION['preferences'] = $prefs ? [
            'language' => $prefs['language'],
            'open_links_new_tab' => (bool)$prefs['open_links_new_tab'],
            'theme' => $prefs['theme'],
            'extended_toast' => (bool)$prefs['extended_toast']
        ] : [
            'language' => 'es-latam',
            'open_links_new_tab' => true,
            'theme' => 'sync',
            'extended_toast' => false
        ];
    }

    private function completeLogin($user) {
        $this->fillSession($user);
        
        $tokenId = $this->createPersistenceToken($user['id']);
        $_SESSION['current_token_id'] = $tokenId;
        
        return ['success' => true, 'message' => $this->i18n->t('api.welcome'), 'redirect' => '/ProjectAurora/'];
    }

    public function generateWebSocketToken() {
        if (!isset($_SESSION['user_id'])) {
            return ['success' => false, 'message' => 'No autenticado'];
        }

        $userId = $_SESSION['user_id'];
        $sessionId = $_SESSION['current_token_id'] ?? 0;

        $token = bin2hex(random_bytes(32)); 
        
        $key = "ws_token:$token";
        $value = "$userId:$sessionId";
        
        try {
            $this->redis->setex($key, 15, $value);
            return ['success' => true, 'ws_token' => $token];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Error Redis: ' . $e->getMessage()];
        }
    }
}
?>