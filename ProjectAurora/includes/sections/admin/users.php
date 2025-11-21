<?php
if (session_status() === PHP_SESSION_NONE) session_start();
$role = $_SESSION['user_role'] ?? 'user';
if (!in_array($role, ['founder', 'administrator', 'admin'])) {
    include __DIR__ . '/../system/404.php'; 
    exit;
}
?>
<div class="section-content overflow-y active" data-section="admin/users">
    <h1>Gestión de Usuarios</h1>
    <p>Administra los usuarios registrados en la plataforma.</p>
    </div>