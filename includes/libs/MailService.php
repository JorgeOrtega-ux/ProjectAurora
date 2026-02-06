<?php
// includes/libs/MailService.php

namespace Aurora\Libs;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use Aurora\Libs\Logger;


// NOTA: Ya no necesitamos los require_once manuales, 
// el autoload de Composer en index.php/loader.php se encarga.

class MailService {
    
    public static function send($toEmail, $subject, $body) {
        $mail = new PHPMailer(true);

        try {
            // Configuración del Servidor
            $mail->isSMTP();
            $mail->Host       = getenv('SMTP_HOST');
            $mail->SMTPAuth   = true;
            $mail->Username   = getenv('SMTP_USER');
            $mail->Password   = getenv('SMTP_PASS');
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; // O ENCRYPTION_STARTTLS si usas puerto 587
            $mail->Port       = getenv('SMTP_PORT');

            // Remitente y Destinatario
            $mail->setFrom(getenv('SMTP_USER'), 'Project Aurora');
            $mail->addAddress($toEmail);

            // Contenido
            $mail->isHTML(true);
            $mail->CharSet = 'UTF-8';
            $mail->Subject = $subject;
            $mail->Body    = $body;

            $mail->send();
            return ['success' => true];
        } catch (Exception $e) {
            // Loguear el error real usando tu clase Logger
            Logger::app("Error enviando correo: " . $mail->ErrorInfo);
            return ['success' => false, 'message' => $mail->ErrorInfo];
        }
    }
}
?>