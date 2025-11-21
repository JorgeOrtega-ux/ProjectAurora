<?php
if (session_status() === PHP_SESSION_NONE) session_start();

$basePath = $basePath ?? '/ProjectAurora/';
$userId = $_SESSION['user_id'];

// 1. Obtener datos
$stmt = $pdo->prepare("SELECT username, avatar, role FROM users WHERE id = ?");
$stmt->execute([$userId]);
$currentUser = $stmt->fetch();

$currentUsername = $currentUser['username'] ?? 'Usuario';
$userAvatar = $currentUser['avatar'] ?? null;
$userRole = $currentUser['role'] ?? 'user';

// URL del avatar
$avatarUrl = null;
if ($userAvatar && !empty($userAvatar)) {
    $avatarUrl = $basePath . $userAvatar . '?t=' . time();
}

// Detectar si es avatar default
$isDefaultAvatar = false;
if (empty($userAvatar) || strpos($userAvatar, '/default/') !== false) {
    $isDefaultAvatar = true;
}
$hasCustomAvatar = !$isDefaultAvatar && ($avatarUrl !== null);
?>

<div class="section-content overflow-y active" data-section="settings/your-profile">
    <div class="component-wrapper">
        
        <div class="component-header-card">
            <h1 class="component-page-title">Tu Perfil</h1>
            <p class="component-page-description">Aquí podrás editar tu información de perfil y personalizar tu avatar.</p>
        </div>

        <div class="component-card component-card--edit-mode" id="avatar-section">
            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
            <input type="file" class="visually-hidden" id="avatar-upload-input" name="avatar" accept="image/png, image/jpeg, image/gif, image/webp">

            <div class="component-card__content">
                <div class="component-card__avatar" id="avatar-preview-container" data-role="<?php echo htmlspecialchars($userRole); ?>">
                    <?php if ($avatarUrl): ?>
                        <img src="<?php echo htmlspecialchars($avatarUrl); ?>" alt="Avatar" class="component-card__avatar-image" id="avatar-preview-image">
                    <?php else: ?>
                        <img src="" alt="Sin avatar" class="component-card__avatar-image" id="avatar-preview-image" style="display: none;">
                        <span class="material-symbols-rounded default-avatar-icon" style="font-size: 32px; color: #999;">person</span>
                    <?php endif; ?>

                    <div class="component-card__avatar-overlay" onclick="document.getElementById('avatar-upload-input').click()">
                        <span class="material-symbols-rounded">photo_camera</span>
                    </div>
                </div>

                <div class="component-card__text">
                    <h2 class="component-card__title">Foto de perfil</h2>
                    <p class="component-card__description">Esto ayudará a tus compañeros a reconocerte.</p>
                    <p class="component-card__meta">Máximo 2MB. Formatos: PNG, JPG, WEBP.</p>
                </div>
            </div>

            <div class="component-card__actions">
                <div id="avatar-actions-default" class="<?php echo !$hasCustomAvatar ? 'active' : 'disabled'; ?>">
                    <button type="button" class="component-button" id="avatar-upload-trigger">
                        <span class="material-symbols-rounded" style="font-size: 18px;">upload</span> Subir foto
                    </button>
                </div>
                
                <div id="avatar-actions-custom" class="<?php echo $hasCustomAvatar ? 'active' : 'disabled'; ?>">
                    <button type="button" class="component-button" id="avatar-remove-trigger">Eliminar</button>
                    <button type="button" class="component-button" id="avatar-change-trigger">Cambiar foto</button>
                </div>
                
                <div id="avatar-actions-preview" class="disabled">
                    <button type="button" class="component-button" id="avatar-cancel-trigger">Cancelar</button>
                    <button type="button" class="component-button" id="avatar-save-trigger-btn">Guardar</button>
                </div>
            </div>
        </div>

        <div class="component-card component-card--edit-mode" id="username-section">
            
            <div class="component-card__content">
                <div class="component-card__text" style="width: 100%;">
                    <h2 class="component-card__title">Nombre de usuario</h2>
                    
                    <div id="username-view-state" class="active">
                        <p class="component-card__description" id="username-display-text">
                            <?php echo htmlspecialchars($currentUsername); ?>
                        </p>
                    </div>

                    <div id="username-edit-state" class="disabled">
                         <div class="input-with-actions">
                             <input type="text" class="component-text-input" id="username-input" 
                                    value="<?php echo htmlspecialchars($currentUsername); ?>" 
                                    required minlength="8" maxlength="32">
                             
                             <div id="username-actions-edit" class="disabled">
                                <button type="button" class="component-button" id="username-cancel-trigger">Cancelar</button>
                                <button type="button" class="component-button primary" id="username-save-trigger-btn">Guardar</button>
                             </div>
                         </div>
                         <p class="component-card__meta">8-32 caracteres. Letras, números y guión bajo.</p>
                    </div>
                </div>
            </div>

            <div class="component-card__actions">
                <div id="username-actions-view" class="active">
                    <button type="button" class="component-button" id="username-edit-trigger">Editar</button>
                </div>
            </div>
        </div>
        
    </div>
</div>