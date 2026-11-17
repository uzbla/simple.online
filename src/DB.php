<?php
declare(strict_types=1);
final class DB {
    private static ?PDO $pdo = null;
    static function pdo(): PDO {
        if (!self::$pdo) {
            $c = cfg('db');
            self::$pdo = new PDO("mysql:host={$c['host']};dbname={$c['name']};charset=utf8mb4", $c['user'], $c['pass'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false, PDO::MYSQL_ATTR_INIT_COMMAND => "SET time_zone='+00:00'"]);
        }
        return self::$pdo;
    }
    static function q(string $sql, array $p = []): PDOStatement { $s = self::pdo()->prepare($sql); $s->execute($p); return $s; }
    static function all(string $sql, array $p = []): array { return self::q($sql, $p)->fetchAll(); }
    static function one(string $sql, array $p = []): ?array { $r = self::q($sql, $p)->fetch(); return $r ?: null; }
    static function val(string $sql, array $p = []) { $r = self::q($sql, $p)->fetchColumn(); return $r === false ? null : $r; }
    static function exec(string $sql, array $p = []): int { return self::q($sql, $p)->rowCount(); }
    static function insert(string $sql, array $p = []): int { self::q($sql, $p); return (int)self::pdo()->lastInsertId(); }
    static function tx(callable $fn) {
        $pdo = self::pdo(); $pdo->beginTransaction();
        try { $r = $fn(); $pdo->commit(); return $r; } catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $e; }
    }
}
