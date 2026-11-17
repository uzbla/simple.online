<?php
// Parolni tiklash: bir martalik havola admin Telegram chatiga yuboriladi (akkaunt mavjudligi oshkor qilinmaydi)
$tok = $_COOKIE['icsrf'] ?? ''; if (!preg_match('/^[a-f0-9]{32}$/', $tok)) { $tok = bin2hex(random_bytes(16)); setcookie('icsrf', $tok, ['path' => '/', 'httponly' => true, 'samesite' => 'Strict', 'secure' => Auth::https()]); }
$done = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && hash_equals($tok, post('_csrf'))) {
    $login = post('login'); $ip = client_ip(); $since = gmdate('Y-m-d H:i:s', time() - 3600);
    $cnt = (int)DB::val("SELECT COUNT(*) FROM admin_login_attempts WHERE ip=? AND kind='reset' AND created_at>?", [$ip, $since]);
    DB::exec("INSERT INTO admin_login_attempts (login,ip,success,kind) VALUES (?,?,0,'reset')", [mb_substr($login, 0, 64), $ip]);
    $a = $cnt < 5 ? DB::one('SELECT * FROM admins WHERE login=? AND status="active" AND telegram_chat_id IS NOT NULL', [$login]) : null;
    if ($a) {
        $raw = bin2hex(random_bytes(32));
        DB::exec('INSERT INTO password_resets (admin_id,token_hash,expires_at) VALUES (?,?,?)', [$a['id'], hash('sha256', $raw), gmdate('Y-m-d H:i:s', time() + 900)]);
        $base = cfg('admin.base_url') ?: ((Auth::https() ? 'https://' : 'http://') . ($_SERVER['HTTP_HOST'] ?? ''));
        Telegram::send((int)$a['telegram_chat_id'], "🔐 Parolni tiklash havolasi (15 daqiqa amal qiladi):\n" . rtrim($base, '/') . '/' . url('reset', ['t' => $raw]));
        Audit::log('security', 'password_reset_requested', ['admin_id' => $a['id'], 'login' => $a['login']]);
    }
    $done = true;
}
?><!doctype html><html lang="uz"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Parolni tiklash</title><meta name="robots" content="noindex"><link rel="stylesheet" href="assets/admin.css"></head>
<body><div class="login"><form class="glass" method="post"><div class="brand">Parolni tiklash</div>
<?php if ($done): ?><div class="flash ok">Agar akkaunt Telegramga bog‘langan bo‘lsa, tiklash havolasi yuborildi. Aks holda Super Admin'ga murojaat qiling.</div>
<?php else: ?><input type="hidden" name="_csrf" value="<?= e($tok) ?>"><label>Login</label><input name="login" required maxlength="64"><p><button>Havola yuborish</button></p><?php endif; ?>
<p class="muted"><a href="<?= e(url('login')) ?>">← Kirishga qaytish</a></p></form></div></body></html>
