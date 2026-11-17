<?php
$FIELDS = ['platform_name' => ['Platforma nomi', 'text', 64], 'default_language' => ['Asosiy til (uz, ru, en)', 'text', 5], 'timezone' => ['Vaqt zonasi', 'text', 64], 'currency' => ['Asosiy valyuta (ISO)', 'text', 3],
  'bot_username' => ['Bot username (@siz)', 'text', 64], 'support_contact' => ['Support kontakti', 'text', 100], 'trial_days' => ['Sinov muddati (kun)', 'number', 3],
  'reminder_max_per_user' => ['Foydalanuvchiga maks. eslatma', 'number', 4], 'queue_rate_per_sec' => ['Telegram yuborish tezligi (xabar/soniya, ≤28)', 'number', 2],
  'queue_max_attempts' => ['Yuborishga maks. urinish', 'number', 2], 'premium_warn_days' => ['Premium tugashidan necha kun oldin ogohlantirish', 'number', 2], 'session_minutes' => ['Admin sessiyasi (daqiqa, harakatsizlik)', 'number', 4]];
if (post_guard()) {
    $old = []; $new = []; $err = null;
    foreach ($FIELDS as $k => [$lab, $type, $max]) { $v = post($k, (string)Settings::get($k));
        if ($k === 'timezone' && !in_array($v, DateTimeZone::listIdentifiers(), true)) $err = 'Vaqt zonasi noto‘g‘ri.';
        if ($k === 'currency' && !preg_match('/^[A-Z]{3}$/', $v)) $err = 'Valyuta ISO kodi (masalan UZS).';
        if ($k === 'queue_rate_per_sec') $v = (string)max(1, min(28, (int)$v));
        if ($k === 'session_minutes') $v = (string)max(5, min(480, (int)$v));
        if ($type === 'number') $v = (string)max(0, (int)$v);
        if ($v !== (string)Settings::get($k)) { $old[$k] = Settings::get($k); $new[$k] = mb_substr($v, 0, $max > 10 ? 200 : 20); } }
    if (!empty($_FILES['logo']['tmp_name']) && is_uploaded_file($_FILES['logo']['tmp_name'])) {
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($_FILES['logo']['tmp_name']); $ext = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp'][$mime] ?? null;
        if (!$ext || $_FILES['logo']['size'] > 1048576) $err = 'Logo PNG/JPG/WEBP, ≤1MB bo‘lishi kerak.';
        else { $name = 'logo_' . bin2hex(random_bytes(6)) . '.' . $ext; $dir = dirname(__DIR__, 2) . '/public/uploads'; if (!is_dir($dir)) mkdir($dir, 0755, true);
            move_uploaded_file($_FILES['logo']['tmp_name'], "$dir/$name"); $old['logo_url'] = Settings::get('logo_url'); $new['logo_url'] = 'uploads/' . $name; } }
    if ($err) flash('err', $err);
    else { foreach ($new as $k => $v) Settings::set($k, $v); if ($new) Audit::log('settings', 'update', ['old' => $old, 'new' => $new]); flash('ok', $new ? 'Saqlandi.' : 'O‘zgarish yo‘q.'); }
    redirect(url('settings'));
}
$sec = fn($v) => $v ? badge('ok', 'Sozlangan (' . '•••• ••••' . ')') : badge('warn', 'Sozlanmagan');
?>
<h1>Tizim sozlamalari</h1><form class="card glass" method="post" enctype="multipart/form-data"><?= csrf() ?><div class="row">
<?php foreach ($FIELDS as $k => [$lab, $type, $max]): ?><div><label><?= e($lab) ?></label><input type="<?= $type ?>" name="<?= e($k) ?>" value="<?= e(Settings::get($k, '')) ?>" maxlength="<?= $max > 10 ? 200 : 20 ?>"></div><?php endforeach; ?></div>
<label>Logo (PNG/JPG/WEBP, ≤1MB)</label><input type="file" name="logo" accept="image/png,image/jpeg,image/webp"><?php if (Settings::get('logo_url')): ?><p class="muted">Joriy: <?= e(Settings::get('logo_url')) ?></p><?php endif; ?><p><button>Saqlash</button></p></form>
<div class="card glass"><h2>Maxfiy konfiguratsiya</h2><p class="muted">Tokenlar va parollar interfeysda ko‘rsatilmaydi va tahrirlanmaydi — ular serverdagi <code>config/config.php</code> / muhit o‘zgaruvchilarida saqlanadi.</p>
<dl class="kv"><dt>Bot token</dt><dd><?= $sec(cfg('telegram.bot_token')) ?></dd><dt>Webhook secret</dt><dd><?= $sec(cfg('telegram.webhook_secret')) ?></dd><dt>To‘lov webhook kaliti</dt><dd><?= $sec(cfg('payments.webhook_secret')) ?></dd><dt>Ma’lumotlar bazasi paroli</dt><dd><?= $sec(cfg('db.pass')) ?></dd></dl></div>
