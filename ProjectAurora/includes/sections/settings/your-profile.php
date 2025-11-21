<?php
if (session_status() === PHP_SESSION_NONE) session_start();
?>
<div class="section-content overflow-y active" data-section="settings/your-profile">
    <div class="component-wrapper">
        <div class="component-header-card">
            <h1 class="component-page-title" data-i18n="settings.profile.title">Tu Perfil</h1>
            <p class="component-page-description" data-i18n="settings.profile.description">Aquí podrás editar tu información de perfil, cambiar tu avatar y nombre de usuario.</p>
        </div>

        <div class="component-card component-card--edit-mode" id="avatar-section">

            <input type="hidden" name="csrf_token" value="6d379709526bc94a1f1081247db712b90d2a67d65c1f143cb5fa17a98579c9c7"> <input type="file" class="visually-hidden" id="avatar-upload-input" name="avatar" accept="image/png, image/jpeg, image/gif, image/webp">

            <div class="component-card__content">
                <div class="component-card__avatar" id="avatar-preview-container" data-role="user">
                    <img src="/ProjectGenesis/assets/uploads/avatars_default/user-3.png" alt="Avatar de user20251120_1825356l" class="component-card__avatar-image" id="avatar-preview-image" data-i18n-alt-prefix="header.profile.altPrefix">

                    <div class="component-card__avatar-overlay">
                        <span class="material-symbols-rounded">photo_camera</span>
                    </div>
                </div>
                <div class="component-card__text">
                    <h2 class="component-card__title" data-i18n="settings.profile.avatarTitle">Foto de perfil</h2>
                    <p class="component-card__description" data-i18n="settings.profile.avatarDesc">Esto ayudará a tus compañeros a reconocerte.</p>
                </div>
            </div>

            <div class="component-card__actions">

                <div id="avatar-actions-default" class="active" style="gap: 12px;">
                    <button type="button" class="component-button" id="avatar-upload-trigger" data-i18n="settings.profile.uploadPhoto">Subir foto</button>
                </div>

                <div id="avatar-actions-custom" class="disabled" style="gap: 12px;">
                    <button type="button" class="component-button danger" id="avatar-remove-trigger" data-i18n="settings.profile.removePhoto">Eliminar foto</button>
                    <button type="button" class="component-button" id="avatar-change-trigger" data-i18n="settings.profile.changePhoto">Cambiar foto</button>
                </div>

                <div id="avatar-actions-preview" class="disabled" style="gap: 12px;">
                    <button type="button" class="component-button" id="avatar-cancel-trigger" data-i18n="settings.profile.cancel">Cancelar</button>
                    <button type="button" class="component-button" id="avatar-save-trigger-btn" data-i18n="settings.profile.save">Guardar</button>
                </div>
            </div>
        </div>
    </div>
</div>









<style>
    .component-wrapper {
        width: 100%;
        max-width: 700px;
        margin: 0 auto;
        padding: 16px;
        display: flex;
        flex-direction: column;
        gap: 16px;
    }

    .component-header-card {
        border: 1px solid #00000020;
        border-radius: 12px;
        padding: 24px;
        background-color: #ffffff;
    }

    .component-page-title {
        font-size: 28px;
        font-weight: 700;
        color: #1f2937;
        text-align: center;
    }

    .component-page-description {
        font-size: 16px;
        color: #6b7280;
        line-height: 1.6;
        margin-bottom: 0;
        text-align: center;
    }

    .component-card--edit-mode {
        align-items: flex-end;
        gap: 8px;
    }

    .component-card {
        display: flex;
        flex-direction: row;
        align-items: center;
        width: 100%;
        justify-content: space-between;
        border: 1px solid #00000020;
        border-radius: 12px;
        padding: 24px;
        background-color: #ffffff;
    }

    .component-card__content {
    display: flex;
    flex: 1 1 auto;
    align-items: center;
    gap: 16px;
}.component-card__avatar {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    border: 1px solid #ffffff40;
    padding: 0;
    position: relative;
    flex-shrink: 0;
}.component-card__avatar-image {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
    position: relative;
    z-index: 1;
    border-radius: 50%;
}.component-card__avatar-overlay {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    border-radius: 50%;
    background-color: rgba(0, 0, 0, 0.5);
    display: flex;
    align-items: center;
    justify-content: center;
    color: #ffffff;
    opacity: 0;
    transition: opacity 0.2s 
ease-in-out;
    z-index: 2;
    cursor: pointer;
}.component-card__avatar-overlay .material-symbols-rounded {
    font-size: 16px;
}.visually-hidden {
    position: absolute;
    width: 1px;
    height: 1px;
    margin: -1px;
    padding: 0;
    overflow: hidden;
    clip: rect(0, 0, 0, 0);
    border: 0;
}.component-button {
    height: 40px;
    border: 1px solid #00000020;
    border-radius: 8px;
    background-color: #ffffff;
    color: #000000;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: background-color 0.2s;
    padding: 0 16px;
    flex-shrink: 0;
}.component-card__title {
    font-size: 14px;
    font-weight: 600;
    color: #000000;
}.component-card__description {
    font-size: 14px;
    color: #6b7280;
}.component-card__text {
    display: flex;
    flex-direction: column;
    line-height: 25px;
    width: 100%;
}
</style>