<?php
if (session_status() === PHP_SESSION_NONE) session_start();

// [MODIFICADO] Capturamos el nombre de la comunidad si viene en la URL
$requestedCommunity = isset($_GET['community']) ? htmlspecialchars($_GET['community']) : null;
?>
<div class="section-content active" data-section="join-community">
    <div class="component-wrapper">

        <div class="component-header-card">
            <h1 class="component-page-title">Unirse a una Comunidad</h1>
            <p class="component-page-description">Ingresa un código de invitación para acceder a un grupo privado.</p>
        </div>

        <div class="component-card component-card--grouped">
            <div class="component-group-item component-group-item--stacked-right">
                
                <div class="component-card__content">
                    <div class="component-icon-container">
                        <span class="material-symbols-rounded">vpn_key</span>
                    </div>
                    <div class="component-card__text">
                        <h2 class="component-card__title">Código de Acceso</h2>
                        <p class="component-card__description">Ingresa el código de 12 dígitos (XXXX-XXXX-XXXX) que te proporcionaron.</p>
                    </div>
                </div>
                
                <div class="component-input-wrapper w-100">
                    <input type="text" 
                           class="component-text-input full-width" 
                           data-input="community-code" 
                           placeholder="Ej: A1B2-C3D4-E5F6"
                           maxlength="14"
                           style="letter-spacing: 2px; text-transform: uppercase; font-weight: 600;">
                </div>

                <div class="component-card__actions" style="flex-direction: column; align-items: flex-end;">
                    <button type="button" class="component-button primary" data-action="submit-join-community">
                        Unirse
                    </button>
                    
                    <?php if ($requestedCommunity): ?>
                        <div style="margin-top:20px; padding-top:15px; border-top:1px solid #eee; width:100%; display:flex; flex-direction:column; align-items:flex-end;">
                            <p style="font-size:0.9rem; color:#444; margin-bottom:10px; text-align:right;">
                                ¿Estás intentando entrar a <strong><?php echo $requestedCommunity; ?></strong>?
                            </p>
                            <button type="button" 
                                    class="component-button secondary" 
                                    data-action="request-access" 
                                    data-community-name="<?php echo $requestedCommunity; ?>">
                                Solicitar Acceso
                            </button>
                        </div>
                    <?php endif; ?>
                </div>

            </div>
        </div>

        <div style="text-align: center; margin-top: 20px;">
            <a href="#" data-nav="explorer" style="color:#666; text-decoration:none; display:inline-flex; align-items:center; gap:5px; font-size:14px; font-weight:500;">
                <span class="material-symbols-rounded" style="font-size:18px;">explore</span> 
                O explora comunidades públicas
            </a>
        </div>

    </div>
</div>