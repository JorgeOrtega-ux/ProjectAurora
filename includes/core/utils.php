<?php
// includes/core/utils.php
namespace App\Core;

use Exception;
use PDO;
use App\Core\Logger;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

class Utils {
    
    /**
     * Envía una respuesta JSON estandarizada y detiene la ejecución.
     */
    public static function sendResponse($data, $statusCode = 200) {
        http_response_code($statusCode);
        echo json_encode($data);
        exit;
    }

    /**
     * Obtiene y decodifica el cuerpo de la petición (JSON).
     */
    public static function getJsonInput() {
        return json_decode(file_get_contents("php://input"));
    }

    /**
     * Valida el token CSRF comparándolo con la sesión.
     */
    public static function validateCSRF($token) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        return !empty($token) && !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }

    /**
     * Genera un identificador único (UUID).
     */
    public static function generateUUID() {
        return bin2hex(random_bytes(16));
    }

    /**
     * Genera un avatar a partir del nombre, lo descarga y lo guarda.
     * Devuelve la ruta relativa para ser guardada en la base de datos.
     */
    public static function generateAndSaveAvatar($name, $uuid, $absoluteStorageDir, $relativeWebDir) {
        if (!file_exists($absoluteStorageDir)) {
            mkdir($absoluteStorageDir, 0777, true);
        }

        $allowedColors = ['2563eb', '16a34a', '7c3aed', 'dc2626', 'ea580c', '374151'];
        $randomColor = $allowedColors[array_rand($allowedColors)];
        $nameEncoded = urlencode($name);
        
        $avatarUrl = "https://ui-avatars.com/api/?name={$nameEncoded}&background={$randomColor}&color=fff&size=512&length=1&format=png";
        
        // Suprimir warnings en caso de que falle la petición externa
        $avatarContent = @file_get_contents($avatarUrl);
        $avatarFilename = $uuid . '.png';
        
        if ($avatarContent) {
            file_put_contents($absoluteStorageDir . $avatarFilename, $avatarContent);
            return $relativeWebDir . $avatarFilename;
        }
        
        return ''; // Fallback en caso de no poder descargar
    }

    /**
     * Carga las variables de entorno desde el archivo .env
     */
    public static function loadEnv($path) {
        if (!file_exists($path)) {
            Logger::system("El archivo .env no existe en la ruta especificada: $path", Logger::LEVEL_CRITICAL);
            throw new Exception("El archivo .env no existe en la ruta especificada.");
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) return;

        foreach ($lines as $line) {
            if (strpos(trim($line), '#') === 0) continue;
            
            if (strpos($line, '=') !== false) {
                list($name, $value) = explode('=', $line, 2);
                $name = trim($name);
                $value = trim(trim($value), '"\'');

                putenv(sprintf('%s=%s', $name, $value));
                $_ENV[$name] = $value;
                $_SERVER[$name] = $value;
            }
        }
    }

    // ==========================================
    // NUEVAS FUNCIONES CENTRALIZADAS
    // ==========================================

    /**
     * Obtiene la IP real del usuario (Útil para Logs y Rate Limiting)
     */
    public static function getClientIP() {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            return $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            return $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            return $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
        }
    }

    /**
     * Envío de correos por SMTP centralizado
     */
    public static function sendEmail($to, $subject, $bodyHtml) {
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host       = getenv('SMTP_HOST') ?: 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = getenv('SMTP_USER');
            $mail->Password   = getenv('SMTP_PASS');
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            $mail->Port       = getenv('SMTP_PORT') ?: 465;

            $appName = getenv('APP_NAME') ?: 'Project Aurora';
            $mail->setFrom(getenv('SMTP_USER'), $appName);
            $mail->addAddress($to);

            $mail->isHTML(true);
            $mail->Subject = mb_encode_mimeheader($subject, "UTF-8");
            $mail->Body    = $bodyHtml;
            $mail->CharSet = 'UTF-8';

            $mail->send();
            return true;
        } catch (PHPMailerException $e) {
            Logger::system("Error al enviar email SMTP a $to. Asunto: $subject", Logger::LEVEL_ERROR, $e);
            return false;
        }
    }

    /**
     * Validación de Correo (Basada en Server Config)
     */
    public static function validateEmail($email) {
        global $APP_CONFIG;
        if (strlen($email) > 254) return false;
        
        $parts = explode('@', $email);
        if (count($parts) !== 2) return false;
        
        $local = $parts[0];
        $domain = strtolower($parts[1]);
        
        $minLocal = (int)($APP_CONFIG['min_email_local_length'] ?? 4);
        $maxLocal = (int)($APP_CONFIG['max_email_local_length'] ?? 64);
        
        if (strlen($local) < $minLocal || strlen($local) > $maxLocal) return false;
        
        $allowedDomainsStr = $APP_CONFIG['allowed_email_domains'] ?? 'gmail.com,outlook.com,icloud.com,hotmail.com,yahoo.com';
        $allowedDomains = array_map('trim', explode(',', strtolower($allowedDomainsStr)));
        
        if (!in_array($domain, $allowedDomains)) return false;
        
        return true;
    }

    /**
     * Validación de Contraseña (Basada en Server Config)
     */
    public static function validatePassword($password) {
        global $APP_CONFIG;
        $len = strlen($password);
        $min = (int)($APP_CONFIG['min_password_length'] ?? 12);
        $max = (int)($APP_CONFIG['max_password_length'] ?? 64);
        return $len >= $min && $len <= $max;
    }

    /**
     * Validación de Usuario (Basada en Server Config)
     */
    public static function validateUsername($username) {
        global $APP_CONFIG;
        $len = strlen(trim($username));
        $min = (int)($APP_CONFIG['min_username_length'] ?? 3);
        $max = (int)($APP_CONFIG['max_username_length'] ?? 32);
        return $len >= $min && $len <= $max;
    }

    /**
     * Verificador matemático de TOTP (Google Authenticator/Authy)
     */
    public static function verifyTOTP($secret, $code) {
        if (empty($secret) || empty($code)) return false;
        
        $base32chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $base32charsFlipped = array_flip(str_split($base32chars));
        $secret = strtoupper($secret);
        
        $paddingCharCount = substr_count($secret, '=');
        $allowedValues = array(6, 4, 3, 1, 0);
        if (!in_array($paddingCharCount, $allowedValues)) return false;
        
        $secret = str_replace('=', '', $secret);
        $secret = str_split($secret);
        $binaryString = "";
        
        foreach ($secret as $char) {
            if (!isset($base32charsFlipped[$char])) return false;
            $binaryString .= str_pad(base_convert($base32charsFlipped[$char], 10, 2), 5, '0', STR_PAD_LEFT);
        }
        
        $binaryArray = str_split($binaryString, 8);
        $decodedSecret = "";
        foreach ($binaryArray as $bin) {
            $decodedSecret .= chr(bindec($bin));
        }
        
        $tm = floor(time() / 30);
        for ($i = -1; $i <= 1; $i++) {
            $time = pack('N*', 0) . pack('N*', $tm + $i);
            $hash = hash_hmac('sha1', $time, $decodedSecret, true);
            $offset = ord(substr($hash, -1)) & 0x0F;
            
            $hashPart = substr($hash, $offset, 4);
            $value = unpack('N', $hashPart);
            $value = $value[1] & 0x7FFFFFFF;
            
            $calculatedCode = str_pad($value % 1000000, 6, '0', STR_PAD_LEFT);
            if ($calculatedCode === $code) return true;
        }
        return false;
    }

    /**
     * Procesa, optimiza y guarda una imagen usando GD Library
     */
    public static function processAndSaveImage($file, $storageDir, $webDir) {
        if ($file['error'] !== UPLOAD_ERR_OK) { 
            Logger::system("Fallo en subida de archivo. UPLOAD_ERR_CODE: " . $file['error'], Logger::LEVEL_WARNING);
            return ['success' => false, 'message' => 'Error al subir la imagen. Código: ' . $file['error']]; 
        }
        if ($file['size'] > 2 * 1024 * 1024) { 
            return ['success' => false, 'message' => 'La imagen supera el peso máximo de 2MB.']; 
        }

        $mime = mime_content_type($file['tmp_name']);
        $allowedMimes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
        if (!in_array($mime, $allowedMimes)) { 
            return ['success' => false, 'message' => 'El formato de imagen no es válido. Solo JPG, PNG, WEBP o GIF.']; 
        }

        $image = null;
        switch ($mime) {
            case 'image/jpeg': $image = @imagecreatefromjpeg($file['tmp_name']); break;
            case 'image/png':  $image = @imagecreatefrompng($file['tmp_name']); break;
            case 'image/webp': $image = @imagecreatefromwebp($file['tmp_name']); break;
            case 'image/gif':  $image = @imagecreatefromgif($file['tmp_name']); break;
        }
        if (!$image) { 
            Logger::system("Archivo de imagen corrupto o malicioso detectado y rechazado.", Logger::LEVEL_WARNING);
            return ['success' => false, 'message' => 'El archivo de imagen está corrupto o tiene contenido malicioso.']; 
        }

        if (!file_exists($storageDir)) { 
            if (!mkdir($storageDir, 0755, true)) {
                Logger::system("No se pudo crear el directorio de subida: $storageDir", Logger::LEVEL_ERROR);
            } 
        }

        $filename = self::generateUUID() . '_' . time() . '.jpg';
        $filepath = rtrim($storageDir, '/') . '/' . $filename;
        $webPath = rtrim($webDir, '/') . '/' . $filename;

        if (in_array($mime, ['image/png', 'image/webp', 'image/gif'])) {
            $width = imagesx($image);
            $height = imagesy($image);
            $bg = imagecreatetruecolor($width, $height);
            $white = imagecolorallocate($bg, 255, 255, 255);
            imagefill($bg, 0, 0, $white);
            imagecopyresampled($bg, $image, 0, 0, 0, 0, $width, $height, $width, $height);
            imagedestroy($image);
            $image = $bg;
        }

        if(!imagejpeg($image, $filepath, 85)){
             Logger::system("Fallo la escritura del archivo de imagen en: $filepath", Logger::LEVEL_ERROR);
             imagedestroy($image);
             return ['success' => false, 'message' => 'Error del servidor al guardar la imagen.'];
        }
        imagedestroy($image);

        return ['success' => true, 'webPath' => $webPath];
    }

    // ==========================================
    // SISTEMA ANTI-SPAM (RATE LIMITING)
    // ==========================================

    public static function checkRateLimit($conn, $action, $blockMinutes = 15, $maxAttempts = 5) {
        try {
            $ip = self::getClientIP();
            $query = "SELECT attempts, blocked_until, last_attempt, (blocked_until > NOW()) as is_blocked FROM rate_limits WHERE ip_address = :ip AND action = :action LIMIT 1";
            $stmt = $conn->prepare($query);
            $stmt->execute([':ip' => $ip, ':action' => $action]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($result) {
                if ($result['is_blocked']) { 
                    Logger::system("Rate limit bloqueó acción '$action' para IP $ip", Logger::LEVEL_WARNING);
                    return false; 
                }
                // Si ya pasó el tiempo de bloqueo (o han pasado N minutos desde el último intento), reiniciamos
                if ($result['blocked_until'] !== null || strtotime($result['last_attempt']) < strtotime("-{$blockMinutes} minutes")) {
                    self::resetAttempts($conn, $action);
                }
            }
            return true;
        } catch (\Throwable $e) {
            Logger::database("Error al verificar rate limit para la acción: $action", Logger::LEVEL_ERROR, $e);
            return true;
        }
    }

    public static function recordActionAttempt($conn, $action, $maxAttempts = 5, $blockMinutes = 15) {
        try {
            $ip = self::getClientIP();
            $query = "SELECT attempts FROM rate_limits WHERE ip_address = :ip AND action = :action LIMIT 1";
            $stmt = $conn->prepare($query);
            $stmt->execute([':ip' => $ip, ':action' => $action]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($result) {
                $attempts = $result['attempts'] + 1;
                $blocked_until = ($attempts >= $maxAttempts) ? date('Y-m-d H:i:s', strtotime("+$blockMinutes minutes")) : null;
                $update = "UPDATE rate_limits SET attempts = :attempts, last_attempt = NOW(), blocked_until = :blocked_until WHERE ip_address = :ip AND action = :action";
                $updStmt = $conn->prepare($update);
                $updStmt->execute([':attempts' => $attempts, ':blocked_until' => $blocked_until, ':ip' => $ip, ':action' => $action]);
                
                if ($blocked_until) {
                    Logger::system("IP $ip bloqueada temporalmente por intentos fallidos en la acción '$action'", Logger::LEVEL_WARNING);
                }
            } else {
                $insert = "INSERT INTO rate_limits (ip_address, action, attempts, last_attempt) VALUES (:ip, :action, 1, NOW())";
                $insStmt = $conn->prepare($insert);
                $insStmt->execute([':ip' => $ip, ':action' => $action]);
            }
        } catch (\Throwable $e) {
            Logger::database("Error al registrar intento en la tabla rate_limits", Logger::LEVEL_ERROR, $e);
        }
    }

    public static function resetAttempts($conn, $action) {
        try {
            $ip = self::getClientIP();
            $query = "DELETE FROM rate_limits WHERE ip_address = :ip AND action = :action";
            $stmt = $conn->prepare($query);
            $stmt->execute([':ip' => $ip, ':action' => $action]);
        } catch (\Throwable $e) {
            Logger::database("Error al reiniciar intentos de rate_limits", Logger::LEVEL_ERROR, $e);
        }
    }

    // ==========================================
    // NUEVAS ABSTRACCIONES (DRY)
    // ==========================================

    /**
     * Parseo de User Agent para obtener SO y Navegador
     */
    public static function parseUserAgent($u_agent) {
        $platform = 'Unknown OS';
        if (preg_match('/windows|win32/i', $u_agent)) {
            $platform = 'Windows';
        } elseif (preg_match('/macintosh|mac os x/i', $u_agent)) {
            $platform = 'Mac';
        } elseif (preg_match('/linux/i', $u_agent)) {
            $platform = 'Linux';
        } elseif (preg_match('/iphone/i', $u_agent)) {
            $platform = 'iOS (iPhone)';
        } elseif (preg_match('/ipad/i', $u_agent)) {
            $platform = 'iOS (iPad)';
        } elseif (preg_match('/android/i', $u_agent)) {
            $platform = 'Android';
        }

        $browser = 'Unknown Browser';
        if (preg_match('/MSIE/i', $u_agent) && !preg_match('/Opera/i', $u_agent)) {
            $browser = 'Internet Explorer';
        } elseif (preg_match('/Firefox/i', $u_agent)) {
            $browser = 'Firefox';
        } elseif (preg_match('/OPR/i', $u_agent)) {
            $browser = 'Opera';
        } elseif (preg_match('/Edg/i', $u_agent)) {
            $browser = 'Edge';
        } elseif (preg_match('/Chrome/i', $u_agent)) {
            $browser = 'Chrome';
        } elseif (preg_match('/Safari/i', $u_agent)) {
            $browser = 'Safari';
        }
        
        return ['os' => $platform, 'browser' => $browser];
    }

    /**
     * Genera un código numérico seguro de longitud específica
     */
    public static function generateNumericCode($length = 6) {
        try {
            // max value for $length (ej: 6 -> 999999)
            $max = pow(10, $length) - 1;
            return str_pad((string) random_int(0, $max), $length, '0', STR_PAD_LEFT);
        } catch (\Exception $e) {
            // Fallback seguro en caso de que random_int falle
            return sprintf("%0" . $length . "d", mt_rand(0, pow(10, $length) - 1));
        }
    }

    /**
     * Genera un token criptográficamente seguro
     */
    public static function generateSecureToken($bytes = 32) {
        return bin2hex(random_bytes($bytes));
    }

    /**
     * Genera un array de códigos de recuperación 2FA (Formato XXXX-XXXX)
     */
    public static function generateRecoveryCodes($count = 10) {
        $codes = [];
        for ($i = 0; $i < $count; $i++) {
            $codes[] = bin2hex(random_bytes(4)) . '-' . bin2hex(random_bytes(4));
        }
        return $codes;
    }

    /**
     * Centraliza el registro de cambios en el historial del usuario (Auditoría)
     */
    public static function logUserChange($conn, $userId, $field, $oldValue, $newValue, $adminId = null) {
        try {
            $actualField = $adminId ? $field . ' (by Admin ID: ' . $adminId . ')' : $field;
            $stmt = $conn->prepare("INSERT INTO user_changes_log (user_id, modified_field, old_value, new_value) VALUES (:user_id, :field, :old_val, :new_val)");
            $stmt->execute([
                ':user_id' => $userId,
                ':field'   => $actualField,
                ':old_val' => $oldValue,
                ':new_val' => $newValue
            ]);
        } catch (\Throwable $e) {
            Logger::database("Error al registrar cambio en user_changes_log (Field: $field)", Logger::LEVEL_ERROR, $e);
        }
    }

    /**
     * Formatea una fecha SQL a un formato legible
     */
    public static function formatDate($dateString, $format = 'd/m/Y H:i:s') {
        if (empty($dateString)) return '';
        try {
            $d = new \DateTime($dateString);
            return $d->format($format);
        } catch (\Exception $e) {
            return $dateString;
        }
    }
}
?>