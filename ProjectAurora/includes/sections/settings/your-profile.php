<?php
if (session_status() === PHP_SESSION_NONE) session_start();

// Aseguramos tener el basePath disponible
$basePath = $basePath ?? '/ProjectAurora/';
$userId = $_SESSION['user_id'];

// 1. Obtener datos frescos del usuario (Username, Avatar y [NUEVO] ROL)
$stmt = $pdo->prepare("SELECT username, avatar, role FROM users WHERE id = ?");
$stmt->execute([$userId]);
$currentUser = $stmt->fetch();

$currentUsername = $currentUser['username'] ?? 'Usuario';
$userAvatar = $currentUser['avatar'] ?? null;
$userRole = $currentUser['role'] ?? 'user'; // <-- Obtenemos el rol

// Construir URL del avatar
$avatarUrl = null;
if ($userAvatar && !empty($userAvatar)) {
    $avatarUrl = $basePath . $userAvatar . '?t=' . time();
}

// [CORRECCIÓN] Lógica para distinguir entre avatar por defecto y personalizado
// Si la ruta en la BD contiene '/default/', es un avatar generado.
// Si no (y no es null), es uno subido por el usuario.
$isDefaultAvatar = false;
if (empty($userAvatar)) {
    $isDefaultAvatar = true;
} else {
    // Buscamos la palabra clave 'default' en la ruta de la BD
    if (strpos($userAvatar, '/default/') !== false) {
        $isDefaultAvatar = true;
    }
}

// La variable clave para la UI:
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
                         <input type="text" class="component-text-input" id="username-input" 
                                value="<?php echo htmlspecialchars($currentUsername); ?>" 
                                required minlength="8" maxlength="32">
                         <p class="component-card__meta">8-32 caracteres. Letras, números y guión bajo.</p>
                    </div>
                </div>
            </div>

            <div class="component-card__actions">
                <div id="username-actions-view" class="active">
                    <button type="button" class="component-button" id="username-edit-trigger">Editar</button>
                </div>

                <div id="username-actions-edit" class="disabled" style="gap: 8px;">
                    <button type="button" class="component-button" id="username-cancel-trigger">Cancelar</button>
                    <button type="button" class="component-button" id="username-save-trigger-btn" style="background: #000; color: #fff; border: none;">Guardar</button>
                </div>
            </div>
        </div>
        
    </div>
</div>

<style>
    /* Layout Global */
    .component-wrapper {
        width: 100%;
        max-width: 750px;
        margin: 0 auto;
        padding: 16px;
        display: flex;
        flex-direction: column;
        gap: 16px;
    }

    /* Tarjetas */
    .component-header-card, .component-card {
        border: 1px solid #00000020;
        border-radius: 12px;
        padding: 24px;
        background-color: #ffffff;
    }

    .component-header-card {
        text-align: center;
    }
    .component-page-title { font-size: 24px; font-weight: 700; margin-bottom: 8px; }
    .component-page-description { font-size: 15px; color: #666; margin: 0; }

    /* Flexbox para las tarjetas de edición */
    .component-card--edit-mode {
        display: flex;
        flex-direction: row;
        align-items: center;
        justify-content: space-between;
        gap: 20px;
        flex-wrap: wrap; /* Permite bajar en móvil */
    }

    .component-card__content {
        flex: 1;
        min-width: 250px; /* Asegura espacio */
        display: flex;
        align-items: center;
        gap: 20px;
    }

    .component-card__text {
        display: flex;
        flex-direction: column;
        gap: 4px;
    }

    .component-card__title { font-size: 16px; font-weight: 600; margin: 0; }
    .component-card__description { font-size: 14px; color: #666; margin: 0; }
    .component-card__meta { font-size: 12px; color: #999; margin-top: 4px; }

    /* Avatar Styles */
    .component-card__avatar {
        width: 72px; height: 72px; border-radius: 50%;
        background-color: #f5f5f5; position: relative;
        display: flex; align-items: center; justify-content: center;
         border: 1px solid #00000020; flex-shrink: 0;
    }

    /* [NUEVO] ESTILOS PARA LOS BORDES DE ROL */
    .component-card__avatar::before {
        content: '';
        position: absolute;
        border-radius: 50%;
        /* Usamos inset negativo para que el borde quede por fuera o justo en el borde */
        top: -4px; left: -4px; right: -4px; bottom: -4px;
        border: 2px solid transparent;
        z-index: 2;
        pointer-events: none;
        transition: border-color 0.2s ease;
    }

    .component-card__avatar[data-role="user"]::before { border-color: #cccccc; }
    .component-card__avatar[data-role="moderator"]::before { border-color: #0000FF; }
    .component-card__avatar[data-role="administrator"]::before { border-color: #FF0000; }
    
    .component-card__avatar[data-role="founder"]::before {
        border: none;
        background-image: conic-gradient(from 300deg, #D32029 0deg 90deg, #206BD3 90deg 210deg, #28A745 210deg 300deg, #FFC107 300deg 360deg);
        mask: radial-gradient(farthest-side, transparent calc(100% - 2px), #fff 0);
        -webkit-mask: radial-gradient(farthest-side, transparent calc(100% - 2px), #fff 0);
    }

    .component-card__avatar-image { width: 100%;        border-radius: 50px; height: 100%; object-fit: cover; }
    .component-card__avatar-overlay {
        position: absolute; inset: 0; background-color: rgba(0,0,0,0.4);

        display: flex; align-items: center; justify-content: center;
        color: #fff; opacity: 0; transition: opacity 0.2s; cursor: pointer;
        z-index: 3; /* Encima del borde */
    }
    .component-card__avatar:hover .component-card__avatar-overlay { opacity: 1; }

    /* Botones y Acciones */
    .component-card__actions {
        display: flex;
        align-items: center;
        justify-content: flex-end;
    }

    .component-button {
        height: 38px; padding: 0 16px; border-radius: 8px;
        font-size: 14px; font-weight: 500; cursor: pointer;
        background: transparent; border: 1px solid #00000020; color: #000;
        display: flex; align-items: center; gap: 8px;
        transition: all 0.2s ease;
    }
    .component-button:hover { background-color: #f5f5fa; }
    .component-button:disabled { opacity: 0.6; cursor: not-allowed; }

    .component-text-input {
        width: 100%; max-width: 300px; height: 40px;
        padding: 0 12px; border: 1px solid #00000020; border-radius: 8px;
        font-size: 14px; outline: none; background: transparent; color: #000;
    }
    .component-text-input:focus { border-color: #000; }

    /* Clases de Estado (Importante para ocultar/mostrar sin superposición) */
    .active { display: block !important; }
    .component-card__actions .active { display: flex !important; gap: 8px; }
    .disabled { display: none !important; }

    @media (max-width: 600px) {
        .component-card--edit-mode { flex-direction: column; align-items: flex-start; }
        .component-card__actions { width: 100%; justify-content: flex-end; margin-top: 10px; }
        .component-text-input { max-width: 100%; }
    }.visually-hidden {
    position: absolute;
    width: 1px;
    height: 1px;
    margin: -1px;
    padding: 0;
    overflow: hidden;
    clip: rect(0, 0, 0, 0);
    border: 0;
}
</style>