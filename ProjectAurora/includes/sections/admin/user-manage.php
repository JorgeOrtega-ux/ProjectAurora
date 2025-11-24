<?php
// includes/sections/admin/user-manage.php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!in_array($_SESSION['user_role'], ['founder', 'administrator'])) {
    include __DIR__ . '/../system/404.php'; exit;
}

$targetUid = $_GET['uid'] ?? 0;
$basePath = isset($GLOBALS['basePath']) ? $GLOBALS['basePath'] : '/ProjectAurora/';
?>

<link rel="stylesheet" href="<?php echo $basePath; ?>assets/css/admin.css">

<div class="section-content active" data-section="admin/user-manage">
    
    <div class="toolbar-stack">
        <div class="component-toolbar">
            <div class="component-toolbar__group">
                <div class="component-icon-button" data-nav="admin/users" data-i18n-tooltip="global.back" data-tooltip="<?php echo trans('global.back'); ?>">
                    <span class="material-symbols-rounded">arrow_back</span>
                </div>
                <div class="component-toolbar__separator"></div>
                <span style="font-size: 14px; font-weight: 600; color: #666;" data-i18n="global.status"><?php echo trans('global.status'); ?></span>
            </div>
            <div class="component-toolbar__right">
                <button class="component-icon-button" id="btn-save-manage" 
                        data-i18n-tooltip="global.save_status" 
                        data-tooltip="<?php echo trans('global.save_status'); ?>">
                    <span class="material-symbols-rounded">save</span>
                </button>
            </div>
        </div>
    </div>

    <div class="component-wrapper section-with-toolbar">

        <div class="component-header-card">
            <h1 class="component-page-title" data-i18n="admin.manage.title"><?php echo trans('admin.manage.title'); ?></h1>
            <p class="component-page-description" data-i18n="admin.manage.desc"><?php echo trans('admin.manage.desc'); ?></p>
        </div>

        <div class="component-card component-card--grouped">
            <div class="component-group-item">
                <div class="component-card__content">
                    <div class="component-card__profile-picture" id="manage-pfp-container">
                        <img src="" id="manage-user-avatar" class="component-card__avatar-image" style="display:none;">
                        <span class="material-symbols-rounded default-avatar-icon" id="manage-user-icon" style="font-size: 24px;">person</span>
                    </div>
                    <div class="component-card__text">
                        <h2 class="component-card__title" id="manage-username" data-i18n="global.loading"><?php echo trans('global.loading'); ?></h2>
                        <p class="component-card__description" id="manage-email">...</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="component-card component-card--grouped" style="margin-top: 16px;">
            <input type="hidden" id="manage-target-id" value="<?php echo htmlspecialchars($targetUid); ?>">
            
            <input type="hidden" id="manage-status-value" value="active">
            <input type="hidden" id="manage-deletion-type" value="admin_decision">

            <div class="component-group-item component-group-item--stacked">
                <div class="component-card__content">
                    <div class="component-card__text">
                        <h2 class="component-card__title" data-i18n="admin.manage.status_label"><?php echo trans('admin.manage.status_label'); ?></h2>
                        <p class="component-card__description" data-i18n="admin.manage.status_desc"><?php echo trans('admin.manage.status_desc'); ?></p>
                    </div>
                </div>
                <div class="component-card__actions w-100">
                    <div class="trigger-select-wrapper w-100">
                        <div class="trigger-selector" data-action="toggle-dropdown" data-target="dropdown-manage-status">
                            <div class="trigger-select-icon">
                                <span class="material-symbols-rounded" id="manage-status-icon" style="color: #2e7d32;">check_circle</span>
                            </div>
                            <div class="trigger-select-text">
                                <span id="manage-status-text" data-i18n="global.active"><?php echo trans('global.active'); ?></span>
                            </div>
                            <div class="trigger-select-arrow">
                                <span class="material-symbols-rounded">arrow_drop_down</span>
                            </div>
                        </div>
                        
                        <div class="popover-module popover-module--anchor-width body-title disabled" id="dropdown-manage-status">
                            <div class="menu-content">
                                <div class="menu-list">
                                    <div class="menu-link active" 
                                         data-action="select-manage-status" 
                                         data-value="active" 
                                         data-label="<?php echo trans('global.active'); ?>" 
                                         data-icon="check_circle" 
                                         data-color="#2e7d32">
                                        <div class="menu-link-icon"><span class="material-symbols-rounded" style="color:#2e7d32">check_circle</span></div>
                                        <div class="menu-link-text" data-i18n="global.active"><?php echo trans('global.active'); ?></div>
                                        <div class="menu-link-icon"><span class="material-symbols-rounded">check</span></div>
                                    </div>
                                    <div class="menu-link" 
                                         data-action="select-manage-status" 
                                         data-value="deleted" 
                                         data-label="<?php echo trans('global.deleted'); ?>" 
                                         data-icon="delete_forever" 
                                         data-color="#616161">
                                        <div class="menu-link-icon"><span class="material-symbols-rounded" style="color:#616161">delete_forever</span></div>
                                        <div class="menu-link-text" data-i18n="global.deleted"><?php echo trans('global.deleted'); ?></div>
                                        <div class="menu-link-icon"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div id="wrapper-deletion-details" class="w-100 d-none">
                <hr class="component-divider">
                
                <div class="component-group-item component-group-item--stacked">
                    <div class="component-card__content">
                        <div class="component-card__text">
                            <h2 class="component-card__title" data-i18n="admin.manage.decision_label"><?php echo trans('admin.manage.decision_label'); ?></h2>
                            <p class="component-card__description" data-i18n="admin.manage.decision_desc"><?php echo trans('admin.manage.decision_desc'); ?></p>
                        </div>
                    </div>
                    <div class="component-card__actions w-100">
                        <div class="trigger-select-wrapper w-100">
                            <div class="trigger-selector" data-action="toggle-dropdown" data-target="dropdown-deletion-type">
                                <div class="trigger-select-icon">
                                    <span class="material-symbols-rounded">gavel</span>
                                </div>
                                <div class="trigger-select-text">
                                    <span id="text-deletion-type" data-i18n="admin.manage.admin_dec"><?php echo trans('admin.manage.admin_dec'); ?></span>
                                </div>
                                <div class="trigger-select-arrow">
                                    <span class="material-symbols-rounded">arrow_drop_down</span>
                                </div>
                            </div>
                            
                            <div class="popover-module popover-module--anchor-width body-title disabled" id="dropdown-deletion-type">
                                <div class="menu-content">
                                    <div class="menu-list">
                                        <div class="menu-link active" 
                                             data-action="select-deletion-type" 
                                             data-value="admin_decision" 
                                             data-label="<?php echo trans('admin.manage.admin_dec'); ?>">
                                            <div class="menu-link-icon"><span class="material-symbols-rounded">admin_panel_settings</span></div>
                                            <div class="menu-link-text" data-i18n="admin.manage.admin_dec"><?php echo trans('admin.manage.admin_dec'); ?></div>
                                            <div class="menu-link-icon"><span class="material-symbols-rounded">check</span></div>
                                        </div>
                                        <div class="menu-link" 
                                             data-action="select-deletion-type" 
                                             data-value="user_decision" 
                                             data-label="<?php echo trans('admin.manage.user_dec'); ?>">
                                            <div class="menu-link-icon"><span class="material-symbols-rounded">person</span></div>
                                            <div class="menu-link-text" data-i18n="admin.manage.user_dec"><?php echo trans('admin.manage.user_dec'); ?></div>
                                            <div class="menu-link-icon"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div id="wrapper-user-reason" class="d-none">
                    <hr class="component-divider">
                    <div class="component-group-item component-group-item--stacked">
                        <div class="component-card__text">
                            <h2 class="component-card__title" data-i18n="admin.manage.user_reason_label"><?php echo trans('admin.manage.user_reason_label'); ?></h2>
                            <p class="component-card__description" data-i18n="admin.manage.user_reason_desc"><?php echo trans('admin.manage.user_reason_desc'); ?></p>
                        </div>
                        <div class="component-input-wrapper w-100" style="margin-top: 10px;">
                            <textarea id="input-user-reason" 
                                      class="component-text-input full-width" 
                                      style="height: 80px; padding: 10px;" 
                                      data-i18n-placeholder="admin.manage.user_reason_placeholder"
                                      placeholder="<?php echo trans('admin.manage.user_reason_placeholder'); ?>"></textarea>
                        </div>
                    </div>
                </div>

                <hr class="component-divider">
                <div class="component-group-item component-group-item--stacked">
                    <div class="component-card__text">
                        <h2 class="component-card__title" data-i18n="admin.manage.admin_comments_label"><?php echo trans('admin.manage.admin_comments_label'); ?></h2>
                        <p class="component-card__description" data-i18n="admin.manage.admin_comments_desc"><?php echo trans('admin.manage.admin_comments_desc'); ?></p>
                    </div>
                    <div class="component-input-wrapper w-100" style="margin-top: 10px;">
                        <textarea id="input-admin-comments" 
                                  class="component-text-input full-width" 
                                  style="height: 80px; padding: 10px;" 
                                  data-i18n-placeholder="admin.manage.admin_comments_placeholder"
                                  placeholder="<?php echo trans('admin.manage.admin_comments_placeholder'); ?>"></textarea>
                    </div>
                </div>

            </div>
        </div>

        <div class="component-header-card" style="margin-top: 32px;">
            <h1 class="component-page-title">Centro de Notificaciones</h1>
            <p class="component-page-description">Envía alertas rápidas o mensajes directos al usuario.</p>
        </div>

        <div class="component-card component-card--grouped">
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