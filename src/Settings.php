<?php
declare(strict_types=1);
final class Settings {
    private static ?array $c = null;
    static function all(): array {
        if (self::$c === null) { self::$c = []; try { foreach (DB::all('SELECT k,v FROM system_settings') as $r) self::$c[$r['k']] = $r['v']; } catch (Throwable) {} }
        return self::$c;
    }
    static function get(string $k, ?string $d = null): ?string { return self::all()[$k] ?? $d; }
    static function set(string $k, ?string $v): void {
        DB::exec('INSERT INTO system_settings (k,v) VALUES (?,?) ON DUPLICATE KEY UPDATE v=VALUES(v)', [$k, $v]);
        self::$c = null;
    }
}
