<?php
[$from, $to, $rk, $fd, $td] = date_range();
$tz = Settings::get('timezone', 'Asia/Tashkent'); $today0 = (new DateTime('today', new DateTimeZone($tz)))->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
$d7 = gmdate('Y-m-d H:i:s', time() - 7 * 86400); $d30 = gmdate('Y-m-d H:i:s', time() - 30 * 86400); $now = now();
$v = fn(string $sql, array $p = []) => (int)DB::val($sql, $p);
$day = fn(string $since) => $v('SELECT COUNT(DISTINCT user_id) FROM user_activity WHERE day>=DATE(?)', [$since]);
$cur = Settings::get('currency', 'UZS');
$rev = fn(string $since) => (float)DB::val('SELECT COALESCE(SUM(amount),0) FROM payments WHERE status="paid" AND paid_at>=?', [$since]);
$w0 = (new DateTime('monday this week', new DateTimeZone($tz)))->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
$m0 = (new DateTime('first day of this month 00:00', new DateTimeZone($tz)))->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
$hb = Settings::get('cron_heartbeat'); $cronAge = $hb ? time() - (int)strtotime($hb . ' UTC') : null;
$signups = series('SELECT {DAY:created_at} d, COUNT(*) v FROM users WHERE created_at>=? AND created_at<? GROUP BY d', [$from, $to], clone $fd, clone $td);
$revs = series('SELECT {DAY:paid_at} d, SUM(amount) v FROM payments WHERE status="paid" AND paid_at>=? AND paid_at<? GROUP BY d', [$from, $to], clone $fd, clone $td);
$activeSubs = $v('SELECT COUNT(DISTINCT user_id) FROM subscriptions WHERE status="active" AND ends_at>?', [$now]);
?>
<h1>Admin Dashboard</h1><p class="sub">Platforma ko‘rsatkichlari · vaqt zonasi: <?= e($tz) ?></p>
<?= range_form('dashboard') ?>
<div class="sec-t">Foydalanuvchilar</div><div class="grid g4">
<?= stat_card('Jami foydalanuvchilar', n($v('SELECT COUNT(*) FROM users WHERE status<>"deleted"'))) ?>
<?= stat_card('Bugun ro‘yxatdan o‘tgan', n($v('SELECT COUNT(*) FROM users WHERE created_at>=?', [$today0]))) ?>
<?= stat_card('Oxirgi 7 kun yangi', n($v('SELECT COUNT(*) FROM users WHERE created_at>=?', [$d7]))) ?>
<?= stat_card('Oylik yangi (30 kun)', n($v('SELECT COUNT(*) FROM users WHERE created_at>=?', [$d30]))) ?>
<?= stat_card('DAU', n($day(gmdate('Y-m-d')))) ?><?= stat_card('WAU', n($day($d7))) ?><?= stat_card('MAU', n($day($d30))) ?>
<?= stat_card('Bloklangan hisoblar', n($v('SELECT COUNT(*) FROM users WHERE status="blocked"'))) ?></div>
<div class="sec-t">Premium</div><div class="grid g4">
<?= stat_card('Jami Premium', n($v('SELECT COUNT(*) FROM users WHERE premium_until>?', [$now]))) ?>
<?= stat_card('Faol obunalar', n($activeSubs)) ?>
<?= stat_card('Muddati tugagan', n($v('SELECT COUNT(DISTINCT user_id) FROM subscriptions WHERE status="expired"'))) ?>
<?= stat_card('Sinov muddatida', n($v('SELECT COUNT(DISTINCT user_id) FROM subscriptions WHERE status="active" AND is_trial=1 AND ends_at>?', [$now]))) ?>
<?= stat_card('Yangi obunalar (filtr)', n($v('SELECT COUNT(*) FROM subscriptions WHERE created_at>=? AND created_at<?', [$from, $to]))) ?>
<?= stat_card('Bekor qilganlar (filtr)', n($v('SELECT COUNT(*) FROM subscriptions WHERE cancelled_at>=? AND cancelled_at<?', [$from, $to]))) ?></div>
<?php if (Auth::can('payments.view')): ?><div class="sec-t">Moliya (<?= e($cur) ?>)</div><div class="grid g4">
<?= stat_card('Bugungi tushum', money($rev($today0), $cur)) ?><?= stat_card('Haftalik tushum', money($rev($w0), $cur)) ?>
<?= stat_card('Oylik tushum', money($rev($m0), $cur)) ?><?= stat_card('Umumiy tushum', money($rev('2000-01-01'), $cur)) ?>
<?= stat_card('Qaytarilgan to‘lovlar', n($v('SELECT COUNT(*) FROM payments WHERE status="refunded"'))) ?>
<?= stat_card('To‘lov xatolari', n($v('SELECT COUNT(*) FROM payments WHERE status="failed"'))) ?></div><?php endif; ?>
<div class="sec-t">Platforma</div><div class="grid g4">
<?= stat_card('Bajarilgan vazifalar', n($v('SELECT COUNT(*) FROM tasks WHERE status="done"'))) ?>
<?= stat_card('Yaratilgan maqsadlar', n($v('SELECT COUNT(*) FROM goals'))) ?>
<?= stat_card('Faol odatlar', n($v('SELECT COUNT(*) FROM habits WHERE status="active"'))) ?>
<?= stat_card('Faol eslatmalar', n($v('SELECT COUNT(*) FROM reminders WHERE enabled=1'))) ?>
<?= stat_card('Yuborilgan Telegram xabarlari', n($v('SELECT COUNT(*) FROM notification_queue WHERE status="sent"'))) ?>
<?= stat_card('Bot / Cron holati', $cronAge === null ? 'Ma’lumot yo‘q' : ($cronAge < 180 ? 'Ishlayapti' : 'To‘xtagan'), $hb ? 'Oxirgi: ' . dt($hb) : '') ?></div>
<div class="grid g2"><div class="card glass"><h2>Yangi foydalanuvchilar</h2><?= chart($signups, 'line') ?></div>
<div class="card glass"><h2>Tushum (<?= e($cur) ?>)</h2><?= chart($revs, 'bar', 'var(--gold)') ?></div></div>
