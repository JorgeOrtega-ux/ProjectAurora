<?php
if (session_status() === PHP_SESSION_NONE) session_start();
$isLoggedIn = isset($_SESSION['user_id']);
$avatar = $isLoggedIn ? $_SESSION['user_avatar'] : '';
$role = $isLoggedIn && isset($_SESSION['user_role']) ? $_SESSION['user_role'] : 'user';
$styleGuest = $isLoggedIn ? 'display: none !important;' : 'display: flex !important;';
$styleUser  = $isLoggedIn ? 'display: flex !important;' : 'display: none !important;';
?>

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
                <button class="component-button component-button--black component-button--rect-40" onclick="window.location.href='/ProjectAurora/login'">
                    <?= t('header.login') ?>
                </button>
                <button class="component-button component-button--square-40" data-action="toggleModuleMainOptions" data-tooltip="Opciones de cuenta">
                    <span class="material-symbols-rounded">more_vert</span>
                </button>
            </div>
            
            <div class="auth-user-actions" style="<?= $styleUser; ?>">
                <button class="component-button component-button--square-40 user-avatar-btn" data-action="toggleModuleMainOptions" data-role="<?= htmlspecialchars($role); ?>" data-tooltip="Cuenta y configuración" style="padding: 0; border: none; background: transparent;">
                    <img id="user-avatar-img" src="<?= htmlspecialchars($avatar); ?>" alt="<?= t('header.profile_alt') ?>" style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;">
                </button>
            </div>
        </div>
    </div>
    <?php include __DIR__ . '/../modules/moduleMainOptions.php'; ?>
</div>