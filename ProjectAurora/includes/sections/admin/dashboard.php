<?php
if (session_status() === PHP_SESSION_NONE) session_start();
$role = $_SESSION['user_role'] ?? 'user';
if (!in_array($role, ['founder', 'administrator', 'admin'])) {
    include __DIR__ . '/../system/404.php';
    exit;
}
?>
<div class="section-content active" data-section="admin/dashboard">
    <h1 data-i18n="admin.dashboard_title"><?php echo trans('admin.dashboard_title'); ?></h1>
    <p data-i18n="admin.dashboard_desc"><?php echo trans('admin.dashboard_desc'); ?></p>
</div>