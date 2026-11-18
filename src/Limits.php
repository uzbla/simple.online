<?php
declare(strict_types=1);
final class Limits {
    const KEYS = ['max_goals', 'max_habits', 'max_reminders', 'full_stats', 'finance_reports', 'export_data'];
    /** Foydalanuvchining amaldagi tarif limitlari (faol obuna bo‘lmasa — Free) */
    static function of(int $uid): array {
        $r = DB::one('SELECT p.limits_json FROM subscriptions s JOIN subscription_plans p ON p.id=s.plan_id WHERE s.user_id=? AND s.status="active" AND s.ends_at>UTC_TIMESTAMP() ORDER BY s.ends_at DESC LIMIT 1', [$uid])
           ?? DB::one('SELECT limits_json FROM subscription_plans WHERE code="free" LIMIT 1');
        $l = json_decode((string)($r['limits_json'] ?? '{}'), true) ?: [];
        foreach (self::KEYS as $k) $l[$k] = (int)($l[$k] ?? 0);
        return $l;
    }
    static function allows(int $limit, int $current): bool { return $limit < 0 || $current < $limit; }
}
