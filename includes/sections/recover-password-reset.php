<?php
// includes/sections/recover-password-reset.php

// 1. Validar que tengamos token desde la URL (router)
if (!isset($resetToken) || empty($resetToken)) {
    echo "<script>window.location.href = '" . $basePath . "login';</script>";
    exit;
}

// 2. Validar contra la Base de Datos (Seguridad)
// Verificamos si el token existe Y si no ha expirado.
// Si ya fue usado, no existirá porque la API lo borra al cambiar la contraseña.
$isValidToken = false;

try {
    // $pdo viene de includes/db.php (cargado previamente en index.php/router.php)
    $stmt = $pdo->prepare("SELECT id FROM password_resets WHERE token = ? AND expires_at > NOW() LIMIT 1");
    $stmt->execute([$resetToken]);
    
    if ($stmt->rowCount() > 0) {
        $isValidToken = true;
    }
} catch (Exception $e) {
    // En caso de error de BD, asumimos inválido por seguridad
    $isValidToken = false;
}
?>

<div class="auth-wrapper">
    <div class="auth-card">
        
        <?php if ($isValidToken): ?>
            <div class="auth-header">
                <h1>Nueva Contraseña</h1>
                <p>Crea una contraseña segura para tu cuenta.</p>
            </div>
            
            <input type="hidden" id="reset-token" value="<?php echo htmlspecialchars($resetToken); ?>">
            
            <div class="form-groups-wrapper">
                <div class="form-group">
                    <input type="password" id="new-password" required placeholder=" ">
                    <label for="new-password">Nueva contraseña</label>
                </div>
                 <div class="form-group">
                    <input type="password" id="confirm-password" required placeholder=" ">
                    <label for="confirm-password">Confirmar contraseña</label>
                </div>
            </div>

            <button type="button" id="btn-recover-reset" class="btn-primary">Cambiar Contraseña</button>

            <div class="auth-footer">
                <p><a href="<?php echo $basePath; ?>login">Cancelar</a></p>
            </div>

        <?php else: ?>
            <div class="auth-header">
                <span class="material-symbols-rounded" style="font-size: 48px; color: #d32f2f; margin-bottom: 10px;">link_off</span>
                <h1 style="color: #d32f2f;">Enlace no válido</h1>
                <p>Este enlace de recuperación ya fue utilizado o ha expirado.</p>
            </div>

            <div class="alert error" style="margin-top: 20px;">
                Por seguridad, los enlaces tienen un tiempo límite de validez y son de un solo uso.
            </div>

            <a href="<?php echo $basePath; ?>recover-password" class="btn-primary" style="display: block; text-decoration: none; line-height: 55px; margin-top: 20px; color: #fff;">
                Solicitar uno nuevo
            </a>

            <div class="auth-footer">
                <p><a href="<?php echo $basePath; ?>login">Volver al inicio</a></p>
            </div>
        <?php endif; ?>

    </div>
</div>