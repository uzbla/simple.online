<?php
$v = fn(string $sql, array $p = []) => (int)DB::val($sql, $p); $d1 = gmdate('Y-m-d H:i:s', time() - 86400);
$hb = Settings::get('cron_heartbeat'); $age = $hb ? time() - (int)strtotime($hb . ' UTC') : null;
$q = []; foreach (DB::all('SELECT status, COUNT(*) c FROM notification_queue GROUP BY status') as $r) $q[$r['status']] = (int)$r['c'];
$dups = $v('SELECT COUNT(*) FROM (SELECT reminder_id FROM reminder_logs GROUP BY reminder_id, scheduled_for HAVING COUNT(*)>1) x');
$content = Auth::can('reminders.content'); $uid = (int)getp('user');
if ($uid && $content) Audit::log('reminders', 'content_view', ['user' => $uid, 'reason' => 'Shaxsiy eslatmalar ko‘rildi']);
?>
<h1>Eslatmalar nazorati</h1><p class="sub">Tizim holati. Shaxsiy eslatmalarni admin o‘zgartira olmaydi.</p>
<div class="grid g4"><?= stat_card('Faol eslatmalar', n($v('SELECT COUNT(*) FROM reminders WHERE enabled=1'))) ?><?= stat_card('Yuborilgan (24 soat)', n($v('SELECT COUNT(*) FROM reminder_logs WHERE status="sent" AND created_at>=?', [$d1]))) ?>
<?= stat_card('Muvaffaqiyatsiz (24 soat)', n($v('SELECT COUNT(*) FROM reminder_logs WHERE status="failed" AND created_at>=?', [$d1]))) ?><?= stat_card('Takroriy yuborish holatlari', n($dups), 'UNIQUE kalit tufayli 0 bo‘lishi kerak') ?>
<?= stat_card('Cron', $age === null ? 'Ma’lumot yo‘q' : ($age < 180 ? 'Ishlayapti' : 'To‘xtagan'), $hb ? dt($hb) : '') ?><?= stat_card('Navbat: kutmoqda', n($q['pending'] ?? 0)) ?><?= stat_card('Navbat: xato', n($q['failed'] ?? 0)) ?><?= stat_card('Navbat: yuborilgan', n($q['sent'] ?? 0)) ?></div>
<div class="card glass tw"><h2>Xatolar tarixi</h2><table><tr><th>Vaqt</th><th>Eslatma</th><?php if ($content): ?><th>Foydalanuvchi</th><?php endif; ?><th>Xato</th></tr>
<?php foreach (DB::all('SELECT l.*, r.user_id, r.type FROM reminder_logs l JOIN reminders r ON r.id=l.reminder_id WHERE l.status="failed" ORDER BY l.id DESC LIMIT 30') as $r): ?>
<tr><td><?= e(dt($r['created_at'])) ?></td><td>#<?= (int)$r['reminder_id'] ?> · <?= e($r['type']) ?></td><?php if ($content): ?><td><a href="<?= e(url('users', ['id' => $r['user_id']])) ?>">#<?= (int)$r['user_id'] ?></a></td><?php endif; ?><td><?= e($r['error']) ?></td></tr><?php endforeach; ?></table></div>
<?php if ($content && $uid): ?><div class="card glass tw"><h2>Foydalanuvchi #<?= $uid ?> eslatmalari <span class="muted">(audit logga yozildi, faqat ko‘rish)</span></h2><table><tr><th>Turi</th><th>Matn</th><th>Vaqt</th><th>Holat</th></tr>
<?php foreach (DB::all('SELECT * FROM reminders WHERE user_id=? ORDER BY id DESC LIMIT 50', [$uid]) as $r) echo '<tr><td>' . e($r['type']) . '</td><td>' . e($r['message']) . '</td><td>' . e(substr($r['time_of_day'], 0, 5)) . '</td><td>' . badge($r['enabled'] ? 'active' : 'disabled') . '</td></tr>'; ?></table></div><?php endif; ?>
