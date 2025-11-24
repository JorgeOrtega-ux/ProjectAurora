<?php
if (session_status() === PHP_SESSION_NONE) session_start();
$role = $_SESSION['user_role'] ?? 'user';
// [CORREGIDO]
if (!in_array($role, ['founder', 'administrator'])) {
    include __DIR__ . '/../system/404.php'; 
    exit;
}
?>
<div class="section-content active" data-section="admin/backups">
    <h1 data-i18n="admin.backups_title"><?php echo translation('admin.backups_title'); ?></h1>
    <p data-i18n="admin.backups_desc"><?php echo translation('admin.backups_desc'); ?></p>
</div>