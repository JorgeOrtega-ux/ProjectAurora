<?php
// includes/sections/settings/delete-account.php

if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../../config/database/db.php';

// Detectar idioma activo
$currentLang = isset($langToLoad) ? $langToLoad : (isset($_SESSION['language']) ? $_SESSION['language'] : 'es-419');

// --- HELPER PARA FECHAS BASADO EN CLAVES DE TRADUCCIÓN ---
if (!function_exists('format_date_with_keys')) {
    function format_date_with_keys($dateString, $langCode) {
        if (!$dateString) return '';
        $ts = strtotime($dateString);
        
        $day  = date('j', $ts);
        $year = date('Y', $ts);
        $monthNameEng = strtolower(date('F', $ts));
        $monthKey = "global.month." . $monthNameEng;
        $keyDe  = "global.date_de";
        $keyDel = "global.date_del";

        $shortLang = substr($langCode, 0, 2);

        if ($shortLang === 'en') {
            return __($monthKey) . " " . $day . ", " . $year;
        } else {
            return $day . " " . __($keyDe) . " " . __($monthKey) . " " . __($keyDel) . " " . $year;
        }
    }
}

// Obtener fecha de creación y formatear mensaje
$deleteAccountMsg = "";
if (isset($pdo) && isset($_SESSION['user_id'])) {
    $stmt = $pdo->prepare("SELECT created_at FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $createdDate = $stmt->fetchColumn();
    
    if ($createdDate) {
        $dateStr = format_date_with_keys($createdDate, $currentLang);
        $deleteAccountMsg = __("settings.security.delete_msg_part1") . " " . $dateStr . " " . __("settings.security.delete_msg_part2");
    }
}
?>
<div class="section-content active" data-section="settings/delete-account">
    <div class="component-wrapper">
        
        <div class="component-header-card" style="border-color: #ffcdd2; background-color: #ffebee;">
            <h1 class="component-page-title" style="color: #d32f2f;">Eliminar cuenta</h1>
            <p class="component-page-description" style="color: #b71c1c;">Esta acción es irreversible. Por favor lee cuidadosamente.</p>
        </div>
        
        <div style="margin-bottom: 16px;">
             <button class="component-button" data-nav="settings/login-and-security">
                <span class="material-symbols-rounded">arrow_back</span> Volver
             </button>
        </div>

        <div class="component-card">
            
            <h2 class="component-card__title" style="margin-bottom: 12px;">¿Qué sucederá?</h2>
            <ul style="margin-bottom: 24px; padding-left: 20px; font-size: 14px; color: #555; line-height: 1.6;">
                <li>Tu perfil, fotos, archivos y configuraciones serán <strong>eliminados permanentemente</strong>.</li>
                <li>No podrás recuperar el acceso a tu cuenta una vez confirmes.</li>
                <li>Se cerrará la sesión en todos los dispositivos donde hayas ingresado.</li>
                <li><strong>No podrás volver a crear una cuenta nueva con este mismo correo electrónico.</strong></li>
            </ul>

            <h2 class="component-card__title" style="margin-bottom: 12px;">Confirmación de Seguridad</h2>
            
            <p class="component-card__description" style="margin-bottom: 16px;">
                <?php echo htmlspecialchars($deleteAccountMsg); ?>
            </p>

            <div class="component-input-wrapper" style="margin-bottom: 24px;">
                <input type="password" id="delete-account-password" class="component-text-input" placeholder="Ingresa tu contraseña actual para continuar">
            </div>

            <div class="component-group-item" style="border: 1px solid #eee; border-radius: 8px; padding: 16px; margin-bottom: 24px;">
                <div class="component-card__content">
                    <div class="component-card__text">
                        <h2 class="component-card__title">Entiendo las consecuencias</h2>
                        <p class="component-card__description">Confirmo que he leído lo anterior y deseo proceder.</p>
                    </div>
                </div>
                <div class="component-card__actions actions-right">
                    <label class="component-toggle-switch">
                        <input type="checkbox" id="confirm-delete-account">
                        <span class="component-toggle-slider"></span>
                    </label>
                </div>
            </div>

            <div class="component-card__actions actions-right">
                 <button class="component-button" data-nav="settings/login-and-security">Cancelar</button>
                 <button class="component-button danger" id="btn-final-delete-account" disabled style="opacity: 0.5; cursor: not-allowed;">
                    Eliminar permanentemente
                </button>
            </div>

        </div>
    </div>
</div>