<?php
// includes/sections/system/maintenance.php
$icon = 'engineering';
$color = '#0277bd'; 
$title = __('system.maintenance.title');
$desc = __('system.maintenance.desc');
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
        
        <div class="alert info" style="margin-top: 20px; font-size: 13px;">
             Estamos realizando mejoras en nuestra infraestructura. Volveremos pronto.
        </div>
        
        <div class="auth-footer">
            <p><a href="<?php echo $basePath; ?>login" style="font-weight: 600;">Acceso Administrativo</a></p>
        </div>
    </div>
</div>