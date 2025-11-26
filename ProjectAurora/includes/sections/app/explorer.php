<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    exit;
}
?>
<div class="section-content active" data-section="explorer">
    <div class="component-wrapper">
        
        <div class="component-header-card" style="text-align: left;">
            <h1 class="component-page-title">Explorar</h1>
            <p class="component-page-description">Descubre comunidades públicas y únete.</p>
        </div>

        <div id="public-communities-list" class="mt-16" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 16px;">
            <div class="small-spinner" style="margin: 40px auto;"></div>
        </div>

    </div>
</div>