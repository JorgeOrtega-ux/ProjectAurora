<?php
// includes/sections/settings/login-security.php

if (session_status() === PHP_SESSION_NONE) session_start();
// Aseguramos la conexión a la BD por si este archivo se carga en un contexto diferente
require_once __DIR__ . '/../../../config/database/db.php';

// --- HELPER DE FECHAS (FORMATO SOLICITADO) ---
if (!function_exists('format_date_es')) {
    function format_date_es($dateString) {
        if (!$dateString) return '';
        $timestamp = strtotime($dateString);
        // Array de meses en español
        $months = [
            "enero", "febrero", "marzo", "abril", "mayo", "junio", 
            "julio", "agosto", "septiembre", "octubre", "noviembre", "diciembre"
        ];
        $day = date('j', $timestamp);
        $monthIndex = date('n', $timestamp) - 1;
        $year = date('Y', $timestamp);
        
        // Formato solicitado: "24 octubre del 2025"
        return "$day " . $months[$monthIndex] . " del $year";
    }
}

// --- LÓGICA DE DATOS ---
$is2faEnabled = false;
$passMsg = __("settings.security.pass_never"); // Valor por defecto traducido
$accountCreatedText = "";

if (isset($pdo) && isset($_SESSION['user_id'])) {
    $userId = $_SESSION['user_id'];

    // 1. Obtener datos del usuario (2FA y Fecha de Creación)
    $stmt = $pdo->prepare("SELECT two_factor_enabled, created_at FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $userRow = $stmt->fetch();
    
    if ($userRow) {
        $is2faEnabled = (bool)$userRow['two_factor_enabled'];
        if (!empty($userRow['created_at'])) {
            $accountCreatedText = format_date_es($userRow['created_at']);
        }
    }

    // 2. Obtener fecha del último cambio de contraseña desde security_logs
    // Buscamos el evento 'pass_change_success' más reciente para este usuario
    $stmtLog = $pdo->prepare("SELECT created_at FROM security_logs WHERE user_identifier = ? AND action_type = 'pass_change_success' ORDER BY id DESC LIMIT 1");
    // Nota: El identificador en logs suele ser "uid_" + ID
    $stmtLog->execute(["uid_" . $userId]);
    $logRow = $stmtLog->fetch();

    if ($logRow && !empty($logRow['created_at'])) {
        $lastDate = format_date_es($logRow['created_at']);
        // Concatenamos la traducción con la fecha
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
                            <?php 
                                echo __("settings.security.delete_msg_part1") . " " . 
                                     htmlspecialchars($accountCreatedText) . " " . 
                                     __("settings.security.delete_msg_part2"); 
                            ?>
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