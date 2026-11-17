<?php
$err = null;
if (post_guard() && ($err = Auth::verify2fa(post('code'))) === null) redirect(url('dashboard'));
?><!doctype html><html lang="uz"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>2FA</title><meta name="robots" content="noindex"><link rel="stylesheet" href="assets/admin.css"></head>
<body><div class="login"><form class="glass" method="post"><div class="brand">Ikki bosqichli tasdiqlash<small>Authenticator ilovasidagi 6 xonali kod</small></div>
<?php if ($err): ?><div class="flash err"><?= e($err) ?></div><?php endif; ?><?= csrf() ?>
<label>Kod</label><input name="code" inputmode="numeric" pattern="\d{6}" maxlength="7" required autofocus autocomplete="one-time-code">
<p><button type="submit">Tasdiqlash</button> <button class="btn ghost" formaction="<?= e(url('logout')) ?>" formnovalidate>Bekor qilish</button></p></form></div></body></html>
