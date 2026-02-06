<?php
// includes/sections/system/security-lock.php
?>
<style>
    /* Estilos específicos para esta vista */
    .security-lock-wrapper {
        min-height: 70vh;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .security-lock-icon-container {
        display: flex;
        justify-content: center;
        margin-bottom: 16px;
    }
    .security-lock-icon {
        font-size: 64px;
        color: var(--color-error, #d32f2f);
    }
    /* Asegurar que el enlace se comporte visualmente como un botón */
    a.component-button {
        text-decoration: none;
        color: var(--text-primary);
        display: flex;
        align-items: center;
        justify-content: center;
    }
    a.component-button:hover {
        color: var(--text-primary);
        text-decoration: none;
    }
</style>

<div class="component-wrapper security-lock-wrapper" data-section="security-lock">
    
    <div class="component-card component-card--compact">
        
        <div class="component-header-centered">
            <div class="security-lock-icon-container">
                <span class="material-symbols-rounded security-lock-icon">gpp_maybe</span>
            </div>

            <h1 class="component-page-title">Autenticación Requerida</h1>
            
            <p class="component-page-description">
                Por políticas de seguridad, el acceso al Panel de Administración está restringido únicamente a cuentas con la Verificación de Dos Pasos (2FA) activa.
            </p>
        </div>

        <div class="mt-24 w-100">
            <a href="<?php echo $basePath; ?>settings/login-security" class="component-button w-100">
                Configurar 2FA Ahora
            </a>
        </div>

    </div>

</div>