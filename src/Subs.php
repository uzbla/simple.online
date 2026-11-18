<?php
declare(strict_types=1);
final class Subs {
    static function recompute(int $userId): ?string {
        $u = DB::val('SELECT MAX(ends_at) FROM subscriptions WHERE user_id=? AND status="active" AND ends_at>UTC_TIMESTAMP()', [$userId]);
        DB::exec('UPDATE users SET premium_until=? WHERE id=?', [$u, $userId]);
        return $u;
    }
    /** Muddatni mavjud faol obuna oxiridan uzaytiradi. Tranzaksiya ichida chaqirilishi mumkin. */
    static function grant(int $userId, int $planId, int $days, string $source, ?int $paymentId = null, ?int $adminId = null, ?string $reason = null, bool $trial = false): int {
        $cur = DB::val('SELECT MAX(ends_at) FROM subscriptions WHERE user_id=? AND status="active" AND ends_at>UTC_TIMESTAMP()', [$userId]);
        $start = $cur && !$trial ? $cur : now();
        $end = gmdate('Y-m-d H:i:s', strtotime($start . ' UTC') + $days * 86400);
        $id = DB::insert('INSERT INTO subscriptions (user_id,plan_id,status,is_trial,source,payment_id,starts_at,ends_at,granted_by,reason) VALUES (?,?,?,?,?,?,?,?,?,?)',
            [$userId, $planId, 'active', (int)$trial, $source, $paymentId, $start, $end, $adminId, $reason]);
        self::recompute($userId); return $id;
    }
    /** Birinchi marta ro‘yxatdan o‘tganlarga sinov muddati beradi (sozlamadagi kunlar) */
    static function trialIfNew(int $userId): void {
        $days = (int)Settings::get('trial_days', '0');
        if ($days < 1 || (int)DB::val('SELECT COUNT(*) FROM subscriptions WHERE user_id=?', [$userId]) > 0) return;
        $plan = DB::one('SELECT id FROM subscription_plans WHERE code="monthly" AND deleted_at IS NULL');
        if ($plan) self::grant($userId, (int)$plan['id'], $days, 'trial', null, null, 'Ro‘yxatdan o‘tish sinovi', true);
    }
    static function cancelAll(int $userId): int {
        $n = DB::exec('UPDATE subscriptions SET status="cancelled", cancelled_at=? WHERE user_id=? AND status="active"', [now(), $userId]);
        self::recompute($userId); return $n;
    }
    /** Kunlik: muddati o‘tganlarni expired qilish */
    static function expireDue(): int {
        $users = DB::all('SELECT DISTINCT user_id FROM subscriptions WHERE status="active" AND ends_at<=?', [now()]);
        DB::exec('UPDATE subscriptions SET status="expired" WHERE status="active" AND ends_at<=?', [now()]);
        foreach ($users as $u) self::recompute((int)$u['user_id']);
        return count($users);
    }
}
