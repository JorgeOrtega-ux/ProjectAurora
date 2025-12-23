<?php
// api/settings-handler.php
session_start();
require_once __DIR__ . '/../config/database/db.php';
require_once __DIR__ . '/../includes/libs/TOTP.php'; 
require_once __DIR__ . '/../includes/libs/I18n.php';

header('Content-Type: application/json');

// Inicializar I18n
$userLang = $_SESSION['preferences']['language'] ?? 'es-latam';
$i18n = new I18n($userLang);

function jsonResponse($success, $message, $data = []) {
    echo json_encode(array_merge(['success' => $success, 'message' => $message], $data));
    exit;
}

// 1. Verificar Autenticación
if (!isset($_SESSION['user_id'])) {
    jsonResponse(false, $i18n->t('api.session_expired'));
}

// 2. Verificar CSRF
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        jsonResponse(false, $i18n->t('api.security_error'));
    }
}

$userId = $_SESSION['user_id'];
$action = $_POST['action'] ?? '';

// ... (UTILIDADES AVATAR) ...
$baseDir = __DIR__ . '/../storage/profilePicture/';
$dirDefault = $baseDir . 'default/';
$dirCustom  = $baseDir . 'custom/';
if (!is_dir($dirDefault)) mkdir($dirDefault, 0777, true);
if (!is_dir($dirCustom)) mkdir($dirCustom, 0777, true);

function deleteOldAvatar($pdo, $userId, $currentPath) {
    if ($currentPath && file_exists(__DIR__ . '/../' . $currentPath)) {
        @unlink(__DIR__ . '/../' . $currentPath);
    }
}
function parseUserAgent($ua) {
    $platform = 'Desconocido'; $browser = 'Desconocido';
    if (preg_match('/windows|win32/i', $ua)) $platform = 'Windows';
    elseif (preg_match('/macintosh|mac os x/i', $ua)) $platform = 'Mac OS';
    elseif (preg_match('/linux/i', $ua)) $platform = 'Linux';
    elseif (preg_match('/android/i', $ua)) $platform = 'Android';
    elseif (preg_match('/iphone|ipad|ipod/i', $ua)) $platform = 'iOS';
    if (preg_match('/MSIE|Trident/i', $ua)) $browser = 'Internet Explorer';
    elseif (preg_match('/Firefox/i', $ua)) $browser = 'Firefox';
    elseif (preg_match('/Chrome/i', $ua)) $browser = 'Chrome';
    elseif (preg_match('/Safari/i', $ua)) $browser = 'Safari';
    elseif (preg_match('/Opera|OPR/i', $ua)) $browser = 'Opera';
    elseif (preg_match('/Edge/i', $ua)) $browser = 'Edge';
    return ['platform' => $platform, 'browser' => $browser];
}

// ... (ACCIONES) ...
if ($action === 'upload_avatar') {
    if (!isset($_FILES['avatar']) || $_FILES['avatar']['error'] !== UPLOAD_ERR_OK) { jsonResponse(false, $i18n->t('api.no_image')); }
    $file = $_FILES['avatar'];
    $allowedTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    if (!in_array($mime, $allowedTypes)) { jsonResponse(false, $i18n->t('api.image_format')); }
    $stmt = $pdo->prepare("SELECT avatar_path, uuid FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $currentUser = $stmt->fetch();
    $oldPath = $currentUser['avatar_path'];
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $newFileName = $currentUser['uuid'] . '-' . time() . '.' . $extension;
    $targetPath = $dirCustom . $newFileName;
    $dbPath = 'storage/profilePicture/custom/' . $newFileName;
    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        $update = $pdo->prepare("UPDATE users SET avatar_path = ? WHERE id = ?");
        if ($update->execute([$dbPath, $userId])) {
            deleteOldAvatar($pdo, $userId, $oldPath);
            $_SESSION['avatar'] = $dbPath;
            jsonResponse(true, $i18n->t('api.pic_updated'), ['new_src' => $dbPath, 'type' => 'custom']);
        } else {
            @unlink($targetPath);
            jsonResponse(false, $i18n->t('api.pic_db_error'));
        }
    } else { jsonResponse(false, $i18n->t('api.pic_move_error')); }

} elseif ($action === 'delete_avatar') {
    $stmt = $pdo->prepare("SELECT avatar_path, username, uuid FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $currentUser = $stmt->fetch();
    $oldPath = $currentUser['avatar_path'];
    $firstLetter = substr($currentUser['username'], 0, 1);
    $avatarUrl = "https://ui-avatars.com/api/?name=" . urlencode($firstLetter) . "&background=random&color=fff&size=128&format=png";
    $newFileName = $currentUser['uuid'] . '-' . time() . '.png';
    $targetPath = $dirDefault . $newFileName;
    $dbPath = 'storage/profilePicture/default/' . $newFileName;
    $imageContent = @file_get_contents($avatarUrl);
    if ($imageContent !== false && file_put_contents($targetPath, $imageContent)) {
        $update = $pdo->prepare("UPDATE users SET avatar_path = ? WHERE id = ?");
        if ($update->execute([$dbPath, $userId])) {
            deleteOldAvatar($pdo, $userId, $oldPath);
            $_SESSION['avatar'] = $dbPath;
            jsonResponse(true, $i18n->t('api.pic_deleted'), ['type' => 'default']);
        } else { jsonResponse(false, $i18n->t('api.pic_db_error')); }
    } else { jsonResponse(false, $i18n->t('api.pic_gen_error')); }

} elseif ($action === 'update_profile') {
    $field = $_POST['field'] ?? '';
    $value = trim($_POST['value'] ?? '');
    if (!in_array($field, ['username', 'email'])) { jsonResponse(false, $i18n->t('api.field_invalid')); }
    if (empty($value)) { jsonResponse(false, $i18n->t('api.field_empty')); }
    if ($field === 'email') { if (!filter_var($value, FILTER_VALIDATE_EMAIL)) { jsonResponse(false, $i18n->t('api.email_invalid')); } }
    $stmt = $pdo->prepare("SELECT id FROM users WHERE $field = ? AND id != ?");
    $stmt->execute([$value, $userId]);
    if ($stmt->fetch()) { jsonResponse(false, $i18n->t('api.field_in_use')); }
    try {
        $update = $pdo->prepare("UPDATE users SET $field = ? WHERE id = ?");
        $update->execute([$value, $userId]);
        $_SESSION[$field] = $value;
        jsonResponse(true, $i18n->t('api.field_updated'));
    } catch (Exception $e) { jsonResponse(false, $i18n->t('api.update_error') . ': ' . $e->getMessage()); }

} elseif ($action === 'validate_current_password') {
    $currentPass = $_POST['current_password'] ?? '';
    if (empty($currentPass)) { jsonResponse(false, $i18n->t('api.pass_current_req')); }
    $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    if ($user && password_verify($currentPass, $user['password'])) { jsonResponse(true, $i18n->t('api.pass_correct')); } else { jsonResponse(false, $i18n->t('api.pass_incorrect')); }

} elseif ($action === 'change_password') {
    $currentPass = $_POST['current_password'] ?? '';
    $newPass     = $_POST['new_password'] ?? '';
    if (empty($currentPass) || empty($newPass)) { jsonResponse(false, $i18n->t('api.missing_data')); }
    if (strlen($newPass) < 6) { jsonResponse(false, $i18n->t('api.pass_short')); }
    $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    if (!$user || !password_verify($currentPass, $user['password'])) { jsonResponse(false, $i18n->t('api.pass_incorrect')); }
    $newHash = password_hash($newPass, PASSWORD_DEFAULT);
    try {
        $update = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
        $update->execute([$newHash, $userId]);
        jsonResponse(true, $i18n->t('api.pass_updated'));
    } catch (Exception $e) { jsonResponse(false, $i18n->t('api.update_error')); }

} elseif ($action === 'update_preference') {
    $key = $_POST['key'] ?? '';
    $value = $_POST['value'] ?? '';

    $allowedKeys = [
        'language' => 'language',
        'open_links_new_tab' => 'open_links_new_tab',
        'theme' => 'theme',
        'extended_toast' => 'extended_toast'
    ];

    if (!array_key_exists($key, $allowedKeys)) {
        jsonResponse(false, $i18n->t('api.pref_invalid'));
    }

    $dbValue = $value;
    
    if ($key === 'language') {
        $allowedLangs = ['es-latam', 'es-mx', 'en-us', 'en-gb', 'fr-fr'];
        if (!in_array($value, $allowedLangs)) jsonResponse(false, $i18n->t('api.lang_invalid'));
        
    } elseif ($key === 'open_links_new_tab' || $key === 'extended_toast') {
        $dbValue = ($value === 'true' || $value === '1') ? 1 : 0;
        
    } elseif ($key === 'theme') {
        $allowedThemes = ['sync', 'light', 'dark'];
        if (!in_array($value, $allowedThemes)) jsonResponse(false, $i18n->t('api.theme_invalid'));
    }

    try {
        $stmtCheck = $pdo->prepare("SELECT $key FROM user_preferences WHERE user_id = ?");
        $stmtCheck->execute([$userId]);
        $currentDbValue = $stmtCheck->fetchColumn();
        
        if ($currentDbValue !== false) {
            $val1 = $currentDbValue; 
            $val2 = $dbValue;
            if ($key === 'open_links_new_tab' || $key === 'extended_toast') {
                $val1 = (int)$val1;
                $val2 = (int)$val2;
            }
            if ($val1 === $val2) {
                jsonResponse(true, $i18n->t('api.pref_saved_no_change'));
            }
        }

        $sql = "INSERT INTO user_preferences (user_id, $key) VALUES (?, ?) 
                ON DUPLICATE KEY UPDATE $key = VALUES($key)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$userId, $dbValue]);

        if (!isset($_SESSION['preferences'])) { $_SESSION['preferences'] = []; }
        
        if ($key === 'open_links_new_tab' || $key === 'extended_toast') {
            $_SESSION['preferences'][$key] = (bool)$dbValue;
        } else {
            $_SESSION['preferences'][$key] = $dbValue;
        }

        jsonResponse(true, $i18n->t('api.pref_saved'));
    } catch (Exception $e) {
        jsonResponse(false, $i18n->t('api.pref_save_error') . ': ' . $e->getMessage());
    }

} elseif ($action === 'init_2fa') {
    $secret = TOTP::createSecret();
    $_SESSION['temp_2fa_secret'] = $secret;
    $username = $_SESSION['username'] ?? 'User';
    $qrUrl = TOTP::getQRCodeUrl($username, $secret, 'ProjectAurora');
    jsonResponse(true, $i18n->t('api.qr_scan'), ['qr_url' => $qrUrl, 'secret' => $secret]);

} elseif ($action === 'enable_2fa') {
    $code = $_POST['code'] ?? '';
    if (!isset($_SESSION['temp_2fa_secret'])) { jsonResponse(false, $i18n->t('api.session_config_expired')); }
    $secret = $_SESSION['temp_2fa_secret'];
    if (TOTP::verifyCode($secret, $code)) {
        $recoveryCodes = [];
        for($i=0; $i<8; $i++) { $recoveryCodes[] = bin2hex(random_bytes(4)); }
        $jsonCodes = json_encode($recoveryCodes);
        $stmt = $pdo->prepare("UPDATE users SET two_factor_secret = ?, two_factor_enabled = 1, two_factor_recovery_codes = ? WHERE id = ?");
        if ($stmt->execute([$secret, $jsonCodes, $userId])) {
            unset($_SESSION['temp_2fa_secret']);
            $_SESSION['two_factor_enabled'] = 1; 
            jsonResponse(true, $i18n->t('api.2fa_enabled'), ['recovery_codes' => $recoveryCodes]);
        } else { jsonResponse(false, $i18n->t('api.pic_db_error')); }
    } else { jsonResponse(false, $i18n->t('api.code_invalid')); }

} elseif ($action === 'disable_2fa') {
    $stmt = $pdo->prepare("UPDATE users SET two_factor_enabled = 0, two_factor_secret = NULL, two_factor_recovery_codes = NULL WHERE id = ?");
    if ($stmt->execute([$userId])) {
        $_SESSION['two_factor_enabled'] = 0;
        jsonResponse(true, $i18n->t('api.2fa_disabled'));
    } else { jsonResponse(false, $i18n->t('api.update_error')); }

} elseif ($action === 'get_sessions') {
    $stmt = $pdo->prepare("SELECT id, selector, ip_address, user_agent, created_at FROM user_auth_tokens WHERE user_id = ? ORDER BY created_at DESC");
    $stmt->execute([$userId]);
    $sessions = $stmt->fetchAll();
    $currentSelector = '';
    if (isset($_COOKIE['auth_persistence_token'])) {
        $parts = explode(':', $_COOKIE['auth_persistence_token']);
        if (count($parts) === 2) $currentSelector = $parts[0];
    }
    $formatted = [];
    foreach($sessions as $s) {
        $info = parseUserAgent($s['user_agent'] ?? '');
        $isCurrent = ($s['selector'] === $currentSelector);
        $formatted[] = [
            'id' => $s['id'],
            'ip' => $s['ip_address'] ?? 'Desconocida',
            'platform' => $info['platform'],
            'browser' => $info['browser'],
            'created_at' => $s['created_at'],
            'is_current' => $isCurrent
        ];
    }
    jsonResponse(true, $i18n->t('api.sessions_list'), ['sessions' => $formatted]);

} elseif ($action === 'revoke_session') {
    $tokenId = $_POST['token_id'] ?? '';
    if(!$tokenId) jsonResponse(false, $i18n->t('api.id_invalid'));
    $stmt = $pdo->prepare("DELETE FROM user_auth_tokens WHERE id = ? AND user_id = ?");
    $stmt->execute([$tokenId, $userId]);
    if ($stmt->rowCount() > 0) { jsonResponse(true, $i18n->t('api.session_revoked')); } else { jsonResponse(false, $i18n->t('api.session_revoke_error')); }

} elseif ($action === 'revoke_all_sessions') {
    $stmt = $pdo->prepare("DELETE FROM user_auth_tokens WHERE user_id = ?");
    $stmt->execute([$userId]);
    setcookie('auth_persistence_token', '', time() - 3600, '/', '', isset($_SERVER['HTTPS']), true);
    jsonResponse(true, $i18n->t('api.all_sessions_revoked'), ['logout' => true]);

} elseif ($action === 'delete_account') {
    $password = $_POST['password'] ?? '';
    if (empty($password)) { jsonResponse(false, $i18n->t('api.pass_req_confirm')); }
    $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    if (!$user || !password_verify($password, $user['password'])) { jsonResponse(false, $i18n->t('api.pass_incorrect')); }
    try {
        $pdo->beginTransaction();
        $update = $pdo->prepare("UPDATE users SET account_status = 'deleted' WHERE id = ?");
        $update->execute([$userId]);
        $deleteTokens = $pdo->prepare("DELETE FROM user_auth_tokens WHERE user_id = ?");
        $deleteTokens->execute([$userId]);
        $pdo->commit();
        setcookie('auth_persistence_token', '', time() - 3600, '/', '', isset($_SERVER['HTTPS']), true);
        session_destroy();
        jsonResponse(true, $i18n->t('api.account_deleted_success'));
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Error deleting account: " . $e->getMessage());
        jsonResponse(false, $i18n->t('api.internal_error'));
    }
}

jsonResponse(false, $i18n->t('api.unknown_action'));
?>