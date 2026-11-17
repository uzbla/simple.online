<?php
declare(strict_types=1);
final class Backup {
    static function dir(): string { $d = ROOT . '/storage/backups'; if (!is_dir($d)) mkdir($d, 0750, true); return $d; }
    private static function run(array $cmd, ?string $stdinFile, ?string $stdoutFile): array {
        $c = cfg('db');
        $spec = [0 => $stdinFile ? ['file', $stdinFile, 'r'] : ['pipe', 'r'], 1 => $stdoutFile ? ['file', $stdoutFile, 'w'] : ['pipe', 'w'], 2 => ['pipe', 'w']];
        $p = proc_open($cmd, $spec, $pipes, null, ['MYSQL_PWD' => (string)$c['pass'], 'PATH' => getenv('PATH') ?: '/usr/bin:/bin']);
        if (!is_resource($p)) return [1, 'proc_open ishlamadi'];
        if (isset($pipes[0])) fclose($pipes[0]);
        $err = stream_get_contents($pipes[2]); if (isset($pipes[1])) fclose($pipes[1]); fclose($pipes[2]);
        return [proc_close($p), trim((string)$err)];
    }
    static function create(string $kind = 'manual', ?int $adminId = null): array {
        $c = cfg('db'); $name = 'intizom_' . gmdate('Ymd_His') . '_' . bin2hex(random_bytes(3)) . '.sql';
        $path = self::dir() . '/' . $name;
        [$code, $err] = self::run([cfg('mysqldump'), '-h', $c['host'], '-u', $c['user'], '--single-transaction', '--routines', '--triggers', '--default-character-set=utf8mb4', $c['name']], null, $path);
        $ok = $code === 0 && is_file($path) && filesize($path) > 0;
        $id = DB::insert('INSERT INTO backup_logs (filename,size_bytes,sha256,kind,status,note,admin_id) VALUES (?,?,?,?,?,?,?)',
            [$name, $ok ? filesize($path) : 0, $ok ? hash_file('sha256', $path) : null, $kind, $ok ? 'ok' : 'failed', $ok ? null : mb_substr($err, 0, 250), $adminId]);
        if (!$ok) { @unlink($path); slog('error', 'backup', 'Backup xato: ' . $err); }
        return ['ok' => $ok, 'id' => $id, 'error' => $err];
    }
    static function verify(array $row): bool {
        $p = self::dir() . '/' . basename($row['filename']);
        return is_file($p) && $row['sha256'] && hash_equals($row['sha256'], hash_file('sha256', $p));
    }
    /** DEFINER ko‘rsatmalarini olib tashlab (boshqa DB foydalanuvchisida ham tiklansin), vaqtinchalik fayl yo‘lini qaytaradi */
    private static function stripped(string $src): ?string {
        $tmp = self::dir() . '/.restore_' . bin2hex(random_bytes(6)) . '.sql';
        $in = fopen($src, 'r'); $out = fopen($tmp, 'w'); if (!$in || !$out) return null;
        while (($line = fgets($in)) !== false) fwrite($out, preg_replace('/\s*DEFINER=`[^`]*`@`[^`]*`/', '', $line));
        fclose($in); fclose($out); return $tmp;
    }
    private static function load(array $row): array {
        $c = cfg('db'); $tmp = self::stripped(self::dir() . '/' . basename($row['filename']));
        if (!$tmp) return [1, 'Faylni o‘qib bo‘lmadi'];
        $r = self::run([cfg('mysql'), '-h', $c['host'], '-u', $c['user'], $c['name']], $tmp, null); @unlink($tmp); return $r;
    }
    /** Tiklaydi; xato bo‘lsa $rollback (tiklashdan oldingi zaxira) ga avtomatik qaytadi */
    static function restore(array $row, ?array $rollback = null): array {
        if (!self::verify($row)) return ['ok' => false, 'error' => 'Backup fayli yaroqsiz (checksum mos emas).'];
        [$code, $err] = self::load($row);
        if ($code === 0) return ['ok' => true, 'error' => ''];
        if ($rollback) { [$c2, $e2] = self::load($rollback); $err .= $c2 === 0 ? ' — Baza tiklashdan oldingi holatga qaytarildi.' : ' — DIQQAT: avtomatik qaytarish ham muvaffaqiyatsiz: ' . $e2; }
        return ['ok' => false, 'error' => $err];
    }
}
