<?php
declare(strict_types=1);
function cfg(string $path, $default = null) {
    $v = $GLOBALS['cfg'];
    foreach (explode('.', $path) as $k) { if (!is_array($v) || !array_key_exists($k, $v)) return $default; $v = $v[$k]; }
    return $v;
}
function e($s): string { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function now(): string { return gmdate('Y-m-d H:i:s'); }
function client_ip(): string {
    $h = cfg('admin.ip_header');
    $ip = $h && !empty($_SERVER[$h]) ? trim(explode(',', $_SERVER[$h])[0]) : ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
    return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
}
/** UTC DB vaqtini Asia/Tashkent ko‘rinishiga o‘tkazadi */
function dt(?string $utc, string $fmt = 'd.m.Y H:i'): string {
    if (!$utc) return '—';
    try { return (new DateTime($utc, new DateTimeZone('UTC')))->setTimezone(new DateTimeZone(Settings::get('timezone', 'Asia/Tashkent')))->format($fmt); }
    catch (Throwable) { return e($utc); }
}
function money($n, string $cur = 'UZS'): string { return number_format((float)$n, 0, '.', ' ') . ' ' . $cur; }
function n($v): string { return number_format((int)$v, 0, '.', ' '); }
function slog(string $level, string $channel, string $msg, array $ctx = []): void {
    try { DB::exec('INSERT INTO system_logs (level,channel,message,context) VALUES (?,?,?,?)',
        [$level, $channel, mb_substr($msg, 0, 1000), $ctx ? json_encode($ctx, JSON_UNESCAPED_UNICODE) : null]); } catch (Throwable) {}
}
/** local "YYYY-MM-DDTHH:MM" (admin vaqt zonasi) -> UTC */
function local_to_utc(string $local): ?string {
    try { return (new DateTime($local, new DateTimeZone(Settings::get('timezone', 'Asia/Tashkent'))))->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'); }
    catch (Throwable) { return null; }
}
function utc_to_local_input(?string $utc): string {
    return $utc ? dt($utc, 'Y-m-d\TH:i') : '';
}
function safe_https_url(?string $u): ?string {
    $u = trim((string)$u);
    return ($u !== '' && preg_match('#^https://[^\s<>"\']+$#i', $u) && filter_var($u, FILTER_VALIDATE_URL)) ? $u : null;
}
