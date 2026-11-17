<?php
$a = Auth::admin(); $s = Auth::session(); $setup = !Auth::full();
if (post_guard()) {
    $act = post('act');
    if ($act === 'password') {
        $np = (string)($_POST['new'] ?? '');
        if (!password_verify((string)($_POST['cur'] ?? ''), $a['password_hash'])) flash('err', 'Joriy parol noto‘g‘ri.');
        elseif ($np !== (string)($_POST['new2'] ?? '')) flash('err', 'Yangi parollar mos kelmadi.');
        elseif ($err = Auth::policyError($np)) flash('err', $err);
        else {
            DB::exec('UPDATE admins SET password_hash=?, must_change_password=0 WHERE id=?', [password_hash($np, PASSWORD_DEFAULT), $a['id']]);
            Auth::revokeAllOf((int)$a['id'], (int)$s['id']);
            Audit::log('security', 'password_changed', ['ref' => $a['id']]); flash('ok', 'Parol yangilandi, boshqa sessiyalar yopildi.');
        }
    } elseif ($act === '2fa_begin' && !$a['totp_enabled']) {
        DB::exec('UPDATE admins SET totp_secret=? WHERE id=?', [Totp::secret(), $a['id']]);
    } elseif ($act === '2fa_confirm' && !$a['totp_enabled'] && $a['totp_secret']) {
        $st = Totp::verify($a['totp_secret'], post('code'));
        if ($st === null) flash('err', 'Kod noto‘g‘ri.');
        else { DB::exec('UPDATE admins SET totp_enabled=1, totp_last_step=?, last_login_at=? WHERE id=?', [$st, now(), $a['id']]); Auth::markFull();
               Audit::log('security', '2fa_enabled', ['ref' => $a['id']]); flash('ok', '2FA yoqildi.'); }
    } elseif ($act === 'link_code' && !$setup) {
        $code = strtoupper(bin2hex(random_bytes(5))); DB::exec('UPDATE admins SET link_code=? WHERE id=?', [$code, $a['id']]);
        flash('ok', "Botga yuboring: /admin $code");
    }
    redirect(url('profile'));
}
$a = DB::one('SELECT * FROM admins WHERE id=?', [$a['id']]) + ['role_name' => $a['role_name']];
?>
<h1>Profil va xavfsizlik</h1><p class="sub"><?= e($a['login']) ?> · <?= e($a['role_name']) ?></p>
<?php if ($setup): ?><div class="flash err">Davom etish uchun ikki bosqichli autentifikatsiyani (2FA) yoqing.</div><?php endif; ?>
<?php if ($a['must_change_password'] && !$setup): ?><div class="flash err">Davom etish uchun vaqtinchalik parolni almashtiring.</div><?php endif; ?>
<div class="grid g2">
<div class="card glass"><h2>Ikki bosqichli autentifikatsiya</h2>
<?php if ($a['totp_enabled']): ?><p><?= badge('active', 'Yoqilgan') ?></p>
<?php elseif ($a['totp_secret']): ?>
  <p>Authenticator ilovasiga (Google Authenticator, Authy, 1Password…) quyidagi kalitni qo‘lda kiriting:</p>
  <p><code><?= e(implode(' ', str_split($a['totp_secret'], 4))) ?></code></p>
  <p class="muted">Yoki URI: <code><?= e(Totp::uri($a['totp_secret'], $a['login'], Settings::get('platform_name', 'INTIZOM OS'))) ?></code></p>
  <form method="post"><?= csrf() ?><input type="hidden" name="act" value="2fa_confirm"><label>Ilovadagi 6 xonali kod</label><input name="code" inputmode="numeric" pattern="\d{6}" required><p><button>Tasdiqlash va yoqish</button></p></form>
<?php else: ?><p><?= badge('disabled', 'O‘chiq') ?></p><form method="post"><?= csrf() ?><input type="hidden" name="act" value="2fa_begin"><button>2FA ni sozlash</button></form><?php endif; ?></div>
<?php if (!$setup): ?>
<div class="card glass"><h2>Parolni almashtirish</h2><form method="post"><?= csrf() ?><input type="hidden" name="act" value="password">
<label>Joriy parol</label><input type="password" name="cur" required autocomplete="current-password"><label>Yangi parol</label><input type="password" name="new" required autocomplete="new-password"><label>Takrorlang</label><input type="password" name="new2" required autocomplete="new-password">
<p><button>Saqlash</button></p></form></div>
<div class="card glass"><h2>Telegram (parol tiklash uchun)</h2><p><?= $a['telegram_chat_id'] ? badge('active', 'Bog‘langan') : badge('disabled', 'Bog‘lanmagan') ?></p>
<form method="post"><?= csrf() ?><input type="hidden" name="act" value="link_code"><button class="btn ghost">Bog‘lash kodini olish</button></form>
<?php if ($a['link_code']): ?><p class="muted">Botga yuboring: <code>/admin <?= e($a['link_code']) ?></code></p><?php endif; ?></div>
<div class="card glass"><h2>Oxirgi kirishlar</h2><div class="tw"><table><tr><th>Vaqt</th><th>IP</th><th>Natija</th></tr>
<?php foreach (DB::all('SELECT * FROM admin_login_attempts WHERE login=? ORDER BY id DESC LIMIT 8', [$a['login']]) as $r): ?>
<tr><td><?= e(dt($r['created_at'])) ?></td><td><?= e($r['ip']) ?></td><td><?= badge($r['success'] ? 'ok' : 'failed', $r['kind'] . ($r['success'] ? ' ✓' : ' ✗')) ?></td></tr><?php endforeach; ?></table></div></div>
<?php endif; ?></div>
