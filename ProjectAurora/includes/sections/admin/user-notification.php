<?php
// includes/sections/admin/user-notification.php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!in_array($_SESSION['user_role'], ['founder', 'administrator'])) {
    include __DIR__ . '/../system/404.php'; exit;
}

$targetUid = $_GET['uid'] ?? 0;
$basePath = isset($GLOBALS['basePath']) ? $GLOBALS['basePath'] : '/ProjectAurora/';
?>

<link rel="stylesheet" href="<?php echo $basePath; ?>assets/css/admin.css">

<div class="section-content active" data-section="admin/user-notification">
    
    <div class="toolbar-stack">
        <div class="component-toolbar">
            <div class="component-toolbar__group">
                <div class="component-icon-button" data-nav="admin/users" data-i18n-tooltip="global.back" data-tooltip="<?php echo trans('global.back'); ?>">
                    <span class="material-symbols-rounded">arrow_back</span>
                </div>
                <div class="component-toolbar__separator"></div>
                <span style="font-size: 14px; font-weight: 600; color: #666;">Notificaciones</span>
            </div>
        </div>
    </div>

    <div class="component-wrapper section-with-toolbar">

        <div class="component-header-card">
            <h1 class="component-page-title">Centro de Notificaciones</h1>
            <p class="component-page-description">Envía alertas rápidas o mensajes directos al usuario.</p>
        </div>

        <div class="component-card component-card--grouped">
            <div class="component-group-item">
                <div class="component-card__content">
                    <div class="component-card__profile-picture" id="notification-pfp-container">
                        <img src="" id="notification-user-avatar" class="component-card__avatar-image" style="display:none;">
                        <span class="material-symbols-rounded default-avatar-icon" id="notification-user-icon" style="font-size: 24px;">person</span>
                    </div>
                    <div class="component-card__text">
                        <h2 class="component-card__title" id="notification-username" data-i18n="global.loading"><?php echo trans('global.loading'); ?></h2>
                        <p class="component-card__description" id="notification-email">...</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="component-card component-card--grouped" style="margin-top: 16px;">
            <input type="hidden" id="notification-target-id" value="<?php echo htmlspecialchars($targetUid); ?>">
            <input type="hidden" id="notif-level-value" value="info">

            <div class="component-group-item component-group-item--stacked">
                <div class="component-card__content">
                    <div class="component-card__text">
                        <h2 class="component-card__title">Plantillas Rápidas</h2>
                        <p class="component-card__description">Selecciona un motivo predefinido para autocompletar.</p>
                    </div>
                </div>
                <div class="component-card__actions w-100">
                    <div class="trigger-select-wrapper w-100">
                        <div class="trigger-selector" data-action="toggle-dropdown" data-target="dropdown-notif-template">
                            <div class="trigger-select-icon">
                                <span class="material-symbols-rounded" style="color: #666;">auto_fix_high</span>
                            </div>
                            <div class="trigger-select-text">
                                <span id="notif-template-text">Seleccionar plantilla...</span>
                            </div>
                            <div class="trigger-select-arrow">
                                <span class="material-symbols-rounded">arrow_drop_down</span>
                            </div>
                        </div>

                        <div class="popover-module popover-module--anchor-width body-title disabled" id="dropdown-notif-template">
                            <div class="menu-content">
                                <div class="menu-list" style="max-height: 300px; overflow-y: auto;">
                                    
                                    <div style="padding: 8px 12px; font-size: 11px; font-weight: 700; color: #999; text-transform: uppercase;">Moderación</div>
                                    
                                    <div class="menu-link" data-action="select-notif-template" 
                                         data-level="warning" 
                                         data-color="#f57c00"
                                         data-message="Hemos detectado un envío excesivo de mensajes o contenido repetitivo por tu parte. Por favor, evita saturar los canales.">
                                        <div class="menu-link-icon"><span class="material-symbols-rounded" style="color:#f57c00">speed</span></div>
                                        <div class="menu-link-text">Spam / Flood</div>
                                    </div>

                                    <div class="menu-link" data-action="select-notif-template" 
                                         data-level="warning" 
                                         data-color="#f57c00"
                                         data-message="Hemos recibido reportes sobre tu comportamiento hacia otros miembros. Mantén el respeto y revisa las normas de convivencia.">
                                        <div class="menu-link-icon"><span class="material-symbols-rounded" style="color:#f57c00">psychology_alt</span></div>
                                        <div class="menu-link-text">Comportamiento Tóxico</div>
                                    </div>

                                    <div class="menu-link" data-action="select-notif-template" 
                                         data-level="urgent" 
                                         data-color="#d32f2f"
                                         data-message="Has compartido contenido que no está permitido en los canales públicos (NSFW/Violencia). Esto es una advertencia formal.">
                                        <div class="menu-link-icon"><span class="material-symbols-rounded" style="color:#d32f2f">18_up_rating</span></div>
                                        <div class="menu-link-text">Contenido Inapropiado (NSFW)</div>
                                    </div>

                                    <div class="menu-link" data-action="select-notif-template" 
                                         data-level="urgent" 
                                         data-color="#d32f2f"
                                         data-message="Está prohibido enviar publicidad no solicitada (invitaciones a otros servidores) por mensaje directo a nuestros miembros.">
                                        <div class="menu-link-icon"><span class="material-symbols-rounded" style="color:#d32f2f">campaign</span></div>
                                        <div class="menu-link-text">Publicidad / DM Ads</div>
                                    </div>

                                    <div style="padding: 8px 12px; font-size: 11px; font-weight: 700; color: #999; text-transform: uppercase; border-top: 1px solid #eee; margin-top: 4px;">Gestión</div>

                                    <div class="menu-link" data-action="select-notif-template" 
                                         data-level="info" 
                                         data-color="#1976d2"
                                         data-message="Tu nombre de usuario infringe nuestras políticas. Por favor, cámbialo lo antes posible para evitar sanciones.">
                                        <div class="menu-link-icon"><span class="material-symbols-rounded" style="color:#1976d2">badge</span></div>
                                        <div class="menu-link-text">Nombre de Usuario</div>
                                    </div>

                                    <div class="menu-link" data-action="select-notif-template" 
                                         data-level="warning" 
                                         data-color="#f57c00"
                                         data-message="Un administrador ha restablecido tu foto de perfil debido a que incumplía las normas de la comunidad.">
                                        <div class="menu-link-icon"><span class="material-symbols-rounded" style="color:#f57c00">image_not_supported</span></div>
                                        <div class="menu-link-text">Foto de Perfil Restablecida</div>
                                    </div>

                                    <div style="padding: 8px 12px; font-size: 11px; font-weight: 700; color: #999; text-transform: uppercase; border-top: 1px solid #eee; margin-top: 4px;">Soporte</div>

                                    <div class="menu-link" data-action="select-notif-template" 
                                         data-level="info" 
                                         data-color="#1976d2"
                                         data-message="El reporte o ticket de soporte que abriste ha sido revisado y cerrado por un administrador. Gracias por colaborar.">
                                        <div class="menu-link-icon"><span class="material-symbols-rounded" style="color:#1976d2">confirmation_number</span></div>
                                        <div class="menu-link-text">Ticket Cerrado</div>
                                    </div>

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
                        <h2 class="component-card__title">Mensaje a Enviar</h2>
                        <p class="component-card__description">Puedes editar el mensaje antes de enviarlo. Se mostrará en tiempo real.</p>
                    </div>
                    <div id="notif-level-indicator" class="component-badge component-badge--neutral" style="margin-left: auto;">
                        <span id="notif-level-label">General</span>
                    </div>
                </div>
                
                <div class="component-input-wrapper w-100" style="margin-top: 10px;">
                    <textarea id="input-notif-message" 
                              class="component-text-input full-width" 
                              style="height: 100px; padding: 10px;" 
                              placeholder="Escribe un mensaje personalizado o selecciona una plantilla arriba..."></textarea>
                </div>
                
                <div class="component-card__actions w-100" style="justify-content: flex-end; margin-top: 10px;">
                    <button type="button" class="component-button primary" id="btn-send-notification">
                        <span class="material-symbols-rounded" style="font-size: 18px;">send</span>
                        Enviar Notificación
                    </button>
                </div>
            </div>
        </div>

    </div>
</div>