<?php
if (session_status() === PHP_SESSION_NONE) session_start();
$role = $_SESSION['user_role'] ?? 'user';
if (!in_array($role, ['founder', 'administrator', 'admin'])) {
    include __DIR__ . '/../system/404.php'; 
    exit;
}
?>
<div class="section-content active" data-section="admin/server">
    <h1>Configuración del Servidor</h1>
    <p>Logs, variables de entorno y estado del sistema.</p>
</div>