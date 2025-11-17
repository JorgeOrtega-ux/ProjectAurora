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
</div>