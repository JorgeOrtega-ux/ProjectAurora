<?php
// includes/sections/settings/your-profile.php

if (session_status() === PHP_SESSION_NONE) session_start();

// [MOCKUP] Datos simulados
$currentUser = [
    'username' => $_SESSION['user_username'] ?? 'UsuarioDemo',
    'email'    => $_SESSION['user_email'] ?? 'demo@projectaurora.com',
    'role'     => $_SESSION['user_role'] ?? 'founder',
    'pfp'      => $_SESSION['user_profile_picture'] ?? null,
    // Valor por defecto actualizado a un código compatible
    'language' => $_SESSION['user_language'] ?? 'es-419', 
    'open_new_tab' => $_SESSION['open_links_in_new_tab'] ?? 1
];

$pfpSrc = $currentUser['pfp'] 
    ? (isset($basePath) ? $basePath : '') . $currentUser['pfp'] 
    : 'https://ui-avatars.com/api/?name=' . urlencode($currentUser['username']) . '&background=random';

// Configuración de idiomas solicitada
$langLabels = [
    'es-419' => 'Español (Latinoamérica)',
    'en-US'  => 'English (United States)',
    'en-GB'  => 'English (United Kingdom)',
    'fr-FR'  => 'Français (France)'
];

// Obtener etiqueta actual o fallback
$currentLangLabel = $langLabels[$currentUser['language']] ?? $langLabels['es-419'];
?>

<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@24,400,1,0" />
<link href="https://fonts.googleapis.com/css2?family=Roboto+Condensed:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet">

<style>
    /* --- RESET & BASE --- */
    .component-wrapper * { box-sizing: border-box; font-family: "Roboto Condensed", sans-serif; }
    
    .component-wrapper {
        width: 100%; max-width: 700px; margin: 0 auto; padding: 16px;
        display: flex; flex-direction: column; gap: 16px;
    }

    /* --- TARJETAS --- */
    .component-header-card, .component-card {
        border: 1px solid #00000020; border-radius: 12px;
        padding: 24px; background-color: #ffffff;
    }
    .component-header-card { text-align: center; }
    .component-page-title { font-size: 24px; font-weight: 700; margin: 0 0 8px 0; color: #000; }
    .component-page-description { font-size: 15px; color: #666; margin: 0; }

    /* --- AGRUPACIÓN --- */
    .component-card--grouped { 
        display: flex; flex-direction: column; padding: 0; gap: 0; 
        overflow: hidden;
    }
    .component-group-item {
        display: flex; flex-direction: row; align-items: center; justify-content: space-between;
        flex-wrap: wrap; padding: 24px; background-color: transparent; gap: 16px;
    }
    .component-divider { border: 0; border-top: 1px solid #00000015; width: 100%; margin: 0; }

    /* --- CONTENIDO --- */
    .component-card__content {
        flex: 1 1 auto; min-width: 0; display: flex; align-items: center; gap: 20px;
    }
    .component-card__text { display: flex; flex-direction: column; gap: 4px; width: 100%; }
    .component-card__title { font-size: 15px; font-weight: 600; margin: 0; color: #000; }
    /* Eliminado margin-bottom de descripción para ajustar espaciado cuando no hay texto */
    .component-card__description { font-size: 14px; color: #666; margin: 0; line-height: 1.4; }

    /* --- FOTO DE PERFIL --- */
    .component-card__profile-picture {
        width: 56px; height: 56px; border-radius: 50%;
        background-color: #f5f5f5; position: relative;
        display: flex; align-items: center; justify-content: center; flex-shrink: 0;
    }
    .component-card__profile-picture::before {
        content: ''; position: absolute; top: -3px; left: -3px; right: -3px; bottom: -3px;
        border-radius: 50%; border: 2px solid transparent; pointer-events: none;
    }
    .component-card__profile-picture[data-role="user"]::before { border-color: #cccccc; }
    .component-card__profile-picture[data-role="moderator"]::before { border-color: #0000FF; }
    .component-card__profile-picture[data-role="administrator"]::before { border-color: #FF0000; }
    .component-card__profile-picture[data-role="founder"]::before {
        border: none;
        background-image: conic-gradient(from 300deg, #D32029 0deg 90deg, #206BD3 90deg 210deg, #28A745 210deg 300deg, #FFC107 300deg 360deg);
        mask: radial-gradient(farthest-side, transparent calc(100% - 2px), #fff 0);
        -webkit-mask: radial-gradient(farthest-side, transparent calc(100% - 2px), #fff 0);
    }
    .component-card__avatar-image { width: 100%; height: 100%; object-fit: cover; border-radius: 50%; }
    .component-card__avatar-overlay {
        position: absolute; inset: 0; background-color: rgba(0, 0, 0, 0.4);
        display: flex; align-items: center; justify-content: center;
        color: #fff; opacity: 0; transition: opacity 0.2s;
        cursor: pointer; border-radius: 50%; z-index: 2;
    }
    .component-card__profile-picture:hover .component-card__avatar-overlay { opacity: 1; }

    /* --- INPUTS Y BOTONES --- */
    .component-card__actions { display: flex; align-items: center; gap: 8px; flex-shrink: 0; }
    .component-card__actions.actions-right { justify-content: flex-end; }
    
    .component-button {
        height: 36px; padding: 0 14px; border-radius: 8px;
        font-size: 13px; font-weight: 500; cursor: pointer;
        background: transparent; border: 1px solid #00000020; color: #000;
        display: flex; align-items: center; justify-content: center; gap: 6px;
        transition: all 0.2s ease; white-space: nowrap;
    }
    .component-button:hover { background-color: #f5f5fa; }
    .component-button.primary { background-color: #000; color: #fff; border: none; }
    .component-button.primary:hover { background-color: #333; }
    .component-button.danger { border-color: #ffcdd2; color: #d32f2f; }
    .component-button.danger:hover { background-color: #ffebee; }

    .component-text-input {
        width: 100%; height: 36px; padding: 0 12px;
        border: 1px solid #00000020; border-radius: 8px;
        font-size: 14px; outline: none; background: transparent; color: #000;
    }
    .component-text-input:focus { border-color: #000; }
    .component-input-wrapper { width: 100%; min-width: 200px; }

    /* --- SELECTOR --- */
    .trigger-select-wrapper { position: relative; width: 220px; height: 36px; } /* Ancho aumentado ligeramente para textos largos */
    .trigger-selector {
        display: flex; align-items: center; width: 100%; height: 100%;
        border: 1px solid #00000020; border-radius: 8px;
        background-color: #ffffff; cursor: pointer;
        padding: 0 8px 0 12px; user-select: none;
    }
    .trigger-selector:hover { background-color: #f5f5fa; }
    .trigger-select-text { flex-grow: 1; font-size: 13px; font-weight: 500; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    
    .popover-module {
        position: absolute; top: calc(100% + 5px); right: 0; width: 100%; z-index: 10;
        background: #fff; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        border: 1px solid #00000010; overflow: hidden; display: none;
    }
    .popover-module.active { display: block; }
    
    .menu-link { display: flex; align-items: center; padding: 10px 12px; cursor: pointer; gap: 8px; justify-content: space-between; }
    .menu-link:hover { background-color: #f5f5fa; }
    .menu-link.active { background-color: #f0f0f0; font-weight: 600; }
    .menu-link-text { font-size: 13px; color: #333; }

    /* --- TOGGLE --- */
    .component-toggle-switch { position: relative; display: inline-block; width: 40px; height: 24px; }
    .component-toggle-switch input { opacity: 0; width: 0; height: 0; }
    .component-toggle-slider {
        position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0;
        background-color: #e0e0e0; transition: .3s; border-radius: 34px;
    }
    .component-toggle-slider:before {
        position: absolute; content: ""; height: 20px; width: 20px; left: 2px; bottom: 2px;
        background-color: white; transition: .3s; border-radius: 50%; box-shadow: 0 1px 3px rgba(0,0,0,0.2);
    }
    .component-toggle-switch input:checked + .component-toggle-slider { background-color: #000; }
    .component-toggle-switch input:checked + .component-toggle-slider:before { transform: translateX(16px); }

    /* --- UTILS --- */
    .active { display: flex !important; }
    .disabled { display: none !important; }
    .material-symbols-rounded { font-size: 20px; }
    .component-badge { font-size: 13px; font-weight: 500; color: #333; }
    
    @media (max-width: 600px) {
        .component-group-item { flex-direction: column; align-items: flex-start; gap: 12px; }
        .component-card__actions { width: 100%; justify-content: flex-end; margin-top: 4px; }
        .component-input-wrapper { width: 100%; }
        .trigger-select-wrapper { width: 100%; }
    }
</style>

<div class="section-content active" data-section="settings/your-profile">
    <div class="component-wrapper">

        <div class="component-header-card">
            <h1 class="component-page-title">Tu Perfil</h1>
            <p class="component-page-description">Gestiona tu identidad y preferencias personales.</p>
        </div>

        <div class="component-card component-card--grouped">

            <div class="component-group-item" data-component="profile-picture-section">
                <div class="component-card__content">
                    <div class="component-card__profile-picture" data-role="<?php echo htmlspecialchars($currentUser['role']); ?>">
                        <img src="<?php echo htmlspecialchars($pfpSrc); ?>" 
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
                
                <input type="file" accept="image/*" hidden data-element="profile-picture-upload-input">

                <div class="component-card__actions actions-right">
                    <div class="active" data-state="profile-picture-actions-default">
                        <button type="button" class="component-button danger" data-action="profile-picture-remove-trigger">
                            <span class="material-symbols-rounded">delete</span>
                        </button>
                        <button type="button" class="component-button primary" data-action="profile-picture-upload-trigger">
                            <span class="material-symbols-rounded">upload</span> Cambiar
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
                        <p class="component-card__description">Idioma de la interfaz.</p>
                    </div>
                </div>
                <div class="component-card__actions actions-right">
                    <div class="trigger-select-wrapper">
                        <div class="trigger-selector" id="language-trigger">
                            <div class="trigger-select-text"><?php echo $currentLangLabel; ?></div>
                            <span class="material-symbols-rounded">arrow_drop_down</span>
                        </div>
                        <div class="popover-module" id="language-popover" data-preference-type="language">
                            <div class="menu-list">
                                <?php foreach ($langLabels as $code => $label): ?>
                                    <div class="menu-link <?php echo ($currentUser['language'] === $code) ? 'active' : ''; ?>" 
                                         data-value="<?php echo $code; ?>"
                                         data-action="select-language">
                                        <div class="menu-link-text"><?php echo $label; ?></div>
                                        <?php if ($currentUser['language'] === $code): ?>
                                            <span class="material-symbols-rounded">check</span>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <hr class="component-divider">

            <div class="component-group-item">
                <div class="component-card__content">
                    <div class="component-card__text">
                        <h2 class="component-card__title">Enlaces externos</h2>
                        <p class="component-card__description">Abrir enlaces en nueva pestaña.</p>
                    </div>
                </div>
                <div class="component-card__actions actions-right">
                    <label class="component-toggle-switch">
                        <input type="checkbox" id="open-links-toggle"
                               <?php echo ($currentUser['open_new_tab'] == 1) ? 'checked' : ''; ?>>
                        <span class="component-toggle-slider"></span>
                    </label>
                </div>
            </div>

        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {

    /* --- UTILIDADES --- */
    const qs = (sel, parent = document) => parent.querySelector(sel);
    
    // Función para alternar modo Vista/Edición
    function toggleEditMode(section, isEditing) {
        const viewState = section.querySelector('[data-state$="-view-state"]');
        const editState = section.querySelector('[data-state$="-edit-state"]');
        const actionsView = section.querySelector('[data-state$="-actions-view"]');
        const actionsEdit = section.querySelector('[data-state$="-actions-edit"]');

        if (isEditing) {
            if(viewState) { viewState.classList.remove('active'); viewState.classList.add('disabled'); }
            if(actionsView) { actionsView.classList.remove('active'); actionsView.classList.add('disabled'); }
            
            if(editState) { editState.classList.remove('disabled'); editState.classList.add('active'); }
            if(actionsEdit) { actionsEdit.classList.remove('disabled'); actionsEdit.classList.add('active'); }
            
            // Auto-focus al input
            const input = section.querySelector('input');
            if(input) input.focus();

        } else {
            if(editState) { editState.classList.remove('active'); editState.classList.add('disabled'); }
            if(actionsEdit) { actionsEdit.classList.remove('active'); actionsEdit.classList.add('disabled'); }

            if(viewState) { viewState.classList.remove('disabled'); viewState.classList.add('active'); }
            if(actionsView) { actionsView.classList.remove('disabled'); actionsView.classList.add('active'); }
        }
    }

    /* --- 1. LÓGICA NOMBRE DE USUARIO --- */
    const usernameSection = qs('[data-component="username-section"]');
    if (usernameSection) {
        const input = qs('[data-element="username-input"]', usernameSection);
        const display = qs('[data-element="username-display-text"]', usernameSection);
        let originalValue = input.value;

        // Click en Editar
        qs('[data-action="username-edit-trigger"]', usernameSection).onclick = () => {
            input.value = originalValue; // Resetear al valor guardado
            toggleEditMode(usernameSection, true);
        };

        // Click en Cancelar
        qs('[data-action="username-cancel-trigger"]', usernameSection).onclick = () => {
            input.value = originalValue;
            toggleEditMode(usernameSection, false);
        };

        // Click en Guardar (Simulado)
        qs('[data-action="username-save-trigger-btn"]', usernameSection).onclick = () => {
            const newVal = input.value.trim();
            if(!newVal) return alert("El nombre no puede estar vacío");
            
            // Simular guardado
            originalValue = newVal;
            display.innerText = '@' + newVal;
            toggleEditMode(usernameSection, false);
            console.log("Guardado Username:", newVal);
        };
    }

    /* --- 2. LÓGICA EMAIL --- */
    const emailSection = qs('[data-component="email-section"]');
    if (emailSection) {
        const input = qs('[data-element="email-input"]', emailSection);
        const display = qs('[data-element="email-display-text"]', emailSection);
        let originalValue = input.value;

        qs('[data-action="email-edit-trigger"]', emailSection).onclick = () => {
            input.value = originalValue;
            toggleEditMode(emailSection, true);
        };

        qs('[data-action="email-cancel-trigger"]', emailSection).onclick = () => {
            input.value = originalValue;
            toggleEditMode(emailSection, false);
        };

        qs('[data-action="email-save-trigger-btn"]', emailSection).onclick = () => {
            const newVal = input.value.trim();
            if(!newVal.includes('@')) return alert("Correo inválido");
            
            originalValue = newVal;
            display.innerText = newVal;
            toggleEditMode(emailSection, false);
            console.log("Guardado Email:", newVal);
        };
    }

    /* --- 3. LÓGICA FOTO DE PERFIL --- */
    const pfpSection = qs('[data-component="profile-picture-section"]');
    if (pfpSection) {
        const fileInput = qs('[data-element="profile-picture-upload-input"]', pfpSection);
        const previewImg = qs('[data-element="profile-picture-preview-image"]', pfpSection);
        const actionsDefault = qs('[data-state="profile-picture-actions-default"]', pfpSection);
        const actionsPreview = qs('[data-state="profile-picture-actions-preview"]', pfpSection);
        
        let originalSrc = previewImg.src;

        // Trigger input file
        const triggerUpload = () => fileInput.click();
        qs('[data-action="trigger-profile-picture-upload"]', pfpSection).onclick = triggerUpload;
        qs('[data-action="profile-picture-upload-trigger"]', pfpSection).onclick = triggerUpload;

        // Al seleccionar archivo
        fileInput.onchange = (e) => {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = (evt) => {
                    previewImg.src = evt.target.result;
                    // Cambiar botones a modo "Guardar/Cancelar"
                    actionsDefault.classList.remove('active'); actionsDefault.classList.add('disabled');
                    actionsPreview.classList.remove('disabled'); actionsPreview.classList.add('active');
                };
                reader.readAsDataURL(file);
            }
        };

        // Cancelar subida
        qs('[data-action="profile-picture-cancel-trigger"]', pfpSection).onclick = () => {
            previewImg.src = originalSrc;
            fileInput.value = '';
            // Volver botones a default
            actionsPreview.classList.remove('active'); actionsPreview.classList.add('disabled');
            actionsDefault.classList.remove('disabled'); actionsDefault.classList.add('active');
        };

        // Guardar subida
        qs('[data-action="profile-picture-save-trigger-btn"]', pfpSection).onclick = () => {
            originalSrc = previewImg.src; // Actualizar referencia
            alert("Foto guardada (simulación)");
            actionsPreview.classList.remove('active'); actionsPreview.classList.add('disabled');
            actionsDefault.classList.remove('disabled'); actionsDefault.classList.add('active');
        };
        
        // Borrar foto
        qs('[data-action="profile-picture-remove-trigger"]', pfpSection).onclick = () => {
            if(confirm("¿Borrar foto?")) {
                alert("Foto borrada");
                previewImg.src = "https://ui-avatars.com/api/?background=random"; // Reset dummy
            }
        };
    }

    /* --- 4. LÓGICA IDIOMA (DROPDOWN) --- */
    const langTrigger = document.getElementById('language-trigger');
    const langPopover = document.getElementById('language-popover');
    
    if (langTrigger && langPopover) {
        // Abrir/Cerrar
        langTrigger.onclick = (e) => {
            e.stopPropagation();
            langPopover.classList.toggle('active');
        };

        // Cerrar al hacer click fuera
        document.addEventListener('click', (e) => {
            if (!langTrigger.contains(e.target) && !langPopover.contains(e.target)) {
                langPopover.classList.remove('active');
            }
        });

        // Seleccionar opción
        const options = langPopover.querySelectorAll('[data-action="select-language"]');
        options.forEach(opt => {
            opt.onclick = () => {
                const label = opt.querySelector('.menu-link-text').innerText;
                const value = opt.getAttribute('data-value');
                
                // Actualizar texto del trigger
                langTrigger.querySelector('.trigger-select-text').innerText = label;
                
                // Actualizar checks visuales
                options.forEach(o => {
                    o.classList.remove('active');
                    const check = o.querySelector('.material-symbols-rounded');
                    if(check) check.remove();
                });
                
                opt.classList.add('active');
                // Agregar check
                const checkSpan = document.createElement('span');
                checkSpan.className = 'material-symbols-rounded';
                checkSpan.innerText = 'check';
                opt.appendChild(checkSpan);

                langPopover.classList.remove('active');
                console.log("Idioma cambiado a:", value);
            };
        });
    }

    /* --- 5. LÓGICA TOGGLE --- */
    const toggle = document.getElementById('open-links-toggle');
    if(toggle) {
        toggle.onchange = (e) => {
            console.log("Abrir en nueva pestaña:", e.target.checked);
        };
    }

});
</script>