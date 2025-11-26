<?php
if (session_status() === PHP_SESSION_NONE) session_start();
?>
<div class="section-content active" data-section="join-community">
    <div class="section-center-wrapper">
        <div class="form-container" style="text-align: center;">
            
            <div style="margin-bottom: 20px;">
                <span class="material-symbols-rounded" style="font-size: 64px; color: #000;">groups</span>
            </div>

            <h1 style="font-size: 24px; margin-bottom: 10px;">Unirse a una Comunidad</h1>
            <p style="color: #666; margin-bottom: 30px;">Ingresa el código de acceso de 12 dígitos que te proporcionaron.</p>

            <div class="floating-label-group">
                <input 
                    type="text" 
                    data-input="community-code" 
                    class="floating-input" 
                    required 
                    placeholder=" "
                    maxlength="14" 
                    style="letter-spacing: 2px; text-transform: uppercase; text-align: center; font-weight: 700;"
                >
                <label class="floating-label" style="left: 50%; transform: translate(-50%, -50%);">XXXX-XXXX-XXXX</label>
            </div>

            <button class="form-button primary" data-action="submit-join-community" style="margin-top: 20px;">
                Unirse
            </button>

            <div class="form-footer-link" style="margin-top: 20px;">
                <a href="#" data-nav="explorer" style="display: inline-flex; align-items: center; gap: 5px;">
                    <span class="material-symbols-rounded" style="font-size: 16px;">explore</span>
                    O explora comunidades públicas
                </a>
            </div>

        </div>
    </div>
</div>