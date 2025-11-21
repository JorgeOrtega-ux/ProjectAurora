<?php
if (session_status() === PHP_SESSION_NONE) session_start();
?>

<div class="section-content active" data-section="forgot-password">
    <div class="section-center-wrapper">
        <div class="form-container">
            
            <div class="auth-back-link">
                <a href="#" onclick="event.preventDefault(); navigateTo('login')" style="color:#666; text-decoration:none; display:flex; align-items:center; gap:5px; font-size:14px;">
                    <span class="material-symbols-rounded" style="font-size:18px;">arrow_back</span> Volver
                </a>
            </div>

            <div data-step="rec-1" class="auth-step-container active">
                <h1>Recuperar Cuenta</h1>
                <p>Ingresa tu correo y te enviaremos un enlace mágico.</p>
                
                <div class="floating-label-group">
                    <input type="email" data-input="rec-email" class="floating-input" required placeholder=" ">
                    <label class="floating-label">Correo Electrónico</label>
                </div>

                <button class="form-button" data-action="rec-step1">Enviar Enlace</button>
                <div data-error="rec-1" class="form-error-message"></div>
            </div>

            <div data-step="rec-success" class="auth-step-container">
                <div style="text-align:center; padding:20px 0;">
                    <span class="material-symbols-rounded" style="font-size:64px; color:#4caf50;">mark_email_read</span>
                </div>
                <h1>¡Enlace Enviado!</h1>
                <p>Revisa tu correo <strong data-display="rec-email"></strong> (y la carpeta de spam).</p>
                <p style="font-size:14px; color:#888; margin-top:10px;">
                    Haz clic en el enlace del correo para crear tu nueva contraseña.
                </p>
            </div>

        </div>
    </div>
</div>