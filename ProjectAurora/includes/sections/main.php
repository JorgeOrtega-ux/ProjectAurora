<?php
// Verificar si hay sesión activa.
// Si main.php se carga por 'include' en index.php, la sesión ya estará iniciada.
// Pero si se llama directo por fetch/AJAX, necesitamos verificar.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    // Si no hay sesión, devolvemos error 401 (No autorizado)
    // Tu javascript (url-manager.js) detectará el error y recargará la página,
    // lo que activará el router y mandará al usuario al login.
    http_response_code(401);
    exit('Acceso denegado');
}
?>
<div class="section-content overflow-y active" data-section="main">
    <h1>Página Principal (Main)</h1>
    <p>Bienvenido, usuario registrado.</p>
    <p>Este contenido es exclusivo para ti.</p>
</div>