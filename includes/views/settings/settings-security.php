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
            $stmtUser = $dbConnection->prepare("SELECT created_at FROM users WHERE id = :id LIMIT 1");
            $stmtUser->execute([':id' => $userId]);
            $userData = $stmtUser->fetch(PDO::FETCH_ASSOC);
            
            if ($userData && !empty($userData['created_at'])) {
                $timestampUser = strtotime($userData['created_at']);
                $diaUser = date('j', $timestampUser);
                $mesUser = $meses[date('n', $timestampUser) - 1];
                $anioUser = date('Y', $timestampUser);
                $accountCreationDate = "$diaUser de $mesUser de $anioUser";
            }

            // 2. Obtener la fecha de la última actualización de contraseña
            $stmtLog = $dbConnection->prepare("
                SELECT changed_at 
                FROM user_changes_log 
                WHERE user_id = :id AND modified_field = 'password' 
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
            <div class="component-group-item" data-component="password-update-section">
                
                <div class="component-card__content">
                    <div>
                        <span class="material-symbols-rounded">lock</span>
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

                <div class="component-card__actions actions-right active" data-state="password-stage-0">
                    <button type="button" class="component-button" data-action="pass-start-flow">
                        <?= t('settings.security.pass_btn_change') ?>
                    </button>
                </div>

                <div class="component-stage-form disabled" data-state="password-stage-1">
                    <div class="component-input-wrapper">
                        <input type="password" class="component-text-input has-action" id="current-password-input" placeholder=" ">
                        <label for="current-password-input" class="component-label-floating"><?= t('settings.security.pass_placeholder_current') ?></label>
                        <button type="button" class="component-input-action" tabindex="-1">
                            <span class="material-symbols-rounded">visibility</span>
                        </button>
                    </div>

                    <div class="component-card__actions actions-right">
                        <button type="button" class="component-button" data-action="pass-cancel-flow"><?= t('settings.security.pass_btn_cancel') ?></button>
                        <button type="button" class="component-button primary" data-action="pass-go-step-2"><?= t('settings.security.pass_btn_continue') ?></button>
                    </div>
                </div>

                <div class="component-stage-form disabled" data-state="password-stage-2">
                    <div class="component-input-wrapper">
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

                    <div class="component-card__actions actions-right">
                        <button type="button" class="component-button" data-action="pass-cancel-flow"><?= t('settings.security.pass_btn_cancel') ?></button>
                        <button type="button" class="component-button primary" data-action="pass-submit-final"><?= t('settings.security.pass_btn_save') ?></button>
                    </div>
                </div>
            </div>
            
            <hr class="component-divider">

            <div class="component-group-item">
                <div class="component-card__content">
                    <div>
                        <span class="material-symbols-rounded">shield</span>
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
                    <div>
                        <span class="material-symbols-rounded">devices</span>
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
                        <h2 class="component-card__title"><?= t('settings.security.delete_title') ?></h2>
                        <p class="component-card__description">
                            <?= $accountCreationDate 
                                ? t('settings.security.delete_desc_date', ['creation_date' => $accountCreationDate]) 
                                : t('settings.security.delete_desc_nodate') 
                            ?>
                        </p>
                    </div>
                </div>

                <div class="component-card__actions actions-right">
                    <button type="button" class="component-button" data-nav="/ProjectAurora/settings/delete-account">
                        <?= t('settings.security.delete_btn') ?>
                    </button>
                </div>
            </div>
        </div>

    </div>
</div>