<?php
// includes/sections/main.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    exit;
}
?>
<div class="section-content overflow-y active" data-section="main">
    <h1>Página Principal (Main)</h1>
    <p>Bienvenido, <?php echo htmlspecialchars($_SESSION['user_email'] ?? ''); ?>.</p>
    <p>Estás navegando en modo SPA híbrido.</p>
    
    <div style="margin-top: 20px; height: 800px; background: linear-gradient(#fff, #f0f0f0); border-radius: 8px; padding: 12px;">
        <p>Contenido scrolleable...</p>
    </div>
</div>