<?php
// api/services/AuthService.php

require_once __DIR__ . '/../../includes/libs/GoogleAuthenticator.php';

class AuthService {
    private $pdo;
    private $i18n;
    private $turnstileSecret;

    public function __construct($pdo, $i18n) {
        $this->pdo = $pdo;
        $this->i18n = $i18n;

        // [CORRECCIÓN CRÍTICA] Lógica robusta para cargar la clave secreta
        // 1. Intentar leer de $_ENV (Cargado por db.php)
        $key = $_ENV['TURNSTILE_SECRET_KEY'] ?? null;

        // 2. Si no existe, intentar getenv() manejando el valor 'false' explícitamente
        if (!$key) {
            $envVal = getenv('TURNSTILE_SECRET_KEY');
            if ($envVal !== false && $envVal !== '') {
                $key = $envVal;
            }
        }

        // 3. Fallback: Si sigue vacío, usar la clave de prueba OFICIAL de Cloudflare (Siempre pasa)
        // Clave Test Secret: 1x00000000000000000000BB
        $this->turnstileSecret = $key ?: '1x00000000000000000000BB';
    }

    // =========================================================================
    //  MÉTODOS PÚBLICOS
    // =========================================================================

    public function registerStep1($email, $password, $turnstileToken) {
        // 1. Validar Turnstile con Logs Detallados
        $turnstileCheck = $this->verifyTurnstile($turnstileToken);
        
        if (!$turnstileCheck['success']) {
            return [
                'success' => false, 
                'message' => 'Error de seguridad (Captcha): ' . $turnstileCheck['message'],
                'debug' => $turnstileCheck['debug'] ?? null // [DEBUG] Pasar logs al frontend
            ];
        }

        if ($this->checkSecurityBlock('register_attempt', 10, 10)) {
            return ['success' => false, 'message' => $this->i18n->t('api.pref_rate_limit')];
        }
        $this->logSecurityEvent($email, 'register_attempt');

        if (empty($email) || empty($password)) {
            return ['success' => false, 'message' => $this->i18n->t('api.fill_all')];
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => $this->i18n->t('api.email_invalid')];
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
        if (!isset($_SESSION['temp_register']['email'])) {
            return ['success' => false, 'message' => $this->i18n->t('api.register_expired')];
        }
        
        $email = $_SESSION['temp_register']['email'];
        
        if ($this->checkSecurityBlock('register_code_req', 3, 15, $email)) {
            return ['success' => false, 'message' => $this->i18n->t('api.wait_resend')];
        }

        $password = $_SESSION['temp_register']['password'];

        if (empty($username)) {
            return ['success' => false, 'message' => $this->i18n->t('api.choose_user')];
        }

        $stmt = $this->pdo->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$username]);
        if ($stmt->fetch()) {
            return ['success' => false, 'message' => $this->i18n->t('api.user_taken')];
        }

        $_SESSION['temp_register']['username'] = $username;
        
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        $payload = json_encode([
            'username' => $username,
            'email' => $email,
            'password_hash' => $passwordHash
        ]);

        $code = str_pad(mt_rand(0, 999999), 6, '0', STR_PAD_LEFT);
        $expiresAt = date('Y-m-d H:i:s', strtotime('+15 minutes'));

        $del = $this->pdo->prepare("DELETE FROM verification_codes WHERE identifier = ? AND code_type = 'account_activation'");
        $del->execute([$email]);

        $sql = "INSERT INTO verification_codes (identifier, code_type, code, payload, expires_at) VALUES (?, 'account_activation', ?, ?, ?)";
        $stmt = $this->pdo->prepare($sql);

        try {
            $stmt->execute([$email, $code, $payload, $expiresAt]);
            $this->logSecurityEvent($email, 'register_code_req');

            $_SESSION['pending_verification_email'] = $email;

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
        
        if ($this->checkSecurityBlock('resend_code_req', 3, 10, $email)) {
             return ['success' => false, 'message' => $this->i18n->t('api.wait_resend')];
        }

        $checkTime = $this->pdo->prepare("
            SELECT created_at 
            FROM verification_codes 
            WHERE identifier = ? 
            AND code_type = 'account_activation' 
            AND created_at > (NOW() - INTERVAL 60 SECOND)
            ORDER BY id DESC LIMIT 1
        ");
        $checkTime->execute([$email]);
        
        if ($checkTime->fetch()) {
            return ['success' => false, 'message' => $this->i18n->t('api.wait_resend')];
        }

        $stmt = $this->pdo->prepare("SELECT payload FROM verification_codes WHERE identifier = ? AND code_type = 'account_activation' ORDER BY id DESC LIMIT 1");
        $stmt->execute([$email]);
        $lastCode = $stmt->fetch();

        if (!$lastCode) {
            return ['success' => false, 'message' => $this->i18n->t('api.no_data')];
        }

        $payload = $lastCode['payload'];
        $newCode = str_pad(mt_rand(0, 999999), 6, '0', STR_PAD_LEFT);
        $expiresAt = date('Y-m-d H:i:s', strtotime('+15 minutes'));

        $del = $this->pdo->prepare("DELETE FROM verification_codes WHERE identifier = ? AND code_type = 'account_activation'");
        $del->execute([$email]);

        $insert = $this->pdo->prepare("INSERT INTO verification_codes (identifier, code_type, code, payload, expires_at) VALUES (?, 'account_activation', ?, ?, ?)");
        
        try {
            $insert->execute([$email, $newCode, $payload, $expiresAt]);
            $this->logSecurityEvent($email, 'resend_code_req');
            return ['success' => true, 'message' => $this->i18n->t('api.code_generated')];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $this->i18n->t('api.code_save_error') . ': ' . $e->getMessage()];
        }
    }

    public function completeRegister($code) {
        $email = $_SESSION['pending_verification_email'] ?? '';

        if (empty($code) || empty($email)) {
            return ['success' => false, 'message' => $this->i18n->t('api.missing_data')];
        }

        if ($this->checkSecurityBlock('register_verify_fail', 5, 15, $email)) {
            return ['success' => false, 'message' => $this->i18n->t('api.login_block')];
        }

        $sql = "SELECT * FROM verification_codes 
                WHERE identifier = ? 
                AND code = ? 
                AND code_type = 'account_activation' 
                AND expires_at > NOW() 
                ORDER BY id DESC LIMIT 1";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$email, $code]);
        $verification = $stmt->fetch();

        if (!$verification) {
            $this->logSecurityEvent($email, 'register_verify_fail'); 
            return ['success' => false, 'message' => $this->i18n->t('api.code_invalid_expired')];
        }

        $payload = json_decode($verification['payload'], true);
        $username = $payload['username'];
        $passwordHash = $payload['password_hash']; 
        $uuid = $this->generateUuid();

        $firstLetter = substr($username, 0, 1);
        $bgColors = ['40a060', 'a73d3d', '3d3da7', '3d9da7', '9d3da7'];
        $randomBg = $bgColors[array_rand($bgColors)];
        $avatarUrl = "https://ui-avatars.com/api/?name=" . urlencode($firstLetter) . "&background=" . $randomBg . "&color=fff&size=512&format=png&bold=true";

        $storageDir = __DIR__ . '/../../storage/profilePicture/default/';
        if (!is_dir($storageDir)) { mkdir($storageDir, 0777, true); }
        
        $fileName = $uuid . '.png';
        $filePath = $storageDir . $fileName;
        $imageContent = @file_get_contents($avatarUrl);
        
        $dbAvatarPath = ($imageContent !== false && file_put_contents($filePath, $imageContent)) 
            ? 'storage/profilePicture/default/' . $fileName 
            : null;

        $this->pdo->beginTransaction();
        try {
            $insertUser = $this->pdo->prepare("INSERT INTO users (uuid, username, email, password, role, avatar_path) VALUES (?, ?, ?, ?, 'user', ?)");
            $insertUser->execute([$uuid, $username, $email, $passwordHash, $dbAvatarPath]);
            $newUserId = $this->pdo->lastInsertId();

            $detectedLang = $this->getBestMatchLanguage();
            $prefStmt = $this->pdo->prepare("INSERT INTO user_preferences (user_id, language, open_links_new_tab, theme, extended_toast) VALUES (?, ?, 1, 'sync', 0)");
            $prefStmt->execute([$newUserId, $detectedLang]);

            $this->pdo->prepare("DELETE FROM verification_codes WHERE id = ?")->execute([$verification['id']]);

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
        // 1. Validar Turnstile con Logs Detallados
        $turnstileCheck = $this->verifyTurnstile($turnstileToken);
        
        if (!$turnstileCheck['success']) {
             return [
                 'success' => false, 
                 'message' => 'Error de seguridad (Captcha): ' . $turnstileCheck['message'],
                 'debug' => $turnstileCheck['debug'] ?? null // [DEBUG] Pasar logs al frontend
             ];
        }

        if ($this->checkSecurityBlock('login_fail', 5, 15, $email)) {
            return ['success' => false, 'message' => $this->i18n->t('api.login_block')];
        }

        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $status = $user['account_status'] ?? 'active';
            if ($status === 'deleted') {
                return ['success' => false, 'message' => $this->i18n->t('api.account_deleted')];
            }

            if (isset($user['two_factor_enabled']) && $user['two_factor_enabled'] == 1) {
                $_SESSION['2fa_pending_user_id'] = $user['id'];
                return ['success' => true, 'message' => $this->i18n->t('api.credentials_ok_2fa'), 'require_2fa' => true];
            }

            return $this->completeLogin($user);
        } else {
            $this->logSecurityEvent($email, 'login_fail');
            return ['success' => false, 'message' => $this->i18n->t('api.credentials_invalid')];
        }
    }

    public function verify2faLogin($code) {
        if (!isset($_SESSION['2fa_pending_user_id'])) {
            return ['success' => false, 'message' => $this->i18n->t('api.session_expired')];
        }

        $userId = $_SESSION['2fa_pending_user_id'];
        
        if ($this->checkSecurityBlock('2fa_login_fail', 5, 15, "user:$userId")) {
            return ['success' => false, 'message' => $this->i18n->t('api.login_block')];
        }

        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();

        if (!$user) {
            return ['success' => false, 'message' => $this->i18n->t('api.user_not_found')];
        }

        $isValid = false;
        
        if (GoogleAuthenticator::verifyCode($user['two_factor_secret'], $code)) {
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
            $this->logSecurityEvent("user:$userId", '2fa_login_fail');
            return ['success' => false, 'message' => $this->i18n->t('api.code_invalid')];
        }
    }

    public function requestReset($email) {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => $this->i18n->t('api.email_invalid')];
        }

        if ($this->checkSecurityBlock('recovery_fail', 5, 15, $email)) {
            return ['success' => false, 'message' => $this->i18n->t('api.recovery_limit')];
        }

        if ($this->checkSecurityBlock('recovery_success', 5, 60)) {
            return ['success' => false, 'message' => $this->i18n->t('api.recovery_limit')];
        }

        $stmt = $this->pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        
        if (!$stmt->fetch()) {
            $this->logSecurityEvent($email, 'recovery_fail');
            return ['success' => false, 'message' => $this->i18n->t('api.email_not_found')];
        }

        $checkRecent = $this->pdo->prepare("SELECT created_at FROM password_resets WHERE email = ? AND created_at > (NOW() - INTERVAL 60 SECOND)");
        $checkRecent->execute([$email]);
        if ($checkRecent->fetch()) {
             return ['success' => false, 'message' => $this->i18n->t('api.wait_resend')];
        }

        $token = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour'));

        $this->pdo->prepare("DELETE FROM password_resets WHERE email = ?")->execute([$email]);
        
        $sql = "INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)";
        
        try {
            $this->pdo->prepare($sql)->execute([$email, $token, $expiresAt]);
            $this->logSecurityEvent($email, 'recovery_success');

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

        if (strlen($newPassword) < 6) {
            return ['success' => false, 'message' => $this->i18n->t('api.pass_short')];
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
                $this->pdo->prepare("DELETE FROM user_auth_tokens WHERE user_id = ?")->execute([$uData['id']]);
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
        setcookie('auth_persistence_token', '', time() - 3600, '/', '', isset($_SERVER['HTTPS']), true);
        unset($_COOKIE['auth_persistence_token']);
        session_destroy();
        
        return ['success' => true, 'message' => $this->i18n->t('api.bye'), 'redirect' => '/ProjectAurora/login'];
    }

    // =========================================================================
    //  MÉTODOS PRIVADOS
    // =========================================================================

    private function verifyTurnstile($token) {
        // [DEBUG] Preparar información técnica para el frontend
        $ip = $this->getClientIp();
        $debugInfo = [
            'timestamp' => date('Y-m-d H:i:s'),
            'backend_secret_key_source' => 'unknown',
            'backend_secret_key_preview' => 'N/A',
            'remote_ip_sent' => $ip,
            'received_token_length' => strlen($token),
            'received_token_preview' => substr($token, 0, 10) . '...',
        ];

        // Determinar origen de la clave (solo informativo)
        if ($this->turnstileSecret === '1x00000000000000000000BB') {
            $debugInfo['backend_secret_key_source'] = 'FALLBACK_TEST_KEY (1x...BB)';
        } else {
            $debugInfo['backend_secret_key_source'] = 'CUSTOM_ENV_KEY';
        }
        $debugInfo['backend_secret_key_preview'] = substr($this->turnstileSecret, 0, 10) . '...';

        if (empty($token)) {
            return [
                'success' => false, 
                'message' => 'Por favor completa el captcha (Token vacío).',
                'debug' => $debugInfo
            ];
        }

        $url = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';
        
        $data = [
            'secret' => $this->turnstileSecret,
            'response' => $token,
            'remoteip' => $ip
        ];

        // [CORRECCIÓN CRÍTICA] Desactivar verificación SSL para evitar fallos en local
        $options = [
            'http' => [
                'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
                'method'  => 'POST',
                'content' => http_build_query($data),
                'ignore_errors' => true // Traer el contenido incluso si es 400/500
            ],
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false
            ]
        ];

        $context  = stream_context_create($options);
        
        // Capturar headers de respuesta (variable mágica $http_response_header)
        // Inicializamos para evitar warnings si la petición falla catastróficamente
        $http_response_header = null;
        
        $result = @file_get_contents($url, false, $context);
        
        // Agregar info de conexión al debug
        $debugInfo['http_response_header'] = $http_response_header ?? 'No headers (Connection Failed)';
        $debugInfo['php_error_last'] = error_get_last();
        $debugInfo['raw_result_preview'] = substr($result, 0, 500); // Primeros 500 chars

        if ($result === FALSE) {
            return [
                'success' => false, 
                'message' => 'Error de conexión HTTP con Cloudflare (Ver consola).', 
                'debug' => $debugInfo
            ];
        }

        $response = json_decode($result, true);
        $debugInfo['json_error'] = json_last_error_msg();
        $debugInfo['cloudflare_response_parsed'] = $response;

        if (isset($response['success']) && $response['success'] === true) {
            return ['success' => true, 'debug' => $debugInfo];
        } else {
            $codes = json_encode($response['error-codes'] ?? []);
            return [
                'success' => false, 
                'message' => "Validación fallida (Codes: $codes).", 
                'debug' => $debugInfo
            ];
        }
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

    private function logSecurityEvent($identifier, $actionType) {
        $ip = $this->getClientIp();
        $stmt = $this->pdo->prepare("INSERT INTO security_logs (user_identifier, action_type, ip_address) VALUES (?, ?, ?)");
        $stmt->execute([$identifier, $actionType, $ip]);
    }

    private function checkSecurityBlock($actionType, $limit, $minutes, $identifier = '') {
        $ip = $this->getClientIp();
        
        $sql = "SELECT COUNT(*) as failures 
                FROM security_logs 
                WHERE (ip_address = ? OR user_identifier = ?) 
                AND action_type = ? 
                AND created_at > (NOW() - INTERVAL $minutes MINUTE)";
                
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$ip, $identifier, $actionType]);
        $result = $stmt->fetch();
        
        return ($result && $result['failures'] >= $limit);
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
    }

    private function completeLogin($user) {
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

        $this->createPersistenceToken($user['id']);

        return ['success' => true, 'message' => $this->i18n->t('api.welcome'), 'redirect' => '/ProjectAurora/'];
    }
}
?>