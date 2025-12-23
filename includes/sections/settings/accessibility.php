<?php
// includes/sections/settings/accessibility.php

// Obtener preferencias actuales de la sesión (o defaults)
$currentTheme = $_SESSION['preferences']['theme'] ?? 'sync';
$isExtended = isset($_SESSION['preferences']['extended_toast']) && $_SESSION['preferences']['extended_toast'] == 1;

// Mapas para visualización
$themeLabels = [
    'sync' => 'Sistema',
    'light' => 'Claro',
    'dark' => 'Oscuro'
];
$themeIcons = [
    'sync' => 'settings_brightness',
    'light' => 'light_mode',
    'dark' => 'dark_mode'
];

$currentThemeLabel = $themeLabels[$currentTheme] ?? 'Sistema';
$currentThemeIcon = $themeIcons[$currentTheme] ?? 'settings_brightness';
?>

<div class="section-content active" data-section="settings/accessibility">
    <div class="component-wrapper">
        
        <div class="component-header-card">
            <h1 class="component-page-title">Accesibilidad</h1>
            <p class="component-page-description">Personaliza la apariencia y el comportamiento de la aplicación.</p>
        </div>

        <div class="component-card component-card--grouped">
            <div class="component-group-item component-group-item--stacked">
                <div class="component-card__content">
                    <div class="component-card__text">
                        <h2 class="component-card__title">Tema</h2>
                        <p class="component-card__description">Elige el tema de la interfaz.</p>
                    </div>
                </div>
                
                <div class="component-card__actions">
                    <div class="trigger-select-wrapper" onclick="toggleDropdown(this)">
                        
                        <div class="trigger-selector">
                            <span class="material-symbols-rounded trigger-select-icon"><?php echo $currentThemeIcon; ?></span>
                            <span class="trigger-select-text"><?php echo $currentThemeLabel; ?></span>
                            <span class="material-symbols-rounded">expand_more</span>
                        </div>

                        <div class="popover-module">
                            <div class="menu-list">
                                <div class="menu-link <?php echo ($currentTheme === 'sync') ? 'active' : ''; ?>" 
                                     onclick="selectOption(this, 'Sistema', 'sync')">
                                    <div class="menu-link-icon">
                                        <span class="material-symbols-rounded">settings_brightness</span>
                                    </div>
                                    <div class="menu-link-text">Sistema</div>
                                </div>
                                
                                <div class="menu-link <?php echo ($currentTheme === 'light') ? 'active' : ''; ?>" 
                                     onclick="selectOption(this, 'Claro', 'light')">
                                    <div class="menu-link-icon">
                                        <span class="material-symbols-rounded">light_mode</span>
                                    </div>
                                    <div class="menu-link-text">Claro</div>
                                </div>
                                
                                <div class="menu-link <?php echo ($currentTheme === 'dark') ? 'active' : ''; ?>" 
                                     onclick="selectOption(this, 'Oscuro', 'dark')">
                                    <div class="menu-link-icon">
                                        <span class="material-symbols-rounded">dark_mode</span>
                                    </div>
                                    <div class="menu-link-text">Oscuro</div>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </div>

        <div class="component-card component-card--grouped">
            <div class="component-group-item">
                <div class="component-card__content">
                    <div class="component-card__text">
                        <h2 class="component-card__title">Duración de notificaciones</h2>
                        <p class="component-card__description">Mantiene los mensajes en pantalla por más tiempo (10s).</p>
                    </div>
                </div>
                <div class="component-card__actions actions-right">
                    <label class="component-toggle-switch">
                        <input type="checkbox" id="pref-extended-toast" <?php echo $isExtended ? 'checked' : ''; ?>>
                        <span class="component-toggle-slider"></span>
                    </label>
                </div>
            </div>
        </div>

    </div>
</div>