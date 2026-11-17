<?php
declare(strict_types=1);
final class Xp {
    /** Qoida bo‘yicha XP beradi. Bir xil (user, action, ref) uchun faqat bir marta. true = berildi */
    static function award(int $userId, string $action, string $refId): bool {
        $rule = DB::one('SELECT xp FROM xp_rules WHERE action_type=? AND active=1', [$action]);
        if (!$rule || (int)$rule['xp'] === 0) return false;
        return self::add($userId, $action, $refId, (int)$rule['xp'], null, null, null);
    }
    static function add(int $userId, string $action, string $refId, int $amount, ?string $reason, ?int $adminId, ?int $dummy): bool {
        return DB::tx(function () use ($userId, $action, $refId, $amount, $reason, $adminId) {
            $n = DB::exec('INSERT IGNORE INTO xp_transactions (user_id,action_type,ref_id,amount,reason,admin_id) VALUES (?,?,?,?,?,?)',
                [$userId, $action, $refId, $amount, $reason, $adminId]);
            if ($n === 0) return false;
            DB::exec('UPDATE users SET xp_total=GREATEST(0,xp_total+?) WHERE id=?', [$amount, $userId]);
            self::recalc($userId); return true;
        });
    }
    static function recalc(int $userId): void {
        $xp = (int)DB::val('SELECT xp_total FROM users WHERE id=?', [$userId]);
        $lvl = DB::one('SELECT id FROM level_rules WHERE min_xp<=? ORDER BY min_xp DESC LIMIT 1', [$xp]);
        if (!$lvl) return;
        DB::exec('UPDATE users SET level_id=? WHERE id=?', [$lvl['id'], $userId]);
        DB::exec('INSERT IGNORE INTO user_levels (user_id,level_id) SELECT ?,id FROM level_rules WHERE min_xp<=?', [$userId, $xp]);
    }
    static function recalcAll(): void {
        foreach (DB::all('SELECT id FROM users') as $u) self::recalc((int)$u['id']);
    }
}
