<?php
if (post_guard() && post('act') === 'revoke' && Auth::can('security.manage')) {
    $s = DB::one('SELECT * FROM admin_sessions WHERE id=? AND revoked_at IS NULL', [(int)post('sid')]);
    if ($s) { DB::exec('UPDATE admin_sessions SET revoked_at=? WHERE id=?', [now(), $s['id']]); Audit::log('security', 'session_revoke', ['ref' => $s['id'], 'new' => ['admin_id' => $s['admin_id']]]); flash('ok', 'Sessiya bekor qilindi.'); }
    redirect(url('security'));
}
$h1 = gmdate('Y-m-d H:i:s', time() - 3600); $d1 = gmdate('Y-m-d H:i:s', time() - 86400); $alerts = [];
foreach (DB::all('SELECT ip, COUNT(*) c FROM admin_login_attempts WHERE success=0 AND created_at>? GROUP BY ip HAVING c>=5', [$h1]) as $r) $alerts[] = "IP {$r['ip']} dan 1 soatda {$r['c']} ta muvaffaqiyatsiz urinish.";
foreach (DB::all('SELECT login FROM admins WHERE status="active" AND totp_enabled=0') as $r) $alerts[] = "Admin «{$r['login']}» da 2FA yoqilmagan.";
if ((int)DB::val('SELECT COUNT(*) FROM system_logs WHERE level="error" AND created_at>?', [$d1]) > 20) $alerts[] = 'Oxirgi 24 soatda 20 dan ortiq tizim xatosi.';
if (Settings::get('last_cron_ok') === null && Settings::get('cron_heartbeat') === null) $alerts[] = 'Cron hali ishga tushmagan.';
$can = Auth::can('security.manage');
?>
<h1>Security Center</h1>
<?php if ($alerts): ?><div class="card glass"><h2>Xavfsizlik ogohlantirishlari</h2><?php foreach ($alerts as $a) echo '<p>' . badge('warning', '!') . ' ' . e($a) . '</p>'; ?></div><?php endif; ?>
<div class="grid g4"><?= stat_card('Noto‘g‘ri urinishlar (24 soat)', n(DB::val('SELECT COUNT(*) FROM admin_login_attempts WHERE success=0 AND created_at>?', [$d1]))) ?><?= stat_card('Faol sessiyalar', n(DB::val('SELECT COUNT(*) FROM admin_sessions WHERE revoked_at IS NULL AND twofa_ok=1 AND last_seen_at>?', [gmdate('Y-m-d H:i:s', time() - 7200)]))) ?>
<?= stat_card('2FA yoqilgan adminlar', n(DB::val('SELECT COUNT(*) FROM admins WHERE totp_enabled=1')) . ' / ' . n(DB::val('SELECT COUNT(*) FROM admins'))) ?><?= stat_card('API/tizim xatolari (24 soat)', n(DB::val('SELECT COUNT(*) FROM system_logs WHERE level="error" AND created_at>?', [$d1]))) ?></div><br>
<div class="card glass tw"><h2>Faol sessiyalar</h2><table><tr><th>Admin</th><th>IP</th><th>Brauzer</th><th>Boshlangan</th><th>Oxirgi faollik</th><th>2FA</th><th></th></tr>
<?php foreach (DB::all('SELECT s.*, a.login FROM admin_sessions s JOIN admins a ON a.id=s.admin_id WHERE s.revoked_at IS NULL ORDER BY s.last_seen_at DESC LIMIT 40') as $s): $cur = $s['id'] == Auth::session()['id']; ?>
<tr><td><?= e($s['login']) ?></td><td><?= e($s['ip']) ?></td><td class="muted"><?= e(mb_strimwidth($s['user_agent'], 0, 50, '…')) ?></td><td><?= e(dt($s['created_at'])) ?></td><td><?= e(dt($s['last_seen_at'])) ?></td><td><?= $s['twofa_ok'] ? badge('ok', '✓') : badge('warn', '…') ?></td>
<td><?php if ($cur): ?><span class="muted">joriy</span><?php elseif ($can): ?><form method="post" class="inline"><?= csrf() ?><input type="hidden" name="act" value="revoke"><input type="hidden" name="sid" value="<?= (int)$s['id'] ?>"><button class="btn danger sm" data-confirm="Sessiya bekor qilinsinmi?">Bekor</button></form><?php endif; ?></td></tr><?php endforeach; ?></table></div>
<div class="grid g2"><div class="card glass tw"><h2>Kirish tarixi</h2><table><tr><th>Vaqt</th><th>Login</th><th>IP</th><th>Tur</th><th>Natija</th></tr>
<?php foreach (DB::all('SELECT * FROM admin_login_attempts ORDER BY id DESC LIMIT 25') as $r) echo '<tr><td>' . e(dt($r['created_at'], 'd.m H:i:s')) . '</td><td>' . e($r['login']) . '</td><td>' . e($r['ip']) . '</td><td>' . e($r['kind']) . '</td><td>' . badge($r['success'] ? 'ok' : 'failed', $r['success'] ? 'OK' : 'Xato') . '</td></tr>'; ?></table></div>
<div class="card glass tw"><h2>Xatolar (tizim jurnali)</h2><table><tr><th>Vaqt</th><th>Kanal</th><th>Xabar</th></tr>
<?php foreach (DB::all('SELECT * FROM system_logs WHERE level IN ("error","warning") ORDER BY id DESC LIMIT 20') as $r) echo '<tr><td>' . e(dt($r['created_at'], 'd.m H:i')) . '</td><td>' . badge($r['level'], $r['channel']) . '</td><td>' . e(mb_strimwidth($r['message'], 0, 110, '…')) . '</td></tr>'; ?></table></div></div>
