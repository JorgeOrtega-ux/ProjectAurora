<?php
// includes/sections/system/status-screen.php

// 1. Detectar el contexto (Mantenimiento vs Estado de Cuenta)
$mode = 'unknown';
$data = [];

// A) Mantenimiento (Variable inyectada desde index.php o loader.php)
if (isset($isMaintenanceContext) && $isMaintenanceContext === true) {
    $mode = 'maintenance';
} 
// B) Estado de Cuenta (Datos en sesión flash)
elseif (isset($_SESSION['account_status_data'])) {
    $data = $_SESSION['account_status_data'];
    $mode = $data['status']; // 'suspended' o 'deleted'
} 
// C) Fallback de seguridad
else {
    // Si se accede directamente sin contexto, redirigir al login
    echo "<script>window.location.href = '" . $basePath . "login';</script>";
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
    'show_timer' => false,
    'actions' => []
];

switch ($mode) {
    case 'maintenance':
        $config['title'] = 'En Mantenimiento';
        $config['desc'] = 'Estamos realizando mejoras importantes en la plataforma.<br>Por favor, vuelve a intentarlo más tarde.';
        $config['icon'] = 'engineering';
        $config['icon_color'] = 'var(--action-primary)'; // Negro/Blanco según tema
        $config['bg_color'] = 'var(--bg-hover-light)';
        $config['actions'][] = [
            'type' => 'refresh',
            'text' => 'Recargar página',
            'class' => 'primary'
        ];
        break;

    case 'suspended':
        $config['title'] = $i18n->t('account_status.suspended_title');
        $config['desc'] = $i18n->t('account_status.suspended_desc');
        $config['icon'] = 'block';
        $config['icon_color'] = '#ed6c02'; // Naranja
        $config['bg_color'] = '#fff3e0';   // Fondo naranja muy claro
        $config['show_reason'] = true;
        $config['show_timer'] = true;
        $config['actions'][] = [
            'type' => 'logout',
            'text' => $i18n->t('account_status.btn_logout'),
            'class' => 'primary'
        ];
        break;

    case 'deleted':
        $config['title'] = $i18n->t('account_status.deleted_title');
        $config['desc'] = $i18n->t('account_status.deleted_desc');
        $config['icon'] = 'delete_forever';
        $config['icon_color'] = '#d32f2f'; // Rojo
        $config['bg_color'] = '#ffebee';   // Fondo rojo muy claro
        $config['show_reason'] = true;
        $config['actions'][] = [
            'type' => 'logout',
            'text' => $i18n->t('account_status.btn_logout'),
            'class' => 'primary'
        ];
        break;
        
    default:
        // Caso de error
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
        // Si ya expiró pero sigue aquí, es indefinida o error, tratamos como indefinida visualmente
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

        <h1 class="component-page-title" style="margin-bottom: 12px; color: <?php echo ($mode === 'maintenance') ? 'var(--text-primary)' : $config['icon_color']; ?>;">
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
            <?php foreach ($config['actions'] as $action): ?>
                <?php if ($action['type'] === 'logout'): ?>
                    <button type="button" class="component-button <?php echo $action['class']; ?> component-button--large" data-action="logout">
                        <?php echo $action['text']; ?>
                    </button>
                <?php elseif ($action['type'] === 'refresh'): ?>
                    <button type="button" class="component-button <?php echo $action['class']; ?> component-button--large" onclick="window.location.reload()">
                        <?php echo $action['text']; ?>
                    </button>
                <?php endif; ?>
            <?php endforeach; ?>
            
            <?php if ($mode !== 'maintenance'): ?>
                <p style="font-size: 12px; color: var(--text-tertiary); margin-top: 8px;">
                    <?php echo $i18n->t('account_status.contact_support'); ?>
                </p>
            <?php endif; ?>
        </div>

    </div>
</div>