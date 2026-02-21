<?php
// includes/core/EmailTemplates.php
namespace App\Core;

class EmailTemplates {
    
    public static function getActivationEmail($username, $code) {
        $appName = getenv('APP_NAME') ?: 'Project Aurora';
        return "
        <div style='font-family: sans-serif; max-width: 600px; margin: 0 auto;'>
            <h2>Bienvenido a {$appName}</h2>
            <p>Hola {$username},</p>
            <p>Tu código de activación es:</p>
            <h1 style='letter-spacing: 4px; background: #f5f5fa; padding: 12px; text-align: center; border-radius: 8px;'>{$code}</h1>
            <p>Este código expirará en 15 minutos.</p>
        </div>";
    }

    public static function getPasswordResetEmail($resetLink) {
        $appName = getenv('APP_NAME') ?: 'Project Aurora';
        return "
        <div style='font-family: sans-serif; max-width: 600px; margin: 0 auto;'>
            <h2>Recuperación de Contraseña</h2>
            <p>Has solicitado restablecer tu contraseña para {$appName}.</p>
            <p>Haz clic en el siguiente enlace para continuar:</p>
            <p><a href='{$resetLink}' style='display: inline-block; padding: 12px 24px; background: #000; color: #fff; text-decoration: none; border-radius: 8px;'>Restablecer Contraseña</a></p>
            <p style='font-size: 12px; color: #666; margin-top: 24px;'>Si no solicitaste este cambio, ignora este correo. El enlace expirará en 1 hora.</p>
        </div>";
    }

    public static function getEmailChangeEmail($username, $newEmail, $code) {
        $appName = getenv('APP_NAME') ?: 'Project Aurora';
        return "
        <div style='font-family: sans-serif; max-width: 600px; margin: 0 auto;'>
            <h2>Cambio de Correo Electrónico</h2>
            <p>Hola {$username},</p>
            <p>Se ha solicitado un cambio en tu cuenta para actualizar el correo a: <b>{$newEmail}</b>.</p>
            <p>Para autorizar el cambio, ingresa el siguiente código de seguridad:</p>
            <h1 style='letter-spacing: 4px; background: #f5f5fa; padding: 12px; text-align: center; border-radius: 8px;'>{$code}</h1>
            <p>Este código expirará en 15 minutos.</p>
        </div>";
    }
}
?>