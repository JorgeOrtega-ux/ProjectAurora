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
        }
    } catch (Exception $e) {}
}

if (empty($currentUser)) {
    $currentUser = [
        'username' => $_SESSION['username'] ?? 'Usuario',
        'email'    => 'No disponible',
        'role'     => $_SESSION['role'] ?? 'user',
        'uuid'     => $_SESSION['uuid'] ?? '',
        'language' => 'es-419'
    ];
}

// 2. LÓGICA DE AVATAR
$hasCustomAvatar = false;
$finalAvatarSrc = '';

if (!empty($currentUser['uuid'])) {
    $uuid = $currentUser['uuid'];
    $relCustom  = 'assets/uploads/avatars/custom/' . $uuid . '.png';
    $relDefault = 'assets/uploads/avatars/default/' . $uuid . '.png';
    $absCustom  = __DIR__ . '/../../../public/' . $relCustom;
    
    if (file_exists($absCustom)) {
        $hasCustomAvatar = true;
        $finalAvatarSrc = (isset($basePath) ? $basePath : '/ProjectAurora/') . $relCustom . '?v=' . microtime(true);
    } else {
        $finalAvatarSrc = (isset($basePath) ? $basePath : '/ProjectAurora/') . $relDefault . '?v=' . microtime(true);
    }
} else {
    $finalAvatarSrc = ''; 
}
?>

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
                        
                        <div class="active" data-state="username-view-state">
                            <span style="font-size: 13px; color: #333;" data-element="username-display-text">
                                <?php echo htmlspecialchars($currentUser['username']); ?>
                            </span>
                        </div>
                        
                        <div class="disabled w-100 input-group-responsive" data-state="username-edit-state">
                            
                            <div class="component-input-wrapper" style="flex: 1;">
                                <input type="text" class="component-text-input" 
                                       value="<?php echo htmlspecialchars($currentUser['username']); ?>" 
                                       data-element="username-input">
                            </div>

                            <div class="component-card__actions disabled" data-state="username-actions-edit" style="margin: 0;">
                                <button type="button" class="component-button" data-action="username-cancel-trigger">Cancelar</button>
                                <button type="button" class="component-button primary" data-action="username-save-trigger-btn">Guardar</button>
                            </div>

                        </div>
                    </div>
                </div>
                
                <div class="component-card__actions actions-right">
                    <div class="active" data-state="username-actions-view">
                        <button type="button" class="component-button" data-action="username-edit-trigger">Editar</button>
                    </div>
                </div>
            </div>

            <hr class="component-divider">

            <div class="component-group-item" data-component="email-section">
                <div class="component-card__content">
                    <div class="component-card__text">
                        <h2 class="component-card__title">Correo electrónico</h2>
                        
                        <div class="active" data-state="email-view-state">
                            <span style="font-size: 13px; color: #333;" data-element="email-display-text">
                                <?php echo htmlspecialchars($currentUser['email']); ?>
                            </span>
                        </div>

                        <div class="disabled w-100 input-group-responsive" data-state="email-edit-state">
                            
                            <div class="component-input-wrapper" style="flex: 1;">
                                <input type="email" class="component-text-input" 
                                       value="<?php echo htmlspecialchars($currentUser['email']); ?>" 
                                       data-element="email-input">
                            </div>

                            <div class="component-card__actions disabled" data-state="email-actions-edit" style="margin: 0;">
                                <button type="button" class="component-button" data-action="email-cancel-trigger">Cancelar</button>
                                <button type="button" class="component-button primary" data-action="email-save-trigger-btn">Guardar</button>
                            </div>

                        </div>
                    </div>
                </div>

                <div class="component-card__actions actions-right">
                    <div class="active" data-state="email-actions-view">
                        <button type="button" class="component-button" data-action="email-edit-trigger">Editar</button>
                    </div>
                </div>
            </div>

        </div>

        <div class="component-card component-card--grouped">
            
            <div class="component-group-item component-group-item--stacked">
                <div class="component-card__content">
                    <div class="component-card__text">
                        <h2 class="component-card__title">Idioma</h2>
                        <p class="component-card__description">Selecciona el idioma de la interfaz.</p>
                    </div>
                </div>
                <div class="component-card__actions">
                    <div class="trigger-select-wrapper" data-ui-type="dropdown" data-align="left">
                        <div class="trigger-selector" data-action="toggle-dropdown">
                            <span class="material-symbols-rounded trigger-select-icon">language</span>
                            <span class="trigger-select-text">Español (Latinoamérica)</span>
                            <span class="material-symbols-rounded">expand_more</span>
                        </div>
                        <div class="popover-module">
                            <div class="menu-list">
                                <div class="menu-link active">
                                    <div class="menu-link-icon">
                                        <span class="material-symbols-rounded">language</span>
                                    </div>
                                    <div class="menu-link-text">Español (Latinoamérica)</div>
                                </div>
                                <div class="menu-link">
                                    <div class="menu-link-icon">
                                        <span class="material-symbols-rounded">translate</span>
                                    </div>
                                    <div class="menu-link-text">English (US)</div>
                                </div>
                                <div class="menu-link">
                                    <div class="menu-link-icon">
                                        <span class="material-symbols-rounded">public</span>
                                    </div>
                                    <div class="menu-link-text">Português (Brasil)</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <hr class="component-divider">

            <div class="component-group-item component-group-item--stacked">
                <div class="component-card__content">
                    <div class="component-card__text">
                        <h2 class="component-card__title">¿Para qué usarás esta web?</h2>
                        <p class="component-card__description">Nos ayuda a personalizar tu experiencia.</p>
                    </div>
                </div>
                <div class="component-card__actions">
                    <div class="trigger-select-wrapper" data-ui-type="dropdown" data-align="left">
                        <div class="trigger-selector" data-action="toggle-dropdown">
                            <span class="material-symbols-rounded trigger-select-icon">person</span>
                            <span class="trigger-select-text">Uso Personal</span>
                            <span class="material-symbols-rounded">expand_more</span>
                        </div>
                        <div class="popover-module">
                            <div class="menu-list">
                                <div class="menu-link active">
                                    <div class="menu-link-icon">
                                        <span class="material-symbols-rounded">person</span>
                                    </div>
                                    <div class="menu-link-text">Uso Personal</div>
                                </div>
                                <div class="menu-link">
                                    <div class="menu-link-icon">
                                        <span class="material-symbols-rounded">groups</span>
                                    </div>
                                    <div class="menu-link-text">Trabajo / Equipo</div>
                                </div>
                                <div class="menu-link">
                                    <div class="menu-link-icon">
                                        <span class="material-symbols-rounded">school</span>
                                    </div>
                                    <div class="menu-link-text">Educación</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <hr class="component-divider">

            <div class="component-group-item">
                <div class="component-card__content">
                    <div class="component-card__text">
                        <h2 class="component-card__title">Abrir enlaces en pestaña nueva</h2>
                        <p class="component-card__description">Los enlaces externos se abrirán en otra ventana.</p>
                    </div>
                </div>
                <div class="component-card__actions actions-right">
                    <label class="component-toggle-switch">
                        <input type="checkbox" checked>
                        <span class="component-toggle-slider"></span>
                    </label>
                </div>
            </div>

        </div>

    </div>
</div>