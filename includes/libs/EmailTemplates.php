<?php
// includes/libs/EmailTemplates.php

class EmailTemplates
{

    /**
     * Estructura base del correo (Header y Footer) para no repetir código.
     */
    private static function getBaseTemplate($content)
    {
        $year = date('Y');

        return <<<HTML
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <style>
                body { margin: 0; padding: 0; background-color: #f4f4f7; font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; }
                .container { width: 100%; max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
                .header { background-color: #1a1a2e; padding: 20px; text-align: center; }
                .header h1 { color: #ffffff; margin: 0; font-size: 24px; font-weight: 600; }
                .content { padding: 40px 30px; color: #51545e; line-height: 1.6; }
                .code-box { background-color: #f0f0f5; border: 1px dashed #ccc; border-radius: 4px; padding: 15px; text-align: center; margin: 25px 0; }
                .code { font-size: 32px; font-weight: bold; letter-spacing: 5px; color: #1a1a2e; font-family: monospace; }
                .btn { display: inline-block; background-color: #4f46e5; color: #ffffff; padding: 12px 24px; border-radius: 6px; text-decoration: none; font-weight: bold; margin: 20px 0; }
                .footer { background-color: #f4f4f7; padding: 20px; text-align: center; color: #a8aaaf; font-size: 12px; }
                .text-small { font-size: 13px; color: #888; }
            </style>
        </head>
        <body>
            <div style="background-color: #f4f4f7; padding: 40px 0;">
                <div class="container">
                    <div class="header">
                        <h1>Project Aurora</h1>
                    </div>
                    
                    <div class="content">
                        $content
                    </div>
                    
                    <div class="footer">
                        <p>&copy; $year Project Aurora. Todos los derechos reservados.</p>
                        <p>Si no solicitaste este correo, puedes ignorarlo de forma segura.</p>
                    </div>
                </div>
            </div>
        </body>
        </html>
HTML;
    }

    /**
     * Plantilla para Código de Verificación (Registro/Login)
     */
    public static function verificationCode($username, $code, $minutes)
    {
        $bodyContent = <<<HTML
            <h2 style="color: #333; margin-top: 0;">Verifica tu cuenta</h2>
            <p>Hola, <strong>$username</strong>:</p>
            <p>Gracias por unirte a Project Aurora. Para completar tu registro o inicio de sesión, utiliza el siguiente código de verificación:</p>
            
            <div class="code-box">
                <span class="code">$code</span>
            </div>
            
            <p class="text-small">Este código expirará en <strong>$minutes minutos</strong>.</p>
            <p>Si tienes problemas, contacta a soporte.</p>
HTML;

        return self::getBaseTemplate($bodyContent);
    }

    /**
     * Plantilla para Recuperación de Contraseña
     */
    public static function passwordReset($link, $minutes)
    {
        $bodyContent = <<<HTML
            <h2 style="color: #333; margin-top: 0;">Restablecer Contraseña</h2>
            <p>Hemos recibido una solicitud para restablecer la contraseña de tu cuenta en Project Aurora.</p>
            <p>Haz clic en el siguiente botón para crear una nueva contraseña:</p>
            
            <div style="text-align: center;">
                <a href="$link" class="btn" style="color: #ffffff;">Restablecer Contraseña</a>
            </div>
            
            <p class="text-small">O copia y pega este enlace en tu navegador:</p>
            <p class="text-small" style="word-break: break-all;"><a href="$link">$link</a></p>
            
            <p class="text-small">Este enlace es válido por <strong>$minutes minutos</strong>.</p>
HTML;

        return self::getBaseTemplate($bodyContent);
    }
}
