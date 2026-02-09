<?php
// includes/sections/app/main.php

// Datos simulados para la demo
$fakeCodes = ['8392-1029', '4810-5821', '9401-5912', '1029-4819', '5912-9102', '3819-5812', '1290-4912', '5819-2019', '9123-5810', '2049-1029'];
?>

<div class="component-wrapper" style="padding: 20px; max-width: 800px; margin: 0 auto;">

    <div class="component-card component-card--grouped">
        
        <div class="component-group-item" data-component="recovery-codes-section" style="display: block;">
            
            <div class="component-card__content" style="margin-bottom: 24px;">
                <div class="component-card__icon-container component-card__icon-container--bordered">
                    <span class="material-symbols-rounded">vibration</span>
                </div>

                <div class="component-card__text">
                    <h2 class="component-card__title">Códigos de Emergencia</h2>
                    <p class="component-card__description">Usa estos códigos si pierdes acceso a tu 2FA. Cada uno es de un solo uso.</p>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); gap: 12px;">
                <?php foreach ($fakeCodes as $code): ?>
                    <div class="recovery-chip" onclick="copyCode('<?php echo $code; ?>')" style="
                        background: #ffffff; 
                        border: 1px solid var(--border-light); 
                        border-radius: 8px; 
                        padding: 10px 14px; 
                        display: flex; 
                        align-items: center; 
                        justify-content: space-between;
                        cursor: pointer;
                        transition: border-color 0.2s ease;
                    ">
                        <span style="font-family: var(--sl-font-mono); font-size: 14px; font-weight: 600; color: var(--text-primary); letter-spacing: 0.5px;">
                            <?php echo $code; ?>
                        </span>
                        <span class="material-symbols-rounded" style="font-size: 16px; color: var(--text-tertiary);">content_copy</span>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="component-card__actions actions-right actions-force-end" style="margin-top: 20px;">
                <button class="component-button primary" style="font-size: 13px;">Copiar todos</button>
            </div>
        </div>

    </div>
</div>

<style>
    /* Efecto hover minimalista: solo borde negro */
    .recovery-chip:hover {
        border-color: #000000 !important;
        /* Se eliminan transform, shadow y background-color previos */
    }
    
    .recovery-chip:active {
        background-color: var(--bg-hover-light) !important;
    }

    /* Adaptación para pantallas móviles pequeñas */
    @media (max-width: 480px) {
        div[style*="grid-template-columns"] {
            grid-template-columns: 1fr 1fr !important;
        }
    }
</style>

<script>
    function copyCode(code) {
        navigator.clipboard.writeText(code).then(() => {
            if(window.ToastManager) {
                window.ToastManager.show('Código copiado al portapapeles', 'info');
            }
        });
    }
</script>