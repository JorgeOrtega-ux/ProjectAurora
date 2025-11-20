<?php
if (session_status() === PHP_SESSION_NONE) session_start();
// Doble seguridad por si se llama directo por fetch sin pasar por router
$role = $_SESSION['user_role'] ?? 'user';
if (!in_array($role, ['founder', 'administrator', 'admin'])) {
    include __DIR__ . '/../system/404.php';
    exit;
}
?>
<div class="section-content overflow-y active" data-section="admin/dashboard">
    <h1>Dashboard Administrativo</h1>
    <p>Bienvenido al panel de control.</p>
</div>