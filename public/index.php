<?php
// public/index.php
session_start();

// Cargar Router, DB y el sistema I18n
require_once __DIR__ . '/../config/routers/router.php';
require_once __DIR__ . '/../config/database/db.php';
require_once __DIR__ . '/../includes/libs/I18n.php';

// === MIDDLEWARE: AUTO-LOGIN POR COOKIE (TOKEN ROTATIVO) ===
if (!isset($_SESSION['user_id']) && isset($_COOKIE['auth_persistence_token'])) {

    $parts = explode(':', $_COOKIE['auth_persistence_token']);

    if (count($parts) === 2) {
        $selector = $parts[0];
        $validator = $parts[1];

        $stmt = $pdo->prepare("SELECT * FROM user_auth_tokens WHERE selector = ? AND expires_at > NOW() LIMIT 1");
        $stmt->execute([$selector]);
        $authToken = $stmt->fetch();

        if ($authToken) {
            if (hash_equals($authToken['hashed_validator'], hash('sha256', $validator))) {

                $stmtUser = $pdo->prepare("SELECT * FROM users WHERE id = ?");
                $stmtUser->execute([$authToken['user_id']]);
                $user = $stmtUser->fetch();

                if ($user) {
                    session_regenerate_id(true);

                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['role'] = $user['role'];
                    $_SESSION['avatar'] = $user['avatar_path'];
                    $_SESSION['email'] = $user['email'];

                    // Cargar preferencias
                    $prefStmt = $pdo->prepare("SELECT language, open_links_new_tab, theme, extended_toast FROM user_preferences WHERE user_id = ?");
                    $prefStmt->execute([$user['id']]);
                    $prefs = $prefStmt->fetch();
                    $_SESSION['preferences'] = $prefs ? [
                        'language' => $prefs['language'],
                        'open_links_new_tab' => (bool)$prefs['open_links_new_tab'],
                        'theme' => $prefs['theme'],
                        'extended_toast' => (bool)$prefs['extended_toast']
                    ] : ['language' => 'es-latam', 'open_links_new_tab' => true, 'theme' => 'sync', 'extended_toast' => false];

                    // Rotación de token
                    $pdo->prepare("DELETE FROM user_auth_tokens WHERE id = ?")->execute([$authToken['id']]);

                    $newSelector = bin2hex(random_bytes(12));
                    $newValidator = bin2hex(random_bytes(32));
                    $newHashedValidator = hash('sha256', $newValidator);
                    $newExpires = date('Y-m-d H:i:s', time() + (86400 * 30));

                    $ins = $pdo->prepare("INSERT INTO user_auth_tokens (user_id, selector, hashed_validator, expires_at) VALUES (?, ?, ?, ?)");
                    $ins->execute([$user['id'], $newSelector, $newHashedValidator, $newExpires]);

                    $isSecure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
                    setcookie('auth_persistence_token', "$newSelector:$newValidator", [
                        'expires' => time() + (86400 * 30),
                        'path' => '/',
                        'domain' => '',
                        'secure' => $isSecure,
                        'httponly' => true,
                        'samesite' => 'Strict'
                    ]);
                }
            }
        }
    }
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$isLoggedIn = isset($_SESSION['user_id']);

// === SISTEMA DE TRADUCCIÓN (INICIALIZACIÓN) ===
$userLang = $_SESSION['preferences']['language'] ?? 'es-latam';
$i18n = new I18n($userLang);
// ==============================================

$publicRoutes = [
    'login',
    'register',
    'register/aditional-data',
    'register/verification-account',
    'recover-password',
    'reset-password'
];

if (!$isLoggedIn) {
    if (!in_array($currentSection, $publicRoutes)) {
        header("Location: " . $basePath . "login");
        exit;
    }
} else {
    if (in_array($currentSection, $publicRoutes)) {
        header("Location: " . $basePath);
        exit;
    }
}

$globalAvatarSrc = '';
$userRole = 'guest';

if ($isLoggedIn) {
    try {
        if (isset($pdo)) {
            $stmt = $pdo->prepare("SELECT role, avatar_path, username, email, two_factor_enabled FROM users WHERE id = ? LIMIT 1");
            $stmt->execute([$_SESSION['user_id']]);
            $freshUser = $stmt->fetch();

            if ($freshUser) {
                $_SESSION['role'] = $freshUser['role'];
                $_SESSION['avatar'] = $freshUser['avatar_path'];
                $_SESSION['username'] = $freshUser['username'];
                $_SESSION['email'] = $freshUser['email'];
                $_SESSION['two_factor_enabled'] = $freshUser['two_factor_enabled'];
            }
        }
    } catch (Exception $e) {
        error_log("Error al refrescar sesión: " . $e->getMessage());
    }

    $userRole = $_SESSION['role'] ?? 'user';

    if (!empty($_SESSION['avatar'])) {
        $avatarFile = __DIR__ . '/../' . $_SESSION['avatar'];
        if (file_exists($avatarFile)) {
            $mimeType = mime_content_type($avatarFile);
            $data = file_get_contents($avatarFile);
            $globalAvatarSrc = 'data:' . $mimeType . ';base64,' . base64_encode($data);
        }
    }

    if (empty($globalAvatarSrc)) {
        $name = $_SESSION['username'] ?? 'User';
        $globalAvatarSrc = "https://ui-avatars.com/api/?name=" . urlencode($name) . "&background=random&color=fff";
    }
}

$routesMap = require __DIR__ . '/../config/routes.php';
$fileToLoad = $routesMap[$currentSection] ?? $routesMap['404'];

?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($userLang); ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $i18n->t('app.name'); ?></title>

    <meta name="csrf-token" content="<?php echo $_SESSION['csrf_token']; ?>">
    <script>
        window.BASE_PATH = '<?php echo $basePath; ?>';
        window.USER_PREFS = <?php echo json_encode($_SESSION['preferences'] ?? new stdClass()); ?>;
        
        // Inyectamos el objeto JSON completo (anidado) para que el JS lo consuma
        window.TRANSLATIONS = <?php echo json_encode($i18n->getAll()); ?>;
    </script>

    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded" />

    <link rel="stylesheet" type="text/css" href="<?php echo $basePath; ?>public/assets/css/styles.css">

    <link rel="stylesheet" type="text/css" href="<?php echo $basePath; ?>public/assets/css/components.css">

    <script type="module" src="<?php echo $basePath; ?>public/assets/js/app-init.js"></script>
</head>

<body>

    <div class="page-wrapper">
        <div class="main-content">
            <div class="general-content">

                <?php if ($isLoggedIn): ?>
                    <div class="general-content-top">
                        <?php
                        include __DIR__ . '/../includes/layouts/header.php';
                        ?>
                    </div>
                <?php endif; ?>

                <div class="general-content-bottom">
                    <?php if ($isLoggedIn): ?>
                        <?php include __DIR__ . '/../includes/modules/module-surface.php'; ?>
                    <?php endif; ?>

                    <div class="general-content-scrolleable" data-container="main-section">
                        <?php
                        if (file_exists($fileToLoad)) {
                            // $i18n está disponible aquí por el include superior
                            include $fileToLoad;
                        } else {
                            echo $i18n->t('errors.file_not_found');
                        }
                        ?>
                    </div>
                </div>

            </div>
        </div>
    </div>

</body>

</html>