<?php
// includes/sections/explorer.php

// Si se llama por AJAX, la sesión no está iniciada automáticamente, así que la iniciamos.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Protección simple: si no hay usuario, error 401 (el JS lo capturará)
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    exit;
}
?>
<div class="section-content overflow-y active" data-section="explorer">
    <h1>Explorar Comunidades</h1>
    <p>Descubre nuevas comunidades y únete a la conversación.</p>
    
    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 16px; margin-top: 24px;">
        <div style="background: #fff; padding: 16px; border-radius: 12px; border: 1px solid #eee;">
            <h3>Comunidad de Diseño</h3>
            <p style="color: #666; font-size: 14px;">Un espacio para creativos.</p>
        </div>
        <div style="background: #fff; padding: 16px; border-radius: 12px; border: 1px solid #eee;">
            <h3>Desarrolladores PHP</h3>
            <p style="color: #666; font-size: 14px;">Hablemos de código backend.</p>
        </div>
    </div>
</div>