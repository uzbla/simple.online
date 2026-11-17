<?php
$ok = fn(bool $b, string $t = '') => badge($b ? 'ok' : 'failed', $t ?: ($b ? 'OK' : 'Muammo'));
$hb = Settings::get('cron_heartbeat'); $age = $hb ? time() - (int)strtotime($hb . ' UTC') : null;
$dbSize = (int)DB::val('SELECT COALESCE(SUM(data_length+index_length),0) FROM information_schema.tables WHERE table_schema=DATABASE()');
$disk = @disk_free_space(ROOT); $total = @disk_total_space(ROOT);
$lastBk = DB::one('SELECT * FROM backup_logs WHERE status="ok" ORDER BY id DESC LIMIT 1');
$tg = null; if (getp('tg') === '1' && cfg('telegram.bot_token')) { $tg = Telegram::call('getMe', []); if ($tg['ok']) Settings::set('bot_ok_at', now()); }
$dbver = DB::val('SELECT VERSION()'); $pend = (int)DB::val('SELECT COUNT(*) FROM notification_queue WHERE status="pending"');
?>
<h1>System Monitoring</h1><div class="grid g2"><div class="card glass"><h2>Server</h2><dl class="kv">
<dt>PHP</dt><dd><?= e(PHP_VERSION) ?> <?= $ok(version_compare(PHP_VERSION, '8.3.0', '>=')) ?></dd><dt>Ma’lumotlar bazasi</dt><dd><?= e($dbver) ?> <?= $ok(true, 'Ulangan') ?></dd><dt>Baza hajmi</dt><dd><?= e(round($dbSize / 1048576, 2)) ?> MB</dd>
<dt>Disk</dt><dd><?= $disk !== false ? e(round($disk / 1073741824, 1)) . ' GB bo‘sh / ' . e(round($total / 1073741824, 1)) . ' GB ' . $ok($disk / $total > 0.1) : '—' ?></dd>
<dt>Kengaytmalar</dt><dd><?php foreach (['pdo_mysql', 'curl', 'mbstring', 'openssl'] as $x) echo e($x) . ' ' . $ok(extension_loaded($x)) . ' '; ?></dd><dt>HTTPS</dt><dd><?= $ok(Auth::https()) ?></dd></dl></div>
<div class="card glass"><h2>Servislar</h2><dl class="kv"><dt>Cron</dt><dd><?= $age === null ? badge('warn', 'Hech ishlamagan') : $ok($age < 180, $age < 180 ? 'Ishlayapti' : 'To‘xtagan') ?> <span class="muted"><?= e(dt($hb)) ?></span></dd>
<dt>Telegram API</dt><dd><?php if ($tg): echo $ok($tg['ok'], $tg['ok'] ? '@' . ($tg['data']['username'] ?? '') : ($tg['error'] ?? 'xato')); else: ?><span class="muted">oxirgi OK: <?= e(dt(Settings::get('bot_ok_at'))) ?></span> <a class="btn ghost sm" href="<?= e(url('system', ['tg' => 1])) ?>">Tekshirish</a><?php endif; ?></dd>
<dt>Xabarlar navbati</dt><dd><?= n($pend) ?> kutmoqda</dd><dt>Oxirgi backup</dt><dd><?= $lastBk ? e(dt($lastBk['created_at'])) . ' (' . e(round($lastBk['size_bytes'] / 1024)) . ' KB)' : badge('warn', 'Yo‘q') ?></dd></dl></div></div>
<div class="card glass tw"><h2>Xatolar jurnali</h2><table><tr><th>Vaqt</th><th>Daraja</th><th>Kanal</th><th>Xabar</th></tr>
<?php foreach (DB::all('SELECT * FROM system_logs WHERE level<>"info" ORDER BY id DESC LIMIT 30') as $r) echo '<tr><td>' . e(dt($r['created_at'], 'd.m H:i:s')) . '</td><td>' . badge($r['level']) . '</td><td>' . e($r['channel']) . '</td><td>' . e(mb_strimwidth($r['message'], 0, 160, '…')) . '</td></tr>'; ?></table></div>
