<?php
// includes/sections/auth/account-status.php

// Obtenemos el tipo de estado desde la URL (?type=suspended o ?type=deleted)
$type = $_GET['type'] ?? 'unknown';

// Definimos los datos a mostrar según el caso
$icon = 'help';
$color = '#666';
$title = 'Estado de Cuenta';
$desc = 'No se pudo determinar el estado de tu cuenta.';
$btnText = __('auth.recover.back_login');
$btnLink = $basePath . 'login';

if ($type === 'deleted') {
    $icon = 'delete_forever';
    $color = '#d32f2f'; // Rojo
    $title = __('auth.status.deleted_title');
    $desc = __('auth.status.deleted_desc');
} elseif ($type === 'suspended') {
    $icon = 'block';
    $color = '#f57c00'; // Naranja
    $title = __('auth.status.suspended_title');
    $desc = __('auth.status.suspended_desc');
    $btnText = __('auth.status.contact_support');
    $btnLink = '#'; // Aquí podrías poner un mailto: o link a soporte real
}

?>

<div class="auth-wrapper">
    <div class="auth-card">
        
        <div style="margin-bottom: 24px;">
            <span class="material-symbols-rounded" style="font-size: 64px; color: <?php echo $color; ?>;">
                <?php echo $icon; ?>
            </span>
        </div>

        <div class="auth-header">
            <h1 style="color: <?php echo $color; ?>;"><?php echo $title; ?></h1>
            <p><?php echo $desc; ?></p>
        </div>
        
        <?php if ($type === 'deleted'): ?>
            <div class="alert error" style="margin-top: 20px; font-size: 13px;">
                Por razones de seguridad y privacidad, los datos eliminados permanentemente no pueden ser recuperados por ningún medio.
            </div>
        <?php endif; ?>

        <a href="<?php echo $btnLink; ?>" class="btn-primary" style="display: flex; justify-content: center; align-items: center; text-decoration: none; margin-top: 24px;">
            <?php echo $btnText; ?>
        </a>

        <?php if ($type === 'suspended'): ?>
            <div class="auth-footer">
                <p><a href="<?php echo $basePath; ?>login"><?= __('auth.recover.back_login') ?></a></p>
            </div>
        <?php endif; ?>

    </div>
</div>