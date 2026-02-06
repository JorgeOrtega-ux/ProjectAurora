<?php
// includes/sections/system/status-screen.php

// === 0. INICIALIZACIÓN DE EMERGENCIA ===
// Esto permite que este archivo funcione incluso si el sistema global ha fallado.
$basePath = $basePath ?? '/ProjectAurora/';

if (!isset($i18n)) {
    // Si el sistema de idiomas murió, creamos un "doble" simple para que el código no falle.
    $i18n = new class { 
        public function t($k) { return $k; } 
    };
}

// 1. Detectar el contexto (Mantenimiento vs Estado de Cuenta vs Error Crítico)
$mode = 'unknown';
$data = [];

// A) Error Crítico (Inyectado desde Utils::showGenericErrorPage)
if (isset($isSystemError) && $isSystemError === true) {
    $mode = 'system_error';
}
// B) Mantenimiento (Variable inyectada desde index.php o loader.php)
elseif (isset($isMaintenanceContext) && $isMaintenanceContext === true) {
    $mode = 'maintenance';
} 
// C) Estado de Cuenta (Datos en sesión flash)
elseif (isset($_SESSION['account_status_data'])) {
    $data = $_SESSION['account_status_data'];
    $mode = $data['status']; // 'suspended' o 'deleted'
} 
// D) Fallback de seguridad
else {
    // Si se accede directamente sin contexto válido, redirigir al login
    if (!headers_sent()) {
        header("Location: " . $basePath . "login");
    } else {
        echo "<script>window.location.href = '" . $basePath . "login';</script>";
    }
    exit;
}

// 2. Configuración Visual Dinámica
$config = [
    'title' => '',
    'desc' => '',
    'icon' => 'info',
    'icon_color' => 'var(--text-primary)',
    'bg_color' => 'var(--bg-hover-light)',
    'show_reason' => false,
    'show_timer' => false
];

switch ($mode) {
    case 'system_error':
        $config['title'] = 'Error del Sistema';
        $config['desc'] = 'Ha ocurrido un error inesperado. El incidente ha sido registrado y notificado automáticamente.';
        $config['icon'] = 'dns'; // Icono genérico de servidor
        $config['icon_color'] = '#dc2626'; // Rojo oscuro
        $config['bg_color'] = '#fef2f2';
        break;

    case 'maintenance':
        $config['title'] = 'En Mantenimiento';
        $config['desc'] = 'Estamos realizando mejoras importantes en la plataforma.<br>Por favor, vuelve a intentarlo más tarde.';
        $config['icon'] = 'engineering';
        $config['icon_color'] = 'var(--text-primary)'; // Negro/Blanco según tema
        $config['bg_color'] = 'var(--bg-hover-light)';
        break;

    case 'suspended':
        $config['title'] = $i18n->t('account_status.suspended_title');
        $config['desc'] = $i18n->t('account_status.suspended_desc');
        $config['icon'] = 'block';
        $config['icon_color'] = '#ed6c02'; // Naranja
        $config['bg_color'] = '#fff3e0';   // Fondo naranja muy claro
        $config['show_reason'] = true;
        $config['show_timer'] = true;
        break;

    case 'deleted':
        $config['title'] = $i18n->t('account_status.deleted_title');
        $config['desc'] = $i18n->t('account_status.deleted_desc');
        $config['icon'] = 'delete_forever';
        $config['icon_color'] = '#d32f2f'; // Rojo
        $config['bg_color'] = '#ffebee';   // Fondo rojo muy claro
        $config['show_reason'] = true;
        break;
        
    default:
        // Caso de error en la lógica del switch
        if ($mode === 'system_error') die("Error Crítico");
        echo "<script>window.location.href = '" . $basePath . "login';</script>";
        exit;
}

// 3. Preparación de datos adicionales (Fechas, Razón)
$reasonText = $data['reason'] ?? '';
$suspensionEnd = $data['suspension_ends_at'] ?? null;
$dateString = '';

if ($mode === 'suspended' && $suspensionEnd) {
    $ts = strtotime($suspensionEnd);
    $isFuture = $ts > time();
    if ($isFuture) {
        $dateString = date('d/m/Y H:i', $ts);
    } else {
        $suspensionEnd = null; 
    }
}
?>

<div class="component-layout-centered">
    <div class="component-card component-card--compact" style="text-align: center; padding: 40px 24px;">
        
        <div style="margin-bottom: 24px;">
            <div style="width: 80px; height: 80px; background-color: <?php echo $config['bg_color']; ?>; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto;">
                <span class="material-symbols-rounded" style="font-size: 40px; color: <?php echo $config['icon_color']; ?>;">
                    <?php echo $config['icon']; ?>
                </span>
            </div>
        </div>

        <h1 class="component-page-title" style="margin-bottom: 12px; color: <?php echo ($mode === 'maintenance' || $mode === 'system_error') ? 'var(--text-primary)' : $config['icon_color']; ?>;">
            <?php echo $config['title']; ?>
        </h1>
        
        <p class="component-page-description" style="margin-bottom: 24px;">
            <?php echo $config['desc']; ?>
        </p>

        <?php if ($config['show_reason'] && !empty($reasonText)): ?>
            <div style="background-color: var(--bg-hover-light); padding: 16px; border-radius: 8px; text-align: left; margin-bottom: 24px; border: 1px solid var(--border-light);">
                <p style="font-size: 13px; font-weight: 600; color: var(--text-secondary); margin-bottom: 4px;">
                    <?php echo $i18n->t('account_status.reason_label'); ?>
                </p>
                <p style="font-size: 14px; color: var(--text-primary); line-height: 1.5;">
                    <?php echo htmlspecialchars($reasonText); ?>
                </p>
            </div>
        <?php endif; ?>

        <?php if ($config['show_timer']): ?>
            <div style="margin-bottom: 32px;">
                <p style="font-size: 13px; color: var(--text-secondary);">
                    <?php if (empty($suspensionEnd)): ?>
                        <strong><?php echo $i18n->t('account_status.permanent_label'); ?></strong>
                    <?php else: ?>
                        <?php echo $i18n->t('account_status.until_label'); ?> 
                        <strong style="color: var(--text-primary);"><?php echo $dateString; ?></strong>
                    <?php endif; ?>
                </p>
            </div>
        <?php endif; ?>

        <div style="display: flex; flex-direction: column; gap: 12px;">
            <?php if ($mode !== 'maintenance' && $mode !== 'system_error'): ?>
                <p style="font-size: 12px; color: var(--text-tertiary); margin-top: 8px;">
                    <?php echo $i18n->t('account_status.contact_support'); ?>
                </p>
            <?php endif; ?>
        </div>

    </div>
</div>