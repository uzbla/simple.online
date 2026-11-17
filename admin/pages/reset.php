<?php
$raw = getp('t'); $row = preg_match('/^[a-f0-9]{64}$/', $raw) ? DB::one('SELECT * FROM password_resets WHERE token_hash=? AND used_at IS NULL AND expires_at>?', [hash('sha256', $raw), now()]) : null;
$tok = $_COOKIE['icsrf'] ?? ''; if (!preg_match('/^[a-f0-9]{32}$/', $tok)) { $tok = bin2hex(random_bytes(16)); setcookie('icsrf', $tok, ['path' => '/', 'httponly' => true, 'samesite' => 'Strict', 'secure' => Auth::https()]); }
$err = null;
if ($row && $_SERVER['REQUEST_METHOD'] === 'POST' && hash_equals($tok, post('_csrf'))) {
    $pw = (string)($_POST['password'] ?? '');
    if ($pw !== (string)($_POST['password2'] ?? '')) $err = 'Parollar mos kelmadi.';
    elseif (!($err = Auth::policyError($pw))) {
        DB::tx(function () use ($row, $pw) {
            DB::exec('UPDATE admins SET password_hash=?, must_change_password=0 WHERE id=?', [password_hash($pw, PASSWORD_DEFAULT), $row['admin_id']]);
            DB::exec('UPDATE password_resets SET used_at=? WHERE id=?', [now(), $row['id']]);
            Auth::revokeAllOf((int)$row['admin_id']);
            Audit::log('security', 'password_reset_done', ['admin_id' => $row['admin_id'], 'login' => (string)DB::val('SELECT login FROM admins WHERE id=?', [$row['admin_id']])]);
        });
        flash('ok', 'Parol yangilandi. Kiring.'); redirect(url('login'));
    }
}
?><!doctype html><html lang="uz"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Yangi parol</title><meta name="robots" content="noindex"><link rel="stylesheet" href="assets/admin.css"></head>
<body><div class="login"><form class="glass" method="post"><div class="brand">Yangi parol</div>
<?php if (!$row): ?><div class="flash err">Havola yaroqsiz yoki muddati tugagan.</div><?php else: if ($err): ?><div class="flash err"><?= e($err) ?></div><?php endif; ?>
<input type="hidden" name="_csrf" value="<?= e($tok) ?>"><label>Yangi parol</label><input type="password" name="password" required autocomplete="new-password"><label>Takrorlang</label><input type="password" name="password2" required autocomplete="new-password">
<p class="muted">Kamida 10 belgi: katta/kichik harf, raqam, maxsus belgi.</p><p><button>Saqlash</button></p><?php endif; ?></form></div></body></html>
