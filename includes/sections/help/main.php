<?php
// includes/sections/help/main.php
?>
<div class="page-header">
    <h1>Centro de Ayuda</h1>
    <p>Bienvenido al soporte de Project Aurora. ¿Cómo podemos ayudarte hoy?</p>
</div>

<div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 16px; margin-top: 24px;">
    
    <div style="padding: 24px; border: 1px solid #eee; border-radius: 12px; cursor: pointer;" onclick="document.querySelector('[data-nav=feedback]').click()">
        <span class="material-symbols-rounded" style="font-size: 32px; color: #000;">chat</span>
        <h3 style="margin-top: 12px;">Enviar Comentarios</h3>
        <p style="color: #666; font-size: 14px;">Reporta un error o sugiere una mejora.</p>
    </div>

    <div style="padding: 24px; border: 1px solid #eee; border-radius: 12px; cursor: pointer;" onclick="document.querySelector('[data-nav=privacy]').click()">
        <span class="material-symbols-rounded" style="font-size: 32px; color: #000;">lock</span>
        <h3 style="margin-top: 12px;">Privacidad</h3>
        <p style="color: #666; font-size: 14px;">Cómo manejamos tus datos.</p>
    </div>

</div>