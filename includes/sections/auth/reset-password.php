<?php
// includes/sections/reset-password.php

$token = $_GET['token'] ?? '';
$isValidToken = false;
$errorMessage = "";
$targetEmail = ""; 

// Verificación Server-Side
if (!empty($token) && isset($pdo)) {
    try {
        $stmt = $pdo->prepare("SELECT email FROM password_resets WHERE token = ? AND expires_at > NOW() LIMIT 1");
        $stmt->execute([$token]);
        $result = $stmt->fetch();
        
        if ($result) {
            $isValidToken = true;
            $targetEmail = $result['email'];
        }
    } catch (Exception $e) {
        error_log("Error validating reset token: " . $e->getMessage());
    }
}

// ... (Bloque de manejo de errores JSON si no es válido) ...
if (!$isValidToken) {
    $errorData = [
        "error" => [
            "message" => "Invalid request. Token not found or expired.",
            "type" => "invalid_request_error",
            "param" => "token",
            "code" => "token_invalid"
        ]
    ];
    $errorMessage = "Route Error (400): " . json_encode($errorData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
}
?>
<div class="component-layout-centered">
    <div class="component-card component-card--compact">
        
        <?php if (!$isValidToken): ?>
            <div class="crash-header">
                <span class="material-symbols-rounded crash-icon">data_object</span>
                <h1 class="crash-title">Bad Request</h1>
            </div>

            <div class="crash-code-box" style="white-space: pre-wrap; font-family: monospace; font-size: 13px; color: #333; background-color: #f5f5f5; border: 1px solid #e0e0e0;">
<?php echo htmlspecialchars($errorMessage); ?>
            </div>

            <div style="margin-top: 24px; text-align: center;">
                <a href="<?php echo $basePath; ?>login" class="component-button component-button--large primary" style="text-decoration: none;">
                    <?php echo $i18n->t('auth.reset.btn_login'); ?>
                </a>
            </div>

        <?php else: ?>
            <div class="component-header-centered">
                <h1><?php echo $i18n->t('auth.reset.title'); ?></h1>
                
                <p>
                    <?php echo $i18n->t('auth.reset.desc'); ?> para: <br>
                    <strong style="color: var(--text-primary);"><?php echo htmlspecialchars($targetEmail); ?></strong>
                </p>
            </div>
            
            <input type="hidden" id="reset_token" value="<?php echo htmlspecialchars($token); ?>">

            <div class="component-stage-form">
                <div class="component-form-group">
                    <div class="component-input-wrapper component-input-wrapper--floating">
                        <input type="password" id="new_password" class="component-text-input has-action" required placeholder=" ">
                        <label for="new_password" class="component-label-floating"><?php echo $i18n->t('auth.reset.field_new'); ?></label>
                        <button type="button" class="component-input-action" data-action="toggle-password" tabindex="-1">
                            <span class="material-symbols-rounded">visibility</span>
                        </button>
                    </div>
                    
                    <div class="component-input-wrapper component-input-wrapper--floating">
                        <input type="password" id="confirm_password" class="component-text-input has-action" required placeholder=" ">
                        <label for="confirm_password" class="component-label-floating"><?php echo $i18n->t('auth.reset.field_confirm'); ?></label>
                        
                        <button type="button" class="component-input-action" data-action="toggle-password" tabindex="-1">
                            <span class="material-symbols-rounded">visibility</span>
                        </button>
                    </div>
                </div>
            </div>

            <button type="button" id="btn-submit-new-password" class="component-button component-button--large primary"><?php echo $i18n->t('auth.reset.btn_change'); ?></button>

        <?php endif; ?>
    </div>
</div>