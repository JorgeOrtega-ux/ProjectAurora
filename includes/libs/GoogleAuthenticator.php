<?php
// includes/libs/GoogleAuthenticator.php

class GoogleAuthenticator {
    protected static $_codeLength = 6;

    /**
     * Crea un secreto nuevo (Base32)
     */
    public static function createSecret($secretLength = 16) {
        $validChars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $secret = '';
        $rnd = false;

        // 1. Usar random_bytes (Nativo en PHP 7+ y recomendado)
        if (function_exists('random_bytes')) {
            try {
                $rnd = random_bytes($secretLength);
            } catch (Exception $e) {
                $rnd = false; 
            }
        } 
        // 2. Fallback a OpenSSL si random_bytes fallara (raro en sistemas modernos)
        elseif (function_exists('openssl_random_pseudo_bytes')) {
            $rnd = openssl_random_pseudo_bytes($secretLength, $cryptoStrong);
            if (!$cryptoStrong) { $rnd = false; }
        }

        // Construir el secreto
        if ($rnd !== false) {
            for ($i = 0; $i < $secretLength; ++$i) {
                $secret .= $validChars[ord($rnd[$i]) & 31];
            }
        } else {
            // Último recurso: mt_rand (menos seguro criptográficamente pero evita errores fatales)
            for ($i = 0; $i < $secretLength; ++$i) {
                $secret .= $validChars[mt_rand(0, 31)];
            }
        }
        return $secret;
    }

    /**
     * Calcula el código
     */
    public static function getCode($secret, $timeSlice = null) {
        if ($timeSlice === null) {
            $timeSlice = floor(time() / 30);
        }
        $secretKey = self::_base32Decode($secret);

        $time = chr(0).chr(0).chr(0).chr(0).pack('N*', $timeSlice);
        $hmac = hash_hmac('SHA1', $time, $secretKey, true);
        $offset = ord(substr($hmac, -1)) & 0x0F;
        $hashPart = substr($hmac, $offset, 4);

        $value = unpack('N', $hashPart);
        $value = $value[1];
        $value = $value & 0x7FFFFFFF;

        $modulo = pow(10, self::$_codeLength);
        return str_pad($value % $modulo, self::$_codeLength, '0', STR_PAD_LEFT);
    }

    /**
     * Verifica el código enviado por el usuario
     * $discrepancy es la tolerancia de ventanas de tiempo (1 = 30 seg antes o después)
     */
    public static function verifyCode($secret, $code, $discrepancy = 1, $currentTimeSlice = null) {
        if ($currentTimeSlice === null) {
            $currentTimeSlice = floor(time() / 30);
        }

        if (strlen($code) != 6) {
            return false;
        }

        for ($i = -$discrepancy; $i <= $discrepancy; ++$i) {
            $calculatedCode = self::getCode($secret, $currentTimeSlice + $i);
            if (hash_equals((string)$calculatedCode, (string)$code)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Genera la URL otpauth:// para que el JS genere el QR
     */
    public static function getQrUrl($title, $secret, $issuer = null) {
        // rawurlencode es importante para compatibilidad con lectores QR
        $url = 'otpauth://totp/' . rawurlencode($title) . '?secret=' . $secret;
        if (isset($issuer)) {
            $url .= '&issuer=' . rawurlencode($issuer);
        }
        return $url;
    }

    private static function _base32Decode($secret) {
        if (empty($secret)) return '';

        $base32chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $base32charsFlipped = array_flip(str_split($base32chars));

        $paddingCharCount = substr_count($secret, '=');
        $allowedValues = array(6, 4, 3, 1, 0);
        if (!in_array($paddingCharCount, $allowedValues)) return false;

        for ($i = 0; $i < 4; ++$i) {
            if ($paddingCharCount == $allowedValues[$i] &&
                substr($secret, -($allowedValues[$i])) != str_repeat('=', $allowedValues[$i])) {
                return false;
            }
        }
        $secret = str_replace('=', '', $secret);
        $secret = str_split($secret);
        $binaryString = '';
        for ($i = 0; $i < count($secret); $i = $i + 8) {
            $x = '';
            if (!in_array($secret[$i], str_split($base32chars))) return false;
            for ($j = 0; $j < 8; ++$j) {
                $x .= str_pad(base_convert(@$base32charsFlipped[@$secret[$i + $j]], 10, 2), 5, '0', STR_PAD_LEFT);
            }
            $eightBits = str_split($x, 8);
            for ($z = 0; $z < count($eightBits); ++$z) {
                $binaryString .= (($y = chr(base_convert($eightBits[$z], 2, 10))) || ord($y) == 48) ? $y : '';
            }
        }
        return $binaryString;
    }
}
?>