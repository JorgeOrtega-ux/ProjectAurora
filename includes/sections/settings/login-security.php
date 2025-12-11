<?php
// includes/sections/settings/login-security.php

if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../../config/database/db.php';

// Detectar idioma
$currentLang = isset($langToLoad) ? $langToLoad : (isset($_SESSION['language']) ? $_SESSION['language'] : 'es-419');

// --- HELPER PARA FECHAS BASADO EN CLAVES DE TRADUCCIÓN ---
if (!function_exists('format_date_with_keys')) {
    function format_date_with_keys($dateString, $langCode) {
        if (!$dateString) return '';
        $ts = strtotime($dateString);
        
        $day  = date('j', $ts);
        $year = date('Y', $ts);
        
        // Obtenemos el nombre del mes en inglés (lowercase) para usarlo como parte de la clave
        // Ejemplo: "january", "february"...
        $monthNameEng = strtolower(date('F', $ts));
        
        // Clave de traducción del mes (ej: global.month.december)
        $monthKey = "global.month." . $monthNameEng;
        
        // Claves de conectores
        $keyDe  = "global.date_de";
        $keyDel = "global.date_del";

        // Estructura según idioma
        $shortLang = substr($langCode, 0, 2);

        if ($shortLang === 'en') {
            // Formato Inglés: [Month] [Day], [Year]
            // Usamos __($monthKey) para que si no hay traducción, salga "global.month.december" (o lo que tengas en el json inglés)
            return __($monthKey) . " " . $day . ", " . $year;
        } else {
            // Formato Español/Default: [Day] [de] [Month] [del] [Year]
            return $day . " " . __($keyDe) . " " . __($monthKey) . " " . __($keyDel) . " " . $year;
        }
    }
}

// --- LÓGICA DE DATOS ---
$is2faEnabled = false;
$passMsg = __("settings.security.pass_never"); 
$accountCreatedText = "";
$deleteAccountMsg = "";

if (isset($pdo) && isset($_SESSION['user_id'])) {
    $userId = $_SESSION['user_id'];

    // 1. Datos de usuario
    $stmt = $pdo->prepare("SELECT two_factor_enabled, created_at FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $userRow = $stmt->fetch();
    
    if ($userRow) {
        $is2faEnabled = (bool)$userRow['two_factor_enabled'];
        if (!empty($userRow['created_at'])) {
            $dateStr = format_date_with_keys($userRow['created_at'], $currentLang);
            
            // Construimos el mensaje concatenando traducciones y la fecha procesada
            $deleteAccountMsg = __("settings.security.delete_msg_part1") . " " . $dateStr . " " . __("settings.security.delete_msg_part2");
        }
    }

    // 2. Última contraseña
    $stmtLog = $pdo->prepare("SELECT created_at FROM security_logs WHERE user_identifier = ? AND action_type = 'pass_change_success' ORDER BY id DESC LIMIT 1");
    $stmtLog->execute(["uid_" . $userId]);
    $logRow = $stmtLog->fetch();

    if ($logRow && !empty($logRow['created_at'])) {
        $lastDate = format_date_with_keys($logRow['created_at'], $currentLang);
        $passMsg = __("settings.security.pass_updated_prefix") . " " . $lastDate;
    }
}
?>
<div class="section-content active" data-section="settings/login-and-security">
    <div class="component-wrapper">
        
        <div class="component-header-card">
            <h1 class="component-page-title"><?= __('settings.security.title') ?></h1>
            <p class="component-page-description"><?= __('settings.security.desc') ?></p>
        </div>

        <div class="component-card component-card--grouped">

            <div class="component-group-item" data-component="password-update-section">
                
                <div class="component-card__content">
                    <div class="component-card__profile-picture component-card__profile-picture--bordered">
                        <span class="material-symbols-rounded" style="font-size: 32px; color: #000;">lock</span>
                    </div>

                    <div class="component-card__text">
                        <h2 class="component-card__title"><?= __('settings.security.pass_title') ?></h2>
                        <p class="component-card__description" style="color: #666;"><?php echo htmlspecialchars($passMsg); ?></p>
                    </div>
                </div>

                <div class="component-card__actions actions-right active" data-state="password-stage-0">
                    <button type="button" class="component-button" data-action="pass-start-flow">
                        <?= __('settings.security.btn_update') ?>
                    </button>
                </div>

                <div class="disabled w-100 component-stage-form" data-state="password-stage-1">
                    <div class="component-input-wrapper">
                        <input type="password" class="component-text-input" 
                               id="current-password-input"
                               placeholder="<?= __('settings.security.current_pass_ph') ?>">
                    </div>

                    <div class="component-card__actions actions-right actions-force-end">
                        <button type="button" class="component-button" data-action="pass-cancel-flow"><?= __('global.cancel') ?></button>
                        <button type="button" class="component-button primary" data-action="pass-go-step-2"><?= __('global.continue') ?></button>
                    </div>
                </div>

                <div class="disabled w-100 component-stage-form" data-state="password-stage-2">
                    <div class="component-input-wrapper">
                        <input type="password" class="component-text-input" 
                               id="new-password-input"
                               placeholder="<?= __('settings.security.new_pass_ph') ?>">
                    </div>
                    <div class="component-input-wrapper">
                        <input type="password" class="component-text-input" 
                               id="repeat-password-input"
                               placeholder="<?= __('settings.security.repeat_pass_ph') ?>">
                    </div>

                    <div class="component-card__actions actions-right actions-force-end">
                        <button type="button" class="component-button" data-action="pass-cancel-flow"><?= __('global.cancel') ?></button>
                        <button type="button" class="component-button primary" data-action="pass-submit-final"><?= __('global.continue') ?></button>
                    </div>
                </div>
            </div>
            
            <hr class="component-divider">

            <div class="component-group-item">
                <div class="component-card__content">
                    <div class="component-card__profile-picture component-card__profile-picture--bordered">
                        <span class="material-symbols-rounded" style="font-size: 32px; color: #000;">shield</span>
                    </div>

                    <div class="component-card__text">
                        <h2 class="component-card__title">Autenticación de dos pasos (2FA)</h2>
                        <p class="component-card__description">
                            <?php if ($is2faEnabled): ?>
                                <span style="color: green; font-weight: bold;">Activado.</span> Tu cuenta está protegida.
                            <?php else: ?>
                                Añade una capa extra de seguridad a tu cuenta.
                            <?php endif; ?>
                        </p>
                    </div>
                </div>

                <div class="component-card__actions actions-right">
                    <?php if ($is2faEnabled): ?>
                        <button type="button" class="component-button danger" data-nav="settings/2fa-setup">
                            Desactivar
                        </button>
                    <?php else: ?>
                        <button type="button" class="component-button primary" data-nav="settings/2fa-setup">
                            Activar 2FA
                        </button>
                    <?php endif; ?>
                </div>
            </div>

        </div>

        <div class="component-card component-card--grouped">
            <div class="component-group-item">
                <div class="component-card__content">
                    <div class="component-card__profile-picture component-card__profile-picture--bordered">
                        <span class="material-symbols-rounded" style="font-size: 32px; color: #000;">devices</span>
                    </div>

                    <div class="component-card__text">
                        <h2 class="component-card__title"><?= __('settings.security.devices_title') ?></h2>
                        <p class="component-card__description"><?= __('settings.security.devices_desc') ?></p>
                    </div>
                </div>

                <div class="component-card__actions actions-right">
                    <button type="button" class="component-button" data-nav="settings/devices">
                        <?= __('settings.security.btn_manage') ?>
                    </button>
                </div>
            </div>
        </div>

        <div class="component-card component-card--grouped">
            <div class="component-group-item">
                <div class="component-card__content">
                    <div class="component-card__text">
                        <h2 class="component-card__title" style="color: #d32f2f;">Eliminar cuenta</h2>
                        <p class="component-card__description">
                            <?php echo htmlspecialchars($deleteAccountMsg); ?>
                        </p>
                    </div>
                </div>

                <div class="component-card__actions actions-right">
                    <button type="button" class="component-button danger" data-nav="settings/delete-account">
                        Eliminar cuenta
                    </button>
                </div>
            </div>
        </div>

    </div>
</div>