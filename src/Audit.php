<?php
declare(strict_types=1);
final class Audit {
    static function log(string $section, string $action, array $o = []): void {
        $a = Auth::admin(); $s = Auth::session();
        DB::exec('INSERT INTO admin_logs (admin_id,admin_login,section,action,target_user_id,target_ref,old_value,new_value,reason,ip,session_id) VALUES (?,?,?,?,?,?,?,?,?,?,?)', [
            $a['id'] ?? ($o['admin_id'] ?? null), $a['login'] ?? ($o['login'] ?? ''), $section, $action,
            $o['user'] ?? null, isset($o['ref']) ? mb_substr((string)$o['ref'], 0, 64) : null,
            isset($o['old']) ? json_encode($o['old'], JSON_UNESCAPED_UNICODE) : null,
            isset($o['new']) ? json_encode($o['new'], JSON_UNESCAPED_UNICODE) : null,
            isset($o['reason']) ? mb_substr((string)$o['reason'], 0, 255) : null, client_ip(), $s['id'] ?? null]);
    }
}
