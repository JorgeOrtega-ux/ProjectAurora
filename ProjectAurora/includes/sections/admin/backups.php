<?php
if (session_status() === PHP_SESSION_NONE) session_start();
$role = $_SESSION['user_role'] ?? 'user';
if (!in_array($role, ['founder', 'administrator', 'admin'])) {
    include __DIR__ . '/../system/404.php'; 
    exit;
}
?>
<div class="section-content overflow-y active" data-section="admin/backups">
    <h1>Copias de Seguridad</h1>
    <p>Gestiona y restaura copias de la base de datos.</p>
</div>