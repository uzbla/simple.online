<?php
declare(strict_types=1);
final class Jobs {
    private static function once(string $key, string $stamp): bool {
        if (Settings::get($key) === $stamp) return false;
        Settings::set($key, $stamp); return true;
    }
    static function tick(): array {
        $out = ['reminders' => Reminders::dispatchDue(), 'campaigns' => Queue::expandCampaigns()];
        $out += Queue::process(40);
        Queue::finalize();
        $tz = new DateTimeZone(Settings::get('timezone', 'Asia/Tashkent')); $l = new DateTime('now', $tz);
        if ($l->format('H') >= 1 && self::once('job.daily', $l->format('Y-m-d'))) { self::daily($l); $out['daily'] = 1;
            if ($l->format('N') === '1' && self::once('job.weekly', $l->format('o-W'))) { self::weekly(); $out['weekly'] = 1; }
            if ($l->format('j') === '1' && self::once('job.monthly', $l->format('Y-m'))) { self::monthly(); $out['monthly'] = 1; } }
        return $out;
    }
    static function daily(DateTime $l): void {
        $exp = Subs::expireDue();
        DB::exec('UPDATE tasks SET status="overdue" WHERE status="todo" AND due_at IS NOT NULL AND due_at<?', [now()]);
        self::streaks();
        // Premium tugashi haqida ogohlantirish (bir marta: aynan N kun qolganda)
        $d = max(1, (int)Settings::get('premium_warn_days', '3'));
        foreach (DB::all('SELECT id, premium_until FROM users WHERE status="active" AND premium_until IS NOT NULL AND DATE(premium_until)=DATE_ADD(UTC_DATE(), INTERVAL ? DAY)', [$d]) as $u)
            Queue::direct((int)$u['id'], "💎 Premium obunangiz $d kundan so‘ng tugaydi (" . dt($u['premium_until'], 'd.m.Y') . ").", 'report');
        // Kun yakuni hisoboti
        foreach (DB::all('SELECT u.id, (SELECT COUNT(*) FROM tasks t WHERE t.user_id=u.id AND t.status="done" AND DATE(t.completed_at)=UTC_DATE()) done_c,
                 (SELECT COUNT(*) FROM tasks t WHERE t.user_id=u.id AND t.status IN ("todo","overdue") AND DATE(t.due_at)<=UTC_DATE()) left_c, u.streak
                 FROM users u JOIN user_settings s ON s.user_id=u.id WHERE u.status="active" AND s.daily_report=1 AND s.notifications_enabled=1') as $r)
            Queue::direct((int)$r['id'], "🌙 <b>Kun yakuni</b>\nBajarildi: {$r['done_c']} · Qoldi: {$r['left_c']} · Streak: {$r['streak']} 🔥\nHar bir kun — cho‘qqiga yana bir qadam.", 'report');
        slog('info', 'cron', "daily: expired=$exp");
    }
    static function weekly(): void {
        foreach (DB::all('SELECT u.id, (SELECT COUNT(*) FROM tasks t WHERE t.user_id=u.id AND t.status="done" AND t.completed_at>=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 7 DAY)) done_c,
                 (SELECT COUNT(*) FROM habit_logs h JOIN habits b ON b.id=h.habit_id WHERE b.user_id=u.id AND h.log_date>=DATE_SUB(UTC_DATE(),INTERVAL 7 DAY)) hab_c
                 FROM users u JOIN user_settings s ON s.user_id=u.id WHERE u.status="active" AND s.weekly_report=1 AND s.notifications_enabled=1') as $r)
            Queue::direct((int)$r['id'], "📅 <b>Haftalik hisobot</b>\nVazifalar: {$r['done_c']} · Odat bajarilishlari: {$r['hab_c']}", 'report');
        foreach (DB::all('SELECT user_id, title, end_date FROM goals WHERE status="active" AND end_date BETWEEN UTC_DATE() AND DATE_ADD(UTC_DATE(), INTERVAL 7 DAY)') as $g)
            Queue::direct((int)$g['user_id'], '🎯 Maqsad muddati yaqin: <b>' . e($g['title']) . '</b> (' . e($g['end_date']) . ')', 'report');
        slog('info', 'cron', 'weekly done');
    }
    static function monthly(): void { $r = Backup::create('scheduled'); slog($r['ok'] ? 'info' : 'error', 'cron', 'monthly backup ' . ($r['ok'] ? 'ok' : $r['error'])); }
    /** Odat va umumiy streak (kunlar ketma-ketligi; rejalanmagan kunlar sinmaydi) */
    static function streaks(): void {
        $today = new DateTime('today', new DateTimeZone(Settings::get('timezone', 'Asia/Tashkent')));
        foreach (DB::all('SELECT id, days_mask FROM habits WHERE status="active"') as $h) {
            $logs = array_flip(array_column(DB::all('SELECT log_date FROM habit_logs WHERE habit_id=? AND log_date>=DATE_SUB(CURDATE(), INTERVAL 400 DAY)', [$h['id']]), 'log_date'));
            $s = 0; $d = clone $today; $first = true;
            for ($i = 0; $i < 400; $i++, $d->modify('-1 day')) {
                if (!($h['days_mask'] & (1 << ((int)$d->format('N') - 1)))) continue;
                if (isset($logs[$d->format('Y-m-d')])) $s++; elseif ($first) { $first = false; continue; } else break;
                $first = false;
            }
            DB::exec('UPDATE habits SET streak=? WHERE id=?', [$s, $h['id']]);
        }
        foreach (DB::all('SELECT DISTINCT user_id FROM user_activity WHERE day>=DATE_SUB(CURDATE(), INTERVAL 1 DAY)') as $r) {
            $days = array_flip(array_column(DB::all('SELECT day FROM user_activity WHERE user_id=? AND day>=DATE_SUB(CURDATE(), INTERVAL 400 DAY)', [$r['user_id']]), 'day'));
            $s = 0; $d = clone $today; if (!isset($days[$d->format('Y-m-d')])) $d->modify('-1 day');
            while (isset($days[$d->format('Y-m-d')])) { $s++; $d->modify('-1 day'); }
            DB::exec('UPDATE users SET streak=? WHERE id=?', [$s, $r['user_id']]);
        }
        DB::exec('UPDATE users SET streak=0 WHERE streak>0 AND id NOT IN (SELECT user_id FROM user_activity WHERE day>=DATE_SUB(CURDATE(), INTERVAL 1 DAY))');
    }
}
