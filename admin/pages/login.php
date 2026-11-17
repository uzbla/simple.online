<?php
$err = null;
$tok = $_COOKIE['icsrf'] ?? ''; if (!preg_match('/^[a-f0-9]{32}$/', $tok)) { $tok = bin2hex(random_bytes(16)); setcookie('icsrf', $tok, ['path' => '/', 'httponly' => true, 'samesite' => 'Strict', 'secure' => Auth::https()]); }
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($tok, post('_csrf'))) { http_response_code(419); $err = 'Forma eskirgan. Qayta urinib ko‘ring.'; }
    elseif (!($err = Auth::login(post('login'), (string)($_POST['password'] ?? '')))) redirect(url('dashboard'));
}
$pn = Settings::get('platform_name', 'INTIZOM OS');
?><!doctype html><html lang="uz"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Kirish · <?= e($pn) ?></title><meta name="robots" content="noindex"><link rel="stylesheet" href="assets/admin.css"></head>
<body><div class="login"><form class="glass" method="post" autocomplete="off"><div class="brand">INTIZOM OS<small>Admin Panel · Reach Your Summit.</small></div>
<?php if ($err): ?><div class="flash err"><?= e($err) ?></div><?php endif; $f = take_flash(); if ($f): ?><div class="flash <?= $f[0] === 'ok' ? 'ok' : 'err' ?>"><?= e($f[1]) ?></div><?php endif; ?>
<input type="hidden" name="_csrf" value="<?= e($tok) ?>">
<label>Login</label><input name="login" required maxlength="64" autofocus autocomplete="username">
<label>Parol</label><input name="password" type="password" required autocomplete="current-password">
<p><button type="submit">Kirish</button></p><p class="muted"><a href="<?= e(url('forgot')) ?>">Parolni unutdingizmi?</a></p></form></div></body></html>
