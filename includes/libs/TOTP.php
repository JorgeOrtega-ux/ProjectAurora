<?php
// includes/libs/TOTP.php

class TOTP {
    private static $base32Map = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    // Genera un secreto aleatorio
    public static function createSecret($length = 16) {
        $secret = '';
        for ($i = 0; $i < $length; $i++) {
            $secret .= self::$base32Map[rand(0, 31)];
        }
        return $secret;
    }

    // Verifica el código
    public static function verifyCode($secret, $code, $discrepancy = 1) {
        $currentTimeSlice = floor(time() / 30);
        
        for ($i = -$discrepancy; $i <= $discrepancy; $i++) {
            $calculatedCode = self::getCode($secret, $currentTimeSlice + $i);
            if (hash_equals((string)$calculatedCode, (string)$code)) {
                return true;
            }
        }
        return false;
    }

    // Calcula el código para un momento dado
    public static function getCode($secret, $timeSlice) {
        $secretKey = self::base32Decode($secret);
        $time = chr(0).chr(0).chr(0).chr(0).pack('N*', $timeSlice);
        $hmac = hash_hmac('sha1', $time, $secretKey, true);
        $offset = ord(substr($hmac, -1)) & 0x0F;
        $hashPart = substr($hmac, $offset, 4);
        $value = unpack('N', $hashPart);
        $value = $value[1];
        $value = $value & 0x7FFFFFFF;
        $modulo = $value % 1000000;
        return str_pad($modulo, 6, '0', STR_PAD_LEFT);
    }

    // Decodifica Base32
    private static function base32Decode($base32) {
        $base32 = strtoupper($base32);
        $l = strlen($base32);
        $n = 0;
        $j = 0;
        $binary = "";
        
        for ($i = 0; $i < $l; $i++) {
            $n = $n << 5;
            $n = $n + strpos(self::$base32Map, $base32[$i]);
            $j = $j + 5;
            if ($j >= 8) {
                $j = $j - 8;
                $binary .= chr(($n & (0xFF << $j)) >> $j);
            }
        }
        return $binary;
    }

    // Genera URL para QR (usando API de Google Charts o similar)
    public static function getQRCodeUrl($name, $secret, $issuer = 'ProjectAurora') {
        $otpauth = "otpauth://totp/" . $name . "?secret=" . $secret . "&issuer=" . $issuer;
        return "https://api.qrserver.com/v1/create-qr-code/?data=" . urlencode($otpauth) . "&size=200x200&ecc=M";
    }
}
?>