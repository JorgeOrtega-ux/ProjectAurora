<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$isLoggedIn = isset($_SESSION['user_id']);
$avatar = $isLoggedIn ? $_SESSION['user_avatar'] : '';

// Lógica simple: Definimos el estilo inicial desde el servidor
$styleGuest = $isLoggedIn ? 'display: none !important;' : 'display: flex !important;';
$styleUser  = $isLoggedIn ? 'display: flex !important;' : 'display: none !important;';
?>

<div class="header">
    <div class="header-left">
        <div class="component-actions">
            <button class="component-button component-button--square-40" data-action="toggleModuleSurface">
                <span class="material-symbols-rounded">menu</span>
            </button>
        </div>
    </div>

    <div class="header-center">
        <div class="component-search">
            <div class="component-search-icon">
                <span class="material-symbols-rounded">search</span>
            </div>
            <div class="component-search-input">
                <input type="text" class="component-search-input-field" placeholder="Buscar...">
            </div>
        </div>
    </div>

   <div class="header-right">
        <div class="component-actions">
            <button class="component-button component-button--square-40 mobile-search-trigger">
                <span class="material-symbols-rounded">search</span>
            </button>

            <div class="auth-guest-actions" style="<?php echo $styleGuest; ?> gap: 8px;">
                <button class="component-button component-button--black component-button--rect-40" data-nav="/ProjectAurora/login">
                    Acceder
                </button>
                <button class="component-button component-button--square-40" data-action="toggleModuleMainOptions">
                    <span class="material-symbols-rounded">more_vert</span>
                </button>
            </div>

            <div class="auth-user-actions" style="<?php echo $styleUser; ?>">
                <button class="component-button component-button--square-40" 
                        data-action="toggleModuleMainOptions" 
                        style="padding: 0; overflow: hidden; border-radius: 50%; border: 2px solid #eee;">
                    <img id="user-avatar-img" src="<?php echo htmlspecialchars($avatar); ?>" alt="Perfil" style="width: 100%; height: 100%; object-fit: cover;">
                </button>
            </div>

        </div>
    </div>

    <?php include __DIR__ . '/../modules/moduleMainOptions.php'; ?>
</div>