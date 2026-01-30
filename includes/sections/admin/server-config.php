<?php
// includes/sections/admin/server-config.php

require_once __DIR__ . '/../../libs/Utils.php';
require_once __DIR__ . '/../../../api/services/AdminService.php';

$currentAdminId = $_SESSION['user_id'] ?? 0;
$adminService = new AdminService($pdo, $i18n, $currentAdminId);
$res = $adminService->getServerConfigAll();
$config = $res['success'] ? $res['config'] : [];

function conf($key, $default = '', $configArray) {
    return isset($configArray[$key]) ? htmlspecialchars($configArray[$key]) : $default;
}

function isChecked($key, $configArray) {
    return (isset($configArray[$key]) && $configArray[$key] == '1') ? 'checked' : '';
}

function renderStepper($label, $desc, $name, $value, $stepSmall = 1, $stepLarge = 10) {
    ?>
    <div class="component-group-item component-group-item--stacked">
        <div class="component-card__content w-100">
            <div class="component-card__text">
                <h2 class="component-card__title"><?php echo $label; ?></h2>
                <p class="component-card__description"><?php echo $desc; ?></p>
            </div>
        </div>
        <div class="component-card__actions w-100">
            <div class="stepper-control" data-role="stepper" data-step-small="<?php echo $stepSmall; ?>" data-step-large="<?php echo $stepLarge; ?>">
                <div class="stepper-side left">
                    <button type="button" class="component-button stepper-btn" data-action="dec-large" title="-<?php echo $stepLarge; ?>">
                        <span class="material-symbols-rounded">keyboard_double_arrow_left</span>
                    </button>
                    <button type="button" class="component-button stepper-btn" data-action="dec-small" title="-<?php echo $stepSmall; ?>">
                        <span class="material-symbols-rounded">chevron_left</span>
                    </button>
                </div>
                <div class="stepper-center">
                    <input type="number" class="component-text-input stepper-input" name="<?php echo $name; ?>" value="<?php echo $value; ?>">
                </div>
                <div class="stepper-side right">
                    <button type="button" class="component-button stepper-btn" data-action="inc-small" title="+<?php echo $stepSmall; ?>">
                        <span class="material-symbols-rounded">chevron_right</span>
                    </button>
                    <button type="button" class="component-button stepper-btn" data-action="inc-large" title="+<?php echo $stepLarge; ?>">
                        <span class="material-symbols-rounded">keyboard_double_arrow_right</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
    <hr class="component-divider">
    <?php
}
?>

<div class="component-wrapper" data-section="admin-server-config">
    
    <div class="component-toolbar-wrapper">
        <div class="component-toolbar component-toolbar--primary">
            <div class="toolbar-group">
                <div class="component-toolbar__side component-toolbar__side--left">
                    <div class="component-toolbar-title">Configuración del Servidor</div>
                </div>
                <div class="component-toolbar__side component-toolbar__side--right">
                    <button class="header-button" data-nav="admin/redis" data-tooltip="Gestor de Redis">
                        <span class="material-symbols-rounded" style="color: #d32f2f;">memory</span>
                    </button>

                    <button class="component-button primary" id="btn-save-server-config">
                        <span class="material-symbols-rounded" style="font-size: 18px;">save</span>
                        Guardar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="component-header-card mt-4">
        <h1 class="component-page-title"><?php echo $i18n->t('menu.admin.server'); ?></h1>
        <p class="component-page-description">Ajusta las variables globales y el comportamiento del sistema.</p>
    </div>

    <div class="component-card component-card--grouped mt-4">
        <div class="component-accordion-item" data-accordion-id="access">
            <div class="component-group-item component-accordion-header">
                <div class="component-card__content">
                    <div class="component-card__icon-container component-card__icon-container--bordered">
                        <span class="material-symbols-rounded">admin_panel_settings</span>
                    </div>
                    <div class="component-card__text">
                        <h2 class="component-card__title">Control de Acceso y Mantenimiento</h2>
                        <p class="component-card__description">Gestiona la disponibilidad de la plataforma.</p>
                    </div>
                </div>
                <span class="material-symbols-rounded component-accordion-chevron">expand_more</span>
            </div>

            <div class="component-accordion-content">
                <hr class="component-divider">
                
                <div class="component-group-item">
                    <div class="component-card__content">
                        <div class="component-card__text">
                            <h2 class="component-card__title">Modo Mantenimiento</h2>
                            <p class="component-card__description">Si se activa, solo los administradores y fundadores podrán acceder al sistema.</p>
                        </div>
                    </div>
                    <div class="component-card__actions actions-right">
                        <label class="component-toggle-switch">
                            <input type="checkbox" name="maintenance_mode" <?php echo isChecked('maintenance_mode', $config); ?>>
                            <span class="component-toggle-slider"></span>
                        </label>
                    </div>
                </div>

                <hr class="component-divider">

                <div class="component-group-item">
                    <div class="component-card__content">
                        <div class="component-card__text">
                            <h2 class="component-card__title">Permitir Nuevos Registros</h2>
                            <p class="component-card__description">Habilita o deshabilita la creación de nuevas cuentas de usuario.</p>
                        </div>
                    </div>
                    <div class="component-card__actions actions-right">
                        <label class="component-toggle-switch">
                            <input type="checkbox" name="allow_registrations" <?php echo isChecked('allow_registrations', $config); ?>>
                            <span class="component-toggle-slider"></span>
                        </label>
                    </div>
                </div>

                <hr class="component-divider">

                <div class="component-group-item">
                    <div class="component-card__content">
                        <div class="component-card__text">
                            <h2 class="component-card__title">Permitir Inicio de Sesión</h2>
                            <p class="component-card__description">Control global para el acceso de usuarios existentes (no afecta a Staff).</p>
                        </div>
                    </div>
                    <div class="component-card__actions actions-right">
                        <label class="component-toggle-switch">
                            <input type="checkbox" name="allow_login" <?php echo isChecked('allow_login', $config); ?>>
                            <span class="component-toggle-slider"></span>
                        </label>
                    </div>
                </div>
            </div>
        </div>

        <hr class="component-divider">

        <div class="component-accordion-item" data-accordion-id="security">
            <div class="component-group-item component-accordion-header">
                <div class="component-card__content">
                    <div class="component-card__icon-container component-card__icon-container--bordered">
                        <span class="material-symbols-rounded">shield</span>
                    </div>
                    <div class="component-card__text">
                        <h2 class="component-card__title">Seguridad y Límites</h2>
                        <p class="component-card__description">Configura protecciones contra fuerza bruta y rate limits.</p>
                    </div>
                </div>
                <span class="material-symbols-rounded component-accordion-chevron">expand_more</span>
            </div>

            <div class="component-accordion-content">
                <hr class="component-divider">

                <?php 
                renderStepper('Intentos de Login Máximos', 'Número de intentos fallidos antes de bloquear temporalmente.', 'security_login_max_attempts', conf('security_login_max_attempts', 5, $config), 1, 5);
                renderStepper('Duración del Bloqueo (Minutos)', 'Tiempo que debe esperar un usuario bloqueado por seguridad.', 'security_block_duration', conf('security_block_duration', 15, $config), 1, 15);
                renderStepper('Rate Limit General (Peticiones/Min)', 'Límite de solicitudes para acciones sensibles.', 'security_general_rate_limit', conf('security_general_rate_limit', 10, $config), 1, 10);
                ?>
            </div>
        </div>

        <hr class="component-divider">

        <div class="component-accordion-item" data-accordion-id="accounts">
            <div class="component-group-item component-accordion-header">
                <div class="component-card__content">
                    <div class="component-card__icon-container component-card__icon-container--bordered">
                        <span class="material-symbols-rounded">badge</span>
                    </div>
                    <div class="component-card__text">
                        <h2 class="component-card__title">Requisitos de Cuenta</h2>
                        <p class="component-card__description">Reglas para nombres de usuario, contraseñas y correos.</p>
                    </div>
                </div>
                <span class="material-symbols-rounded component-accordion-chevron">expand_more</span>
            </div>

            <div class="component-accordion-content">
                <hr class="component-divider">

                <?php
                renderStepper('Longitud Mínima de Contraseña', 'Mínimo de caracteres requeridos para la clave.', 'password_min_length', conf('password_min_length', 8, $config), 1, 4);
                renderStepper('Longitud Mínima de Usuario', 'Longitud mínima permitida para el nombre de usuario.', 'username_min_length', conf('username_min_length', 4, $config), 1, 2);
                renderStepper('Longitud Máxima de Usuario', 'Longitud máxima permitida para el nombre de usuario.', 'username_max_length', conf('username_max_length', 20, $config), 1, 5);
                renderStepper('Longitud Mínima Prefijo Email', 'Caracteres requeridos antes del @.', 'email_min_prefix_length', conf('email_min_prefix_length', 3, $config), 1, 3);
                ?>

                <div class="component-group-item component-group-item--stacked">
                    <div class="component-card__content w-100">
                        <div class="component-card__text">
                            <h2 class="component-card__title">Dominios de Correo Permitidos</h2>
                            <p class="component-card__description">Solo se permitirán registros con estos dominios. Usa <strong>*</strong> para todos.</p>
                        </div>
                    </div>
                    
                    <div class="component-card__actions w-100">
                        <input type="hidden" name="email_allowed_domains" id="input-allowed-domains" value="<?php echo conf('email_allowed_domains', '*', $config); ?>">
                        
                        <div class="domain-manager-wrapper">
                            <div class="domain-list" id="domain-list-container"></div>
                            
                            <div class="domain-input-group" id="domain-input-group" style="display: none;">
                                <input type="text" class="component-text-input" id="new-domain-input" placeholder="ej: gmail.com" autocomplete="off">
                                
                                <div class="domain-input-actions">
                                    <button type="button" class="component-button" id="btn-cancel-domain">
                                        Cancelar
                                    </button>
                                    <button type="button" class="component-button primary" id="btn-confirm-domain">
                                        <span class="material-symbols-rounded">check</span>
                                        Agregar
                                    </button>
                                </div>
                            </div>

                            <button type="button" class="component-button" id="btn-add-domain">
                                <span class="material-symbols-rounded">add</span>
                                Agregar dominio
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <hr class="component-divider">

        <div class="component-accordion-item" data-accordion-id="uploads">
            <div class="component-group-item component-accordion-header">
                <div class="component-card__content">
                    <div class="component-card__icon-container component-card__icon-container--bordered">
                        <span class="material-symbols-rounded">cloud_upload</span>
                    </div>
                    <div class="component-card__text">
                        <h2 class="component-card__title">Carga de Archivos</h2>
                        <p class="component-card__description">Límites para avatares y archivos multimedia.</p>
                    </div>
                </div>
                <span class="material-symbols-rounded component-accordion-chevron">expand_more</span>
            </div>

            <div class="component-accordion-content">
                <hr class="component-divider">
                <?php
                $bytesVal = (int)conf('upload_avatar_max_size', 2097152, $config);
                $mbVal = $bytesVal / 1048576;
                renderStepper('Tamaño Máximo de Avatar (MB)', 'Límite de peso para la imagen de perfil en Megabytes.', 'upload_avatar_max_size', $mbVal, 1, 5);
                renderStepper('Dimensión Máxima (Píxeles)', 'Alto o ancho máximo permitido para la imagen.', 'upload_avatar_max_dim', conf('upload_avatar_max_dim', 4096, $config), 128, 1024);
                ?>
            </div>
        </div>

        <hr class="component-divider">

        <div class="component-accordion-item" data-accordion-id="tokens">
            <div class="component-group-item component-accordion-header">
                <div class="component-card__content">
                    <div class="component-card__icon-container component-card__icon-container--bordered">
                        <span class="material-symbols-rounded">timer</span>
                    </div>
                    <div class="component-card__text">
                        <h2 class="component-card__title">Expiración de Tokens</h2>
                        <p class="component-card__description">Controla la validez de enlaces y códigos temporales.</p>
                    </div>
                </div>
                <span class="material-symbols-rounded component-accordion-chevron">expand_more</span>
            </div>

            <div class="component-accordion-content">
                <hr class="component-divider">
                <?php
                renderStepper('Expiración Código Verificación (Minutos)', 'Tiempo de validez para códigos de email.', 'auth_verification_code_expiry', conf('auth_verification_code_expiry', 15, $config), 1, 5);
                renderStepper('Expiración Token Contraseña (Minutos)', 'Tiempo de validez para enlaces de recuperación.', 'auth_reset_token_expiry', conf('auth_reset_token_expiry', 60, $config), 5, 30);
                ?>
            </div>
        </div>

    </div>
</div>