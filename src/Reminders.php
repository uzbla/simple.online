<?php
declare(strict_types=1);
final class Reminders {
    /** Keyingi ishga tushish vaqtini (UTC) hisoblaydi. $afterUtc dan keyin bo‘lgan eng yaqin mos kun. null = qayta ishga tushmaydi */
    static function next(array $r, string $tz, ?string $afterUtc = null): ?string {
        $zone = new DateTimeZone($tz ?: 'Asia/Tashkent');
        $after = new DateTime($afterUtc ?? 'now', new DateTimeZone('UTC'));
        $lead = (int)$r['lead_minutes'];
        $time = substr((string)$r['time_of_day'], 0, 8);
        if ((int)$r['days_mask'] === 0) {   // bir martalik
            if (empty($r['once_date'])) return null;
            $t = new DateTime($r['once_date'] . ' ' . $time, $zone); $t->modify("-$lead minutes");
            $t->setTimezone(new DateTimeZone('UTC'));
            return $t > $after ? $t->format('Y-m-d H:i:s') : null;
        }
        $day = (clone $after)->setTimezone($zone)->setTime(0, 0)->modify('-1 day');
        for ($i = 0; $i < 9; $i++) {
            $day->modify('+1 day');
            if (!((int)$r['days_mask'] & (1 << ((int)$day->format('N') - 1)))) continue;
            $t = new DateTime($day->format('Y-m-d') . ' ' . $time, $zone); $t->modify("-$lead minutes");
            $t->setTimezone(new DateTimeZone('UTC'));
            if ($t > $after) return $t->format('Y-m-d H:i:s');
        }
        return null;
    }
    /** Vaqti kelgan eslatmalarni navbatga qo‘yadi. Atomik "claim" tufayli ikki marta yuborilmaydi. */
    static function dispatchDue(int $limit = 500): int {
        $due = DB::all('SELECT r.*, u.telegram_id, u.timezone, u.status AS ustatus FROM reminders r JOIN users u ON u.id=r.user_id
                        WHERE r.enabled=1 AND r.next_run_at IS NOT NULL AND r.next_run_at<=? ORDER BY r.next_run_at LIMIT ' . $limit, [now()]);
        $n = 0;
        foreach ($due as $r) {
            $next = self::next($r, $r['timezone'], now());
            // claim: faqat next_run_at hali o‘zgarmagan bo‘lsa
            $claimed = DB::exec('UPDATE reminders SET next_run_at=?, enabled=? WHERE id=? AND next_run_at=?',
                [$next, $next === null ? 0 : 1, $r['id'], $r['next_run_at']]);
            if ($claimed !== 1) continue;
            try {
                DB::tx(function () use ($r, &$n) {
                    $ins = DB::exec('INSERT IGNORE INTO reminder_logs (reminder_id,scheduled_for,status) VALUES (?,?,"queued")', [$r['id'], $r['next_run_at']]);
                    if ($ins === 0) return;   // allaqachon bor — takror yo‘q
                    if ($r['ustatus'] !== 'active') { DB::exec('UPDATE reminder_logs SET status="failed", error="Foydalanuvchi faol emas" WHERE reminder_id=? AND scheduled_for=?', [$r['id'], $r['next_run_at']]); return; }
                    DB::exec('INSERT INTO notification_queue (kind,reminder_id,user_id,chat_id,text) VALUES ("reminder",?,?,?,?)',
                        [$r['id'], $r['user_id'], $r['telegram_id'], '🔔 ' . e($r['message'])]);
                    $n++;
                });
            } catch (Throwable $e) { slog('error', 'reminders', 'dispatch: ' . $e->getMessage(), ['reminder' => $r['id']]); }
        }
        return $n;
    }
}
