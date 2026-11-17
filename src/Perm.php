<?php
declare(strict_types=1);
final class Perm {
    /** permission => [bo‘lim, tavsif, delegatsiya qilib bo‘ladimi] */
    const ALL = [
        'dashboard.view' => ['Dashboard', 'Dashboardni ko‘rish', true],
        'users.view' => ['Foydalanuvchilar', 'Ro‘yxat va profilni ko‘rish', true],
        'users.pii' => ['Foydalanuvchilar', 'Maxfiy ma’lumotlarni ko‘rish (audit bilan)', true],
        'users.manage' => ['Foydalanuvchilar', 'Bloklash / faollashtirish / xabar yuborish', true],
        'premium.manage' => ['Obunalar', 'Tariflar va Premium berish/bekor qilish', true],
        'payments.view' => ['To‘lovlar', 'To‘lovlarni ko‘rish', true],
        'payments.manage' => ['To‘lovlar', 'Kutilayotgan to‘lovni bekor qilish', true],
        'notify.send' => ['Bildirishnomalar', 'Ommaviy xabar yuborish', true],
        'content.manage' => ['Kontent', 'Kontentni boshqarish', true],
        'analytics.view' => ['Analytics', 'Statistikani ko‘rish', true],
        'analytics.export' => ['Analytics', 'CSV eksport', true],
        'support.manage' => ['Support', 'Murojaatlarga javob berish', true],
        'reminders.view' => ['Eslatmalar', 'Eslatmalar nazorati', true],
        'reminders.content' => ['Eslatmalar', 'Shaxsiy eslatma matnini ko‘rish (audit)', true],
        'xp.manage' => ['XP', 'XP qoidalari va darajalar', true],
        'logs.view' => ['Xavfsizlik', 'Admin loglarini ko‘rish', true],
        'security.view' => ['Xavfsizlik', 'Security Center', true],
        'security.manage' => ['Xavfsizlik', 'Sessiyalarni bekor qilish', true],
        'settings.manage' => ['Tizim', 'Tizim sozlamalari', true],
        'system.view' => ['Tizim', 'Monitoring', true],
        'backup.manage' => ['Tizim', 'Backup yaratish/yuklash', true],
        // Faqat Super Admin (delegatsiya qilinmaydi):
        'xp.adjust' => ['XP', 'Qo‘lda XP qo‘shish/ayirish', false],
        'admins.manage' => ['Adminlar', 'Adminlarni boshqarish', false],
        'backup.restore' => ['Tizim', 'Backupni tiklash', false],
    ];
    private static array $cache = [];
    static function has(array $admin, string $perm): bool {
        if (!isset(self::ALL[$perm])) return false;
        if ((int)$admin['role_id'] === 1) return true;
        if (!self::ALL[$perm][2]) return false;
        $id = (int)$admin['id'];
        if (!isset(self::$cache[$id])) {
            $set = [];
            foreach (DB::all('SELECT permission FROM admin_permissions WHERE role_id=? AND granted=1', [$admin['role_id']]) as $r) $set[$r['permission']] = true;
            foreach (DB::all('SELECT permission,granted FROM admin_permissions WHERE admin_id=?', [$id]) as $r) {
                if ($r['granted']) $set[$r['permission']] = true; else unset($set[$r['permission']]);
            }
            self::$cache[$id] = $set;
        }
        return isset(self::$cache[$id][$perm]);
    }
    static function reset(): void { self::$cache = []; }
}
