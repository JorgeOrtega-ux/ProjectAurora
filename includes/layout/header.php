<?php
if (session_status() === PHP_SESSION_NONE) session_start();
$isLoggedIn = isset($_SESSION['user_id']);
$avatar = $isLoggedIn ? $_SESSION['user_avatar'] : '';
$role = $isLoggedIn && isset($_SESSION['user_role']) ? $_SESSION['user_role'] : 'user';
$styleGuest = $isLoggedIn ? 'display: none !important;' : 'display: flex !important;';
$styleUser  = $isLoggedIn ? 'display: flex !important;' : 'display: none !important;';
$appUrl = rtrim(getenv('APP_URL') ?: '/ProjectAurora', '/');
?>

<script>
    // Pasamos las traducciones exactas del backend al cliente para el Router SPA
    // Usamos el namespace "title." para separar los nombres de las pestañas de los de la UI.
    window.AppRouteTitles = {
        '/': "<?= htmlspecialchars(t('title.home'), ENT_QUOTES, 'UTF-8') ?>",
        '/explore': "<?= htmlspecialchars(t('title.explore'), ENT_QUOTES, 'UTF-8') ?>",
        '/login': "<?= htmlspecialchars(t('title.login'), ENT_QUOTES, 'UTF-8') ?>",
        '/register': "<?= htmlspecialchars(t('title.register'), ENT_QUOTES, 'UTF-8') ?>",
        '/forgot-password': "<?= htmlspecialchars(t('title.forgot'), ENT_QUOTES, 'UTF-8') ?>",
        '/reset-password': "<?= htmlspecialchars(t('title.reset'), ENT_QUOTES, 'UTF-8') ?>",
        '/settings/your-account': "<?= htmlspecialchars(t('title.profile'), ENT_QUOTES, 'UTF-8') ?>",
        '/settings/security': "<?= htmlspecialchars(t('title.security'), ENT_QUOTES, 'UTF-8') ?>",
        '/settings/accessibility': "<?= htmlspecialchars(t('title.accessibility'), ENT_QUOTES, 'UTF-8') ?>",
        '/settings/devices': "<?= htmlspecialchars(t('title.devices'), ENT_QUOTES, 'UTF-8') ?>",
        '/settings/delete-account': "<?= htmlspecialchars(t('title.delete_account'), ENT_QUOTES, 'UTF-8') ?>",
        '/settings/guest': "<?= htmlspecialchars(t('title.guest'), ENT_QUOTES, 'UTF-8') ?>",
        '/admin/dashboard': "<?= htmlspecialchars(t('title.admin_dashboard'), ENT_QUOTES, 'UTF-8') ?>",
        '/admin/users': "<?= htmlspecialchars(t('title.admin_users'), ENT_QUOTES, 'UTF-8') ?>",
        '/admin/backups': "<?= htmlspecialchars(t('title.admin_backups'), ENT_QUOTES, 'UTF-8') ?>",
        '/admin/server': "<?= htmlspecialchars(t('title.admin_server'), ENT_QUOTES, 'UTF-8') ?>"
    };
    window.AppName = "ProjectAurora";
</script>

<div class="header">
    <div class="header-left">
        <div class="component-actions">
            <button class="component-button component-button--square-40" data-action="toggleModuleSurface" data-tooltip="Menú de navegación">
                <span class="material-symbols-rounded">menu</span>
            </button>
        </div>
    </div>
    <div class="header-center">
        <div class="component-search">
            <div class="component-search-icon"><span class="material-symbols-rounded">search</span></div>
            <div class="component-search-input">
                <input type="text" class="component-search-input-field" placeholder="<?= t('header.search_placeholder') ?>">
            </div>
        </div>
    </div>
   <div class="header-right">
        <div class="component-actions">
            <button class="component-button component-button--square-40 mobile-search-trigger" data-tooltip="Buscar">
                <span class="material-symbols-rounded">search</span>
            </button>
            
            <div class="auth-guest-actions" style="<?= $styleGuest; ?> gap: 8px;">
                <button class="component-button component-button--black component-button--rect-40" onclick="window.location.href='<?= htmlspecialchars($appUrl) ?>/login'">
                    <?= t('header.login') ?>
                </button>
                <button class="component-button component-button--square-40" data-action="toggleModuleMainOptions" data-tooltip="Opciones de cuenta">
                    <span class="material-symbols-rounded">more_vert</span>
                </button>
            </div>
            
            <div class="auth-user-actions" style="<?= $styleUser; ?>">
                <button class="component-button component-button--square-40 component-avatar component-avatar--sm" data-action="toggleModuleMainOptions" data-role="<?= htmlspecialchars($role); ?>" data-tooltip="Cuenta y configuración" style="padding: 0; border: none; background: transparent;">
                    <img id="user-avatar-img" src="<?= htmlspecialchars($avatar); ?>" class="component-avatar__image" alt="<?= t('header.profile_alt') ?>">
                </button>
            </div>
        </div>
    </div>
    <?php include __DIR__ . '/../modules/moduleMainOptions.php'; ?>
</div>