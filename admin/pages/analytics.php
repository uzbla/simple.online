<?php
[$from, $to, $rk, $fd, $td] = date_range(); $cur = Settings::get('currency', 'UZS');
$data = [
 'users_growth' => ['Yangi foydalanuvchilar', series('SELECT {DAY:created_at} d, COUNT(*) v FROM users WHERE created_at>=? AND created_at<? GROUP BY d', [$from, $to], clone $fd, clone $td)],
 'dau' => ['Kunlik faol foydalanuvchilar', series('SELECT day d, COUNT(*) v FROM user_activity WHERE day>=DATE(?) AND day<DATE(?) GROUP BY d', [$from, $to], clone $fd, clone $td)],
 'revenue' => ["Obuna daromadi ($cur)", series('SELECT {DAY:paid_at} d, SUM(amount) v FROM payments WHERE status="paid" AND paid_at>=? AND paid_at<? GROUP BY d', [$from, $to], clone $fd, clone $td)],
 'tasks' => ['Bajarilgan vazifalar', series('SELECT {DAY:completed_at} d, COUNT(*) v FROM tasks WHERE status="done" AND completed_at>=? AND completed_at<? GROUP BY d', [$from, $to], clone $fd, clone $td)],
 'goals' => ['Yaratilgan maqsadlar', series('SELECT {DAY:created_at} d, COUNT(*) v FROM goals WHERE created_at>=? AND created_at<? GROUP BY d', [$from, $to], clone $fd, clone $td)],
 'habits' => ['Odat bajarilishlari', series('SELECT log_date d, COUNT(*) v FROM habit_logs WHERE log_date>=DATE(?) AND log_date<DATE(?) GROUP BY d', [$from, $to], clone $fd, clone $td)],
 'reminders' => ['Yetkazilgan eslatmalar', series('SELECT {DAY:sent_at} d, COUNT(*) v FROM notification_queue WHERE kind="reminder" AND status="sent" AND sent_at>=? AND sent_at<? GROUP BY d', [$from, $to], clone $fd, clone $td)],
];
$exp = getp('export');
if ($exp !== '' && isset($data[$exp])) { need('analytics.export'); Audit::log('analytics', 'export', ['ref' => $exp, 'new' => ['from' => $from, 'to' => $to]]);
    csv_out("intizom_{$exp}_" . $fd->format('Ymd') . '.csv', ['sana', $data[$exp][0]], array_map(null, array_keys($data[$exp][1]), array_values($data[$exp][1]))); }
if ($exp === 'summary') {}
$totalUsers = (int)DB::val('SELECT COUNT(*) FROM users WHERE status<>"deleted"'); $payers = (int)DB::val('SELECT COUNT(DISTINCT user_id) FROM payments WHERE status="paid"');
$sent = (int)DB::val('SELECT COUNT(*) FROM reminder_logs WHERE status="sent" AND created_at>=? AND created_at<?', [$from, $to]); $fail = (int)DB::val('SELECT COUNT(*) FROM reminder_logs WHERE status="failed" AND created_at>=? AND created_at<?', [$from, $to]);
$mau = (int)DB::val('SELECT COUNT(DISTINCT user_id) FROM user_activity WHERE day>=DATE_SUB(UTC_DATE(),INTERVAL 30 DAY)'); $wau = (int)DB::val('SELECT COUNT(DISTINCT user_id) FROM user_activity WHERE day>=DATE_SUB(UTC_DATE(),INTERVAL 7 DAY)');
?>
<h1>Analytics</h1><p class="sub">Tanlangan oraliq: <?= e($fd->format('d.m.Y')) ?> — <?= e((clone $td)->modify('-1 day')->format('d.m.Y')) ?></p><?= range_form('analytics') ?>
<div class="grid g4"><?= stat_card('Jami foydalanuvchilar', n($totalUsers)) ?><?= stat_card('WAU / MAU', n($wau) . ' / ' . n($mau)) ?>
<?= stat_card('Premium konversiyasi', $totalUsers ? round($payers / $totalUsers * 100, 1) . '%' : '0%', n($payers) . ' to‘lovchi') ?>
<?= stat_card('Eslatma yetkazilishi', ($sent + $fail) ? round($sent / ($sent + $fail) * 100, 1) . '%' : '—', n($sent) . ' yuborilgan / ' . n($fail) . ' xato') ?></div><br>
<div class="grid g2"><?php foreach ($data as $k => [$lab, $ser]): ?><div class="card glass"><h2><?= e($lab) ?> <span class="muted">· jami <?= e(n(array_sum($ser))) ?></span></h2><?= chart($ser, in_array($k, ['revenue', 'tasks', 'habits', 'reminders'], true) ? 'bar' : 'line', $k === 'revenue' ? 'var(--gold)' : 'var(--ice)') ?>
<?php if (Auth::can('analytics.export')): ?><a class="btn ghost sm" href="<?= e(url('analytics', ['export' => $k, 'range' => $rk, 'from' => getp('from'), 'to' => getp('to')])) ?>">CSV (Excel)</a><?php endif; ?></div><?php endforeach; ?></div>
