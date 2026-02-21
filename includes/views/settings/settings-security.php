<?php 
if (session_status() === PHP_SESSION_NONE) session_start(); 

// --- LÓGICA: OBTENER FECHAS DESDE LA BASE DE DATOS ---
$lastPasswordUpdate = null;
$accountCreationDate = null;

if (isset($_SESSION['user_id'])) {
    global $dbConnection;
    if (isset($dbConnection)) {
        $userId = $_SESSION['user_id'];

        // Array para convertir los números de mes a texto en español
        $meses = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];

        try {
            // 1. Obtener la fecha de creación de la cuenta
            $stmtUser = $dbConnection->prepare("SELECT fecha FROM users WHERE id = :id LIMIT 1");
            $stmtUser->execute([':id' => $userId]);
            $userData = $stmtUser->fetch(PDO::FETCH_ASSOC);
            
            if ($userData && !empty($userData['fecha'])) {
                $timestampUser = strtotime($userData['fecha']);
                $diaUser = date('j', $timestampUser);
                $mesUser = $meses[date('n', $timestampUser) - 1];
                $anioUser = date('Y', $timestampUser);
                $accountCreationDate = "$diaUser de $mesUser de $anioUser";
            }

            // 2. Obtener la fecha de la última actualización de contraseña
            $stmtLog = $dbConnection->prepare("
                SELECT changed_at 
                FROM user_changes_log 
                WHERE user_id = :id AND modified_field = 'contrasena' 
                ORDER BY changed_at DESC 
                LIMIT 1
            ");
            $stmtLog->execute([':id' => $userId]);
            $logData = $stmtLog->fetch(PDO::FETCH_ASSOC);
            
            if ($logData && !empty($logData['changed_at'])) {
                $timestampLog = strtotime($logData['changed_at']);
                $diaLog = date('j', $timestampLog);
                $mesLog = $meses[date('n', $timestampLog) - 1];
                $anioLog = date('Y', $timestampLog);
                $lastPasswordUpdate = "$diaLog de $mesLog de $anioLog";
            }
        } catch (\Throwable $e) {
            // Fallback silencioso en caso de error
        }
    }
}
// ------------------------------------------------------------
?>
<div class="view-content">
    <div class="component-wrapper">
        <input type="hidden" id="csrf_token_settings" value="<?= $_SESSION['csrf_token'] ?? ''; ?>">
        
        <div class="component-header-card">
            <h1 class="component-page-title"><?= t('settings.security.title') ?></h1>
            <p class="component-page-description"><?= t('settings.security.desc') ?></p>
        </div>

        <div class="component-card--grouped">
            <div class="component-group-item" data-component="password-update-section" style="flex-direction: column; align-items: flex-start;">
                
                <div class="component-card__content" style="width: 100%;">
                    <div style="display: flex; align-items: center; justify-content: center; width: 40px; height: 40px; border-radius: 8px; border: 1px solid var(--border-color); background-color: var(--bg-surface-alt); flex-shrink: 0;">
                        <span class="material-symbols-rounded" style="color: var(--text-secondary); font-size: 20px;">lock</span>
                    </div>
                    <div class="component-card__text">
                        <h2 class="component-card__title"><?= t('settings.security.pass_title') ?></h2>
                        <p class="component-card__description">
                            <?= $lastPasswordUpdate 
                                ? t('settings.security.pass_desc_updated', ['last_update' => $lastPasswordUpdate]) 
                                : t('settings.security.pass_desc_never') 
                            ?>
                        </p>
                    </div>
                </div>

                <div class="component-card__actions actions-right active w-100" data-state="password-stage-0" style="justify-content: flex-end; margin-top: 8px;">
                    <button type="button" class="component-button" data-action="pass-start-flow">
                        <?= t('settings.security.pass_btn_change') ?>
                    </button>
                </div>

                <div class="w-100 component-stage-form disabled" data-state="password-stage-1" style="margin-top: 16px;">
                    <div class="component-input-wrapper">
                        <input type="password" class="component-text-input has-action" id="current-password-input" placeholder=" ">
                        <label for="current-password-input" class="component-label-floating"><?= t('settings.security.pass_placeholder_current') ?></label>
                        <button type="button" class="component-input-action" tabindex="-1">
                            <span class="material-symbols-rounded">visibility</span>
                        </button>
                    </div>

                    <div class="component-card__actions actions-right" style="margin-top: 12px;">
                        <button type="button" class="component-button" data-action="pass-cancel-flow"><?= t('settings.security.pass_btn_cancel') ?></button>
                        <button type="button" class="component-button primary" data-action="pass-go-step-2"><?= t('settings.security.pass_btn_continue') ?></button>
                    </div>
                </div>

                <div class="w-100 component-stage-form disabled" data-state="password-stage-2" style="margin-top: 16px;">
                    <div class="component-input-wrapper" style="margin-bottom: 8px;">
                        <input type="password" class="component-text-input has-action" id="new-password-input" placeholder=" ">
                        <label for="new-password-input" class="component-label-floating"><?= t('settings.security.pass_placeholder_new') ?></label>
                        <button type="button" class="component-input-action" tabindex="-1">
                            <span class="material-symbols-rounded">visibility</span>
                        </button>
                    </div>
                    <div class="component-input-wrapper">
                        <input type="password" class="component-text-input has-action" id="repeat-password-input" placeholder=" ">
                        <label for="repeat-password-input" class="component-label-floating"><?= t('settings.security.pass_placeholder_repeat') ?></label>
                        <button type="button" class="component-input-action" tabindex="-1">
                            <span class="material-symbols-rounded">visibility</span>
                        </button>
                    </div>

                    <div class="component-card__actions actions-right" style="margin-top: 12px;">
                        <button type="button" class="component-button" data-action="pass-cancel-flow"><?= t('settings.security.pass_btn_cancel') ?></button>
                        <button type="button" class="component-button primary" data-action="pass-submit-final"><?= t('settings.security.pass_btn_save') ?></button>
                    </div>
                </div>
            </div>
            
            <hr class="component-divider">

            <div class="component-group-item">
                <div class="component-card__content">
                    <div style="display: flex; align-items: center; justify-content: center; width: 40px; height: 40px; border-radius: 8px; border: 1px solid var(--border-color); background-color: var(--bg-surface-alt); flex-shrink: 0;">
                        <span class="material-symbols-rounded" style="color: var(--text-secondary); font-size: 20px;">shield</span>
                    </div>
                    <div class="component-card__text">
                        <h2 class="component-card__title"><?= t('settings.security.2fa_title') ?></h2>
                        <p class="component-card__description"><?= t('settings.security.2fa_desc') ?></p>
                    </div>
                </div>

                <div class="component-card__actions actions-right">
                    <button type="button" class="component-button primary" data-nav="/ProjectAurora/settings/2fa-setup">
                        <?= t('settings.security.2fa_btn_setup') ?>
                    </button>
                </div>
            </div>

        </div>

        <div class="component-card--grouped">
            <div class="component-group-item">
                <div class="component-card__content">
                    <div style="display: flex; align-items: center; justify-content: center; width: 40px; height: 40px; border-radius: 8px; border: 1px solid var(--border-color); background-color: var(--bg-surface-alt); flex-shrink: 0;">
                        <span class="material-symbols-rounded" style="color: var(--text-secondary); font-size: 20px;">devices</span>
                    </div>
                    <div class="component-card__text">
                        <h2 class="component-card__title"><?= t('settings.security.devices_title') ?></h2>
                        <p class="component-card__description"><?= t('settings.security.devices_desc') ?></p>
                    </div>
                </div>

                <div class="component-card__actions actions-right">
                    <button type="button" class="component-button" data-nav="/ProjectAurora/settings/devices">
                        <?= t('settings.security.devices_btn_manage') ?>
                    </button>
                </div>
            </div>
        </div>

        <div class="component-card--grouped">
            <div class="component-group-item">
                <div class="component-card__content">
                    <div class="component-card__text">
                        <h2 class="component-card__title" style="color: var(--action-danger);"><?= t('settings.security.delete_title') ?></h2>
                        <p class="component-card__description">
                            <?= $accountCreationDate 
                                ? t('settings.security.delete_desc_date', ['creation_date' => $accountCreationDate]) 
                                : t('settings.security.delete_desc_nodate') 
                            ?>
                        </p>
                    </div>
                </div>

                <div class="component-card__actions actions-right">
                    <button type="button" class="component-button" style="color: var(--action-danger); border-color: var(--action-danger);" data-nav="/ProjectAurora/settings/delete-account">
                        <?= t('settings.security.delete_btn') ?>
                    </button>
                </div>
            </div>
        </div>

    </div>
</div>