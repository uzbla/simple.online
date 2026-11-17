<?php
declare(strict_types=1);
/** RFC 6238 TOTP (SHA1, 30s, 6 raqam) */
final class Totp {
    private const A = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    static function secret(): string { $b = random_bytes(20); $o = ''; $bits = '';
        foreach (str_split($b) as $ch) $bits .= str_pad(decbin(ord($ch)), 8, '0', STR_PAD_LEFT);
        foreach (str_split($bits, 5) as $c) $o .= self::A[bindec(str_pad($c, 5, '0'))]; return $o; }
    private static function decode(string $s): string { $bits = '';
        foreach (str_split(strtoupper($s)) as $ch) { $p = strpos(self::A, $ch); if ($p === false) continue; $bits .= str_pad(decbin($p), 5, '0', STR_PAD_LEFT); }
        $o = ''; foreach (str_split($bits, 8) as $b) if (strlen($b) === 8) $o .= chr(bindec($b)); return $o; }
    static function code(string $secret, int $step): string {
        $h = hash_hmac('sha1', pack('J', $step), self::decode($secret), true); $o = ord($h[19]) & 15;
        $v = ((ord($h[$o]) & 0x7f) << 24) | (ord($h[$o + 1]) << 16) | (ord($h[$o + 2]) << 8) | ord($h[$o + 3]);
        return str_pad((string)($v % 1000000), 6, '0', STR_PAD_LEFT);
    }
    /** To‘g‘ri bo‘lsa vaqt qadamini qaytaradi (replay oldini olish uchun), aks holda null */
    static function verify(string $secret, string $code, ?int $lastStep = null, ?int $time = null): ?int {
        $code = preg_replace('/\s+/', '', $code);
        if (!preg_match('/^\d{6}$/', $code)) return null;
        $cur = intdiv($time ?? time(), 30);
        for ($i = -1; $i <= 1; $i++) { $s = $cur + $i;
            if (hash_equals(self::code($secret, $s), $code)) return ($lastStep !== null && $s <= $lastStep) ? null : $s; }
        return null;
    }
    static function uri(string $secret, string $label, string $issuer): string {
        return 'otpauth://totp/' . rawurlencode("$issuer:$label") . '?secret=' . $secret . '&issuer=' . rawurlencode($issuer) . '&digits=6&period=30';
    }
}
