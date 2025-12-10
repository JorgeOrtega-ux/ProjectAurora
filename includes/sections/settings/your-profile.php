<?php
// includes/sections/settings/your-profile.php

if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../db.php'; 

// 1. Obtener datos frescos
$currentUser = [];
$userId = $_SESSION['user_id'] ?? 0;

if ($userId) {
    try {
        $stmt = $pdo->prepare("SELECT username, email, role, uuid FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $userDB = $stmt->fetch();
        if ($userDB) {
            $currentUser = $userDB;
            $currentUser['language'] = 'es-419';
            $currentUser['open_new_tab'] = 1;
        }
    } catch (Exception $e) {}
}

if (empty($currentUser)) {
    // Fallback
    $currentUser = [
        'username' => $_SESSION['username'] ?? 'Usuario',
        'email'    => 'No disponible',
        'role'     => $_SESSION['role'] ?? 'user',
        'uuid'     => $_SESSION['uuid'] ?? '',
        'language' => 'es-419'
    ];
}

// 2. LÓGICA DE AVATAR (ESTRATEGIA 2 CARPETAS DENTRO DE UPLOADS)
$hasCustomAvatar = false;
$finalAvatarSrc = '';

if (!empty($currentUser['uuid'])) {
    $uuid = $currentUser['uuid'];
    
    // Rutas relativas para el navegador
    $relUploadPath  = 'assets/uploads/profile_pictures/' . $uuid . '.png';
    $relDefaultPath = 'assets/uploads/default_avatars/' . $uuid . '.png';
    
    // Rutas físicas para comprobación
    $absUploadPath  = __DIR__ . '/../../../public/' . $relUploadPath;
    $absDefaultPath = __DIR__ . '/../../../public/' . $relDefaultPath;

    // Verificar si existe en UPLOADS/PROFILE_PICTURES (Prioridad)
    if (file_exists($absUploadPath)) {
        $hasCustomAvatar = true;
        $finalAvatarSrc = (isset($basePath) ? $basePath : '/ProjectAurora/') . $relUploadPath . '?v=' . time();
    } else {
        // Si no está subida, usamos la DEFAULT (si existe en default_avatars)
        if (file_exists($absDefaultPath)) {
            $finalAvatarSrc = (isset($basePath) ? $basePath : '/ProjectAurora/') . $relDefaultPath . '?v=' . time();
        } else {
            // Fallback extremo (UI Avatars directo)
            $finalAvatarSrc = 'https://ui-avatars.com/api/?name=' . urlencode($currentUser['username']) . '&background=random&color=fff&size=128';
        }
    }
} else {
    $finalAvatarSrc = 'https://ui-avatars.com/api/?name=User&background=random&color=fff';
}

$langLabels = ['es-419' => 'Español (Latinoamérica)'];
$currentLangLabel = $langLabels['es-419'];
?>

<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@24,400,1,0" />
<link href="https://fonts.googleapis.com/css2?family=Roboto+Condensed:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet">

<style>
    /* Estilos base */
    .component-wrapper * { box-sizing: border-box; font-family: "Roboto Condensed", sans-serif; }
    .component-wrapper { width: 100%; max-width: 700px; margin: 0 auto; padding: 16px; display: flex; flex-direction: column; gap: 16px; }
    .component-header-card, .component-card { border: 1px solid #00000020; border-radius: 12px; padding: 24px; background-color: #ffffff; }
    .component-header-card { text-align: center; }
    .component-page-title { font-size: 24px; font-weight: 700; margin: 0 0 8px 0; color: #000; }
    .component-page-description { font-size: 15px; color: #666; margin: 0; }
    .component-card--grouped { display: flex; flex-direction: column; padding: 0; gap: 0; overflow: hidden; }
    .component-group-item { display: flex; flex-direction: row; align-items: center; justify-content: space-between; flex-wrap: wrap; padding: 24px; background-color: transparent; gap: 16px; }
    .component-divider { border: 0; border-top: 1px solid #00000015; width: 100%; margin: 0; }
    .component-card__content { flex: 1 1 auto; min-width: 0; display: flex; align-items: center; gap: 20px; }
    .component-card__text { display: flex; flex-direction: column; gap: 4px; width: 100%; }
    .component-card__title { font-size: 15px; font-weight: 600; margin: 0; color: #000; }
    .component-card__description { font-size: 14px; color: #666; margin: 0; line-height: 1.4; }
    
    /* PFP Styles */
    .component-card__profile-picture { width: 56px; height: 56px; border-radius: 50%; background-color: #f5f5f5; position: relative; display: flex; align-items: center; justify-content: center; flex-shrink: 0; overflow: hidden; }
    .component-card__profile-picture::before { content: ''; position: absolute; top: 0; left: 0; right: 0; bottom: 0; border-radius: 50%; border: 2px solid transparent; pointer-events: none; z-index: 2; }
    .component-card__profile-picture[data-role="user"]::before { border-color: #cccccc; }
    .component-card__profile-picture[data-role="moderator"]::before { border-color: #0000FF; }
    .component-card__profile-picture[data-role="administrator"]::before { border-color: #FF0000; }
    .component-card__profile-picture[data-role="founder"]::before { border: none; background-image: conic-gradient(from 300deg, #D32029 0deg 90deg, #206BD3 90deg 210deg, #28A745 210deg 300deg, #FFC107 300deg 360deg); mask: radial-gradient(farthest-side, transparent calc(100% - 2px), #fff 0); -webkit-mask: radial-gradient(farthest-side, transparent calc(100% - 2px), #fff 0); }
    .component-card__avatar-image { width: 100%; height: 100%; object-fit: cover; border-radius: 50%; }
    
    /* Hover Overlay */
    .component-card__avatar-overlay { position: absolute; inset: 0; background-color: rgba(0, 0, 0, 0.4); display: flex; align-items: center; justify-content: center; color: #fff; opacity: 0; transition: opacity 0.2s; cursor: pointer; border-radius: 50%; z-index: 3; }
    .component-card__profile-picture:hover .component-card__avatar-overlay { opacity: 1; }

    /* Buttons */
    .component-card__actions { display: flex; align-items: center; gap: 8px; flex-shrink: 0; }
    .component-card__actions.actions-right { justify-content: flex-end; }
    .component-button { height: 36px; padding: 0 14px; border-radius: 8px; font-size: 13px; font-weight: 500; cursor: pointer; background: transparent; border: 1px solid #00000020; color: #000; display: flex; align-items: center; justify-content: center; gap: 6px; transition: all 0.2s ease; white-space: nowrap; }
    .component-button:hover { background-color: #f5f5fa; }
    .component-button.primary { background-color: #000; color: #fff; border: none; }
    .component-button.primary:hover { background-color: #333; }
    .component-button.danger { border-color: #ffcdd2; color: #d32f2f; }
    .component-button.danger:hover { background-color: #ffebee; }
    .component-text-input { width: 100%; height: 36px; padding: 0 12px; border: 1px solid #00000020; border-radius: 8px; font-size: 14px; outline: none; background: transparent; color: #000; }
    .component-input-wrapper { width: 100%; min-width: 200px; }
    
    .active { display: flex !important; }
    .disabled { display: none !important; }
    .material-symbols-rounded { font-size: 20px; }
    .component-badge { font-size: 13px; font-weight: 500; color: #333; }
    
    @media (max-width: 600px) {
        .component-group-item { flex-direction: column; align-items: flex-start; gap: 12px; }
        .component-card__actions { width: 100%; justify-content: flex-end; margin-top: 4px; }
        .component-input-wrapper { width: 100%; }
    }
</style>

<div class="section-content active" data-section="settings/your-profile">
    <div class="component-wrapper">

        <div class="component-header-card">
            <h1 class="component-page-title">Tu Perfil</h1>
            <p class="component-page-description">Gestiona tu identidad y preferencias personales.</p>
        </div>

        <div class="component-card component-card--grouped">

            <div class="component-group-item" data-component="profile-picture-section" data-has-custom="<?php echo $hasCustomAvatar ? 'true' : 'false'; ?>">
                <div class="component-card__content">
                    <div class="component-card__profile-picture" data-role="<?php echo htmlspecialchars($currentUser['role']); ?>">
                        <img src="<?php echo htmlspecialchars($finalAvatarSrc); ?>" 
                             class="component-card__avatar-image" 
                             data-element="profile-picture-preview-image">
                        <div class="component-card__avatar-overlay" data-action="trigger-profile-picture-upload">
                            <span class="material-symbols-rounded">edit</span>
                        </div>
                    </div>
                    <div class="component-card__text">
                        <h2 class="component-card__title">Foto de Perfil</h2>
                        <p class="component-card__description">Visible para todos.</p>
                    </div>
                </div>
                
                <input type="file" accept="image/png, image/jpeg, image/webp" hidden data-element="profile-picture-upload-input">

                <div class="component-card__actions actions-right">
                    
                    <div class="active" data-state="profile-picture-actions-default">
                        <button type="button" class="component-button danger" 
                                data-action="profile-picture-remove-trigger"
                                style="<?php echo $hasCustomAvatar ? '' : 'display:none;'; ?>">
                            <span class="material-symbols-rounded">delete</span>
                        </button>
                        
                        <button type="button" class="component-button primary" data-action="profile-picture-upload-trigger">
                            <span class="material-symbols-rounded">upload</span> 
                            <span data-element="upload-btn-text"><?php echo $hasCustomAvatar ? 'Cambiar' : 'Subir foto'; ?></span>
                        </button>
                    </div>

                    <div class="disabled" data-state="profile-picture-actions-preview">
                        <button type="button" class="component-button" data-action="profile-picture-cancel-trigger">Cancelar</button>
                        <button type="button" class="component-button primary" data-action="profile-picture-save-trigger-btn">Guardar</button>
                    </div>
                </div>
            </div>

            <hr class="component-divider">

            <div class="component-group-item" data-component="username-section">
                <div class="component-card__content">
                    <div class="component-card__text">
                        <h2 class="component-card__title">Nombre de usuario</h2>
                        <div class="active" data-state="username-view-state" style="margin-top: 4px;">
                            <span class="component-badge" data-element="username-display-text">
                                @<?php echo htmlspecialchars($currentUser['username']); ?>
                            </span>
                        </div>
                        <div class="disabled w-100" data-state="username-edit-state" style="margin-top: 8px;">
                            <div class="component-input-wrapper">
                                <input type="text" class="component-text-input" 
                                       value="<?php echo htmlspecialchars($currentUser['username']); ?>" 
                                       data-element="username-input">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="component-card__actions actions-right">
                    <div class="active" data-state="username-actions-view">
                        <button type="button" class="component-button" data-action="username-edit-trigger">Editar</button>
                    </div>
                    <div class="disabled" data-state="username-actions-edit">
                        <button type="button" class="component-button" data-action="username-cancel-trigger">Cancelar</button>
                        <button type="button" class="component-button primary" data-action="username-save-trigger-btn">Guardar</button>
                    </div>
                </div>
            </div>

            <hr class="component-divider">

            <div class="component-group-item" data-component="email-section">
                <div class="component-card__content">
                    <div class="component-card__text">
                        <h2 class="component-card__title">Correo electrónico</h2>
                        <div class="active" data-state="email-view-state" style="margin-top: 4px;">
                            <span style="font-size: 13px; color: #333;" data-element="email-display-text">
                                <?php echo htmlspecialchars($currentUser['email']); ?>
                            </span>
                        </div>
                        <div class="disabled w-100" data-state="email-edit-state" style="margin-top: 8px;">
                            <div class="component-input-wrapper">
                                <input type="email" class="component-text-input" 
                                       value="<?php echo htmlspecialchars($currentUser['email']); ?>" 
                                       data-element="email-input">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="component-card__actions actions-right">
                    <div class="active" data-state="email-actions-view">
                        <button type="button" class="component-button" data-action="email-edit-trigger">Editar</button>
                    </div>
                    <div class="disabled" data-state="email-actions-edit">
                        <button type="button" class="component-button" data-action="email-cancel-trigger">Cancelar</button>
                        <button type="button" class="component-button primary" data-action="email-save-trigger-btn">Guardar</button>
                    </div>
                </div>
            </div>

        </div>

        <div class="component-card component-card--grouped">
            <div class="component-group-item">
                <div class="component-card__content">
                    <div class="component-card__text">
                        <h2 class="component-card__title">Idioma</h2>
                        <p class="component-card__description">Español (Latinoamérica)</p>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>