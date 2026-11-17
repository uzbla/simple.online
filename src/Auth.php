<?php
declare(strict_types=1);
/** Admin autentifikatsiyasi: parol + TOTP, sessiyalar DB'da (token hash), login limitlari */
final class Auth {
    const COOKIE = 'isid';
    const MAX_FAIL_LOGIN = 5;   // 15 daqiqada bitta login uchun
    const MAX_FAIL_IP = 20;     // 15 daqiqada bitta IP uchun
    private static ?array $ctx = null;

    static function policyError(string $p): ?string {
        if (mb_strlen($p) < 10) return 'Parol kamida 10 belgidan iborat bo‘lishi kerak.';
        if (!preg_match('/[a-z]/', $p) || !preg_match('/[A-Z]/', $p) || !preg_match('/\d/', $p) || !preg_match('/[^a-zA-Z0-9]/', $p))
            return 'Parolda katta va kichik harf, raqam hamda maxsus belgi bo‘lishi kerak.';
        return null;
    }
    private static function cookie(string $val, int $exp): void {
        setcookie(self::COOKIE, $val, ['expires' => $exp, 'path' => '/', 'secure' => self::https(), 'httponly' => true, 'samesite' => 'Strict']);
    }
    static function https(): bool { return !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || (cfg('admin.ip_header') && ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https'); }
    private static function rec(string $login, bool $ok, string $kind = 'password'): void {
        DB::exec('INSERT INTO admin_login_attempts (login,ip,success,kind,user_agent) VALUES (?,?,?,?,?)',
            [mb_substr($login, 0, 64), client_ip(), (int)$ok, $kind, mb_substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255)]);
    }
    static function locked(string $login): bool {
        $since = gmdate('Y-m-d H:i:s', time() - 900);
        $l = (int)DB::val("SELECT COUNT(*) FROM admin_login_attempts WHERE login=? AND success=0 AND created_at>?", [$login, $since]);
        $i = (int)DB::val("SELECT COUNT(*) FROM admin_login_attempts WHERE ip=? AND success=0 AND created_at>?", [client_ip(), $since]);
        return $l >= self::MAX_FAIL_LOGIN || $i >= self::MAX_FAIL_IP;
    }
    /** Xato matnini yoki null (muvaffaqiyat) qaytaradi */
    static function login(string $login, string $password): ?string {
        $login = trim($login);
        if (self::locked($login)) return 'Juda ko‘p noto‘g‘ri urinish. 15 daqiqadan so‘ng qayta urinib ko‘ring.';
        $a = DB::one('SELECT * FROM admins WHERE login=?', [$login]);
        // vaqt bo‘yicha farqlanmaslik uchun har doim hash tekshiriladi
        $hash = $a['password_hash'] ?? '$2y$12$abcdefghijklmnopqrstuuMwL0dZ1z6m7Qm2kqkq5Qk5yXQ0o0Q0a';
        $ok = password_verify($password, $hash) && $a && $a['status'] === 'active';
        self::rec($login, $ok);
        if (!$ok) { if ($a) slog('warning', 'admin_auth', 'Noto‘g‘ri parol', ['login' => $login, 'ip' => client_ip()]); return 'Login yoki parol noto‘g‘ri.'; }
        if (password_needs_rehash($a['password_hash'], PASSWORD_DEFAULT)) DB::exec('UPDATE admins SET password_hash=? WHERE id=?', [password_hash($password, PASSWORD_DEFAULT), $a['id']]);
        self::start((int)$a['id'], false);
        return null;
    }
    private static function start(int $adminId, bool $twofa): void {
        $tok = bin2hex(random_bytes(32));
        DB::exec('INSERT INTO admin_sessions (admin_id,token_hash,csrf,twofa_ok,ip,user_agent) VALUES (?,?,?,?,?,?)',
            [$adminId, hash('sha256', $tok), bin2hex(random_bytes(32)), (int)$twofa, client_ip(), mb_substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255)]);
        self::cookie($tok, 0);
        self::$ctx = null;
    }
    static function current(): ?array {
        if (self::$ctx !== null) return self::$ctx ?: null;
        self::$ctx = [];
        $tok = $_COOKIE[self::COOKIE] ?? '';
        if (!is_string($tok) || !preg_match('/^[a-f0-9]{64}$/', $tok)) return null;
        $s = DB::one('SELECT * FROM admin_sessions WHERE token_hash=? AND revoked_at IS NULL', [hash('sha256', $tok)]);
        if (!$s) return null;
        $idle = max(5, (int)Settings::get('session_minutes', '30')) * 60;
        if (strtotime($s['last_seen_at'] . ' UTC') < time() - $idle || strtotime($s['created_at'] . ' UTC') < time() - 43200) {
            DB::exec('UPDATE admin_sessions SET revoked_at=? WHERE id=?', [now(), $s['id']]); return null;
        }
        $a = DB::one('SELECT a.*, r.code AS role_code, r.name AS role_name FROM admins a JOIN admin_roles r ON r.id=a.role_id WHERE a.id=? AND a.status="active"', [$s['admin_id']]);
        if (!$a) return null;
        if (strtotime($s['last_seen_at'] . ' UTC') < time() - 60) DB::exec('UPDATE admin_sessions SET last_seen_at=? WHERE id=?', [now(), $s['id']]);
        return self::$ctx = ['admin' => $a, 'session' => $s];
    }
    static function admin(): ?array { return self::current()['admin'] ?? null; }
    static function session(): ?array { return self::current()['session'] ?? null; }
    /** To‘liq tasdiqlangan (2FA o‘tgan) adminmi */
    static function full(): bool { $c = self::current(); return $c && $c['session']['twofa_ok']; }
    static function can(string $perm): bool { $a = self::admin(); return $a && self::full() && Perm::has($a, $perm); }

    static function verify2fa(string $code): ?string {
        $c = self::current(); if (!$c) return 'Sessiya tugagan.';
        $a = $c['admin'];
        if (self::locked($a['login'])) return 'Juda ko‘p noto‘g‘ri urinish. Keyinroq urinib ko‘ring.';
        $step = $a['totp_secret'] ? Totp::verify($a['totp_secret'], $code, $a['totp_last_step'] !== null ? (int)$a['totp_last_step'] : null) : null;
        self::rec($a['login'], $step !== null, '2fa');
        if ($step === null) return 'Kod noto‘g‘ri yoki eskirgan.';
        DB::exec('UPDATE admins SET totp_last_step=?, last_login_at=? WHERE id=?', [$step, now(), $a['id']]);
        // session fixation'ga qarshi: 2FA'dan so‘ng tokenni almashtiramiz
        DB::exec('UPDATE admin_sessions SET revoked_at=? WHERE id=?', [now(), $c['session']['id']]);
        self::start((int)$a['id'], true);
        return null;
    }
    /** 2FA yoqish jarayonida (hali yoqilmagan admin) sessiyani tasdiqlangan deb belgilash */
    static function markFull(): void {
        $c = self::current(); if (!$c) return;
        DB::exec('UPDATE admin_sessions SET twofa_ok=1 WHERE id=?', [$c['session']['id']]); self::$ctx = null;
    }
    static function logout(): void {
        $c = self::current(); if ($c) DB::exec('UPDATE admin_sessions SET revoked_at=? WHERE id=?', [now(), $c['session']['id']]);
        self::cookie('', time() - 3600); self::$ctx = null;
    }
    static function revokeAllOf(int $adminId, ?int $exceptSession = null): void {
        DB::exec('UPDATE admin_sessions SET revoked_at=? WHERE admin_id=? AND revoked_at IS NULL AND id<>?', [now(), $adminId, $exceptSession ?? 0]);
    }
}
