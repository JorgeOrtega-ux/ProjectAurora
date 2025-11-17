<?php
// includes/sections/status-page.php

// Como la sesión se destruyó en el router, obtenemos el motivo por GET
$status = $_GET['status'] ?? 'suspended';

// Lógica de visualización según estado
$title = "Cuenta Suspendida";
$message = "Tu cuenta ha sido suspendida temporalmente por incumplir nuestras normas.";
$icon = "block"; // Icono material symbols
$color = "#d32f2f"; // Rojo

if ($status === 'deleted') {
    $title = "Cuenta Eliminada";
    $message = "Esta cuenta ha sido eliminada permanentemente. Si consideras que es un error, contacta a soporte.";
    $icon = "delete_forever";
    $color = "#616161"; // Gris oscuro
}
?>

<div class="section-content overflow-y active" data-section="status-page">
    <div class="section-center-wrapper">
        
        <div class="form-container" style="text-align: center; padding-top: 0;">
            
            <div style="margin-bottom: 20px;">
                <span class="material-symbols-rounded" style="font-size: 80px; color: <?php echo $color; ?>;">
                    <?php echo $icon; ?>
                </span>
            </div>

            <h1 style="margin-bottom: 15px; color: <?php echo $color; ?>; font-size: 28px;">
                <?php echo $title; ?>
            </h1>
            
            <p style="color: #555; line-height: 1.6; font-size: 16px; margin-bottom: 40px;">
                <?php echo $message; ?>
            </p>
            
            <div>
                <a href="<?php echo isset($basePath) ? $basePath : '/ProjectAurora/'; ?>login" style="color: #888; text-decoration: none; font-size: 14px; font-weight: 500;">
                    <span class="material-symbols-rounded" style="font-size: 16px; vertical-align: text-bottom;">arrow_back</span> 
                    Volver al inicio
                </a>
            </div>

        </div>

    </div>
</div>