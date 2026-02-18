<?php http_response_code(404); ?>
<div class="view-content animate-fade-in">
    <div style="text-align: center; padding: 40px 20px;">
        <span class="material-symbols-rounded" style="font-size: 64px; color: #ff4444; margin-bottom: 16px;">
            error
        </span>
        <h1 style="font-size: 24px; margin-bottom: 8px;">Página no encontrada</h1>
        <p style="color: #666; margin-bottom: 24px;">
            Lo sentimos, la URL <strong><?php echo htmlspecialchars($_SERVER['REQUEST_URI'] ?? ''); ?></strong> no existe en Project Aurora.
        </p>
        
        <div class="component-actions" style="justify-content: center;">
            <div class="component-button nav-item" data-nav="/ProjectAurora/">
                <span class="material-symbols-rounded" style="margin-right: 8px; font-size: 20px;">home</span>
                <span>Volver al inicio</span>
            </div>
        </div>
    </div>
</div>