<?php
$u = DB::one('SELECT u.*, l.name AS lvl, l.icon FROM users u LEFT JOIN level_rules l ON l.id=u.level_id WHERE u.id=? AND u.status<>"deleted"', [$id]);
if (!$u) { echo '<div class="card glass">Foydalanuvchi topilmadi. <a href="' . e(url('users')) . '">Ro‘yxat</a></div>'; return; }
$canM = Auth::can('users.manage'); $canP = Auth::can('premium.manage'); $pii = Auth::can('users.pii');
$back = url('users', ['id' => $id]);
if (post_guard()) {
    $act = post('act'); $reason = post('reason');
    $need = fn() => $reason === '' ? (flash('err', 'Sabab majburiy.') || true) : false;
    if (in_array($act, ['block', 'unblock'], true) && $canM) {
        if (!$need()) {
            DB::exec('UPDATE users SET status=?, blocked_reason=? WHERE id=?', [$act === 'block' ? 'blocked' : 'active', $act === 'block' ? $reason : null, $id]);
            if ($act === 'block') DB::exec('UPDATE notification_queue SET status="cancelled" WHERE user_id=? AND status="pending"', [$id]);
            Audit::log('users', $act, ['user' => $id, 'old' => ['status' => $u['status']], 'new' => ['status' => $act === 'block' ? 'blocked' : 'active'], 'reason' => $reason]); flash('ok', 'Bajarildi.');
        }
    } elseif ($act === 'grant' && $canP) {
        $plan = DB::one('SELECT * FROM subscription_plans WHERE id=? AND deleted_at IS NULL AND code<>"free"', [(int)post('plan')]); $days = (int)post('days');
        if (!$plan || $days < 1 || $days > 3650) flash('err', 'Tarif yoki kunlar noto‘g‘ri (1–3650).');
        elseif (!$need()) {
            DB::tx(fn() => Subs::grant($id, (int)$plan['id'], $days, 'admin', null, (int)Auth::admin()['id'], $reason));
            Audit::log('premium', 'grant', ['user' => $id, 'old' => ['premium_until' => $u['premium_until']], 'new' => ['plan' => $plan['code'], 'days' => $days], 'reason' => $reason]); flash('ok', 'Premium berildi.');
        }
    } elseif ($act === 'set_expiry' && $canP) {
        $new = local_to_utc(post('until'));
        $sub = DB::one('SELECT id FROM subscriptions WHERE user_id=? AND status="active" ORDER BY ends_at DESC LIMIT 1', [$id]);
        if (!$new || !$sub || $new <= now()) flash('err', 'Faol obuna yo‘q yoki sana kelajakda emas.');
        elseif (!$need()) {
            DB::tx(function () use ($id, $sub, $new) { DB::exec('UPDATE subscriptions SET ends_at=? WHERE id=?', [$new, $sub['id']]);
                DB::exec('UPDATE subscriptions SET status="cancelled", cancelled_at=? WHERE user_id=? AND status="active" AND id<>?', [now(), $id, $sub['id']]); Subs::recompute($id); });
            Audit::log('premium', 'set_expiry', ['user' => $id, 'old' => ['premium_until' => $u['premium_until']], 'new' => ['premium_until' => $new], 'reason' => $reason]); flash('ok', 'Muddat o‘zgartirildi.');
        }
    } elseif ($act === 'revoke' && $canP) {
        if (!$need()) { $c = Subs::cancelAll($id);
            Audit::log('premium', 'revoke', ['user' => $id, 'old' => ['premium_until' => $u['premium_until']], 'new' => ['premium_until' => null, 'cancelled' => $c], 'reason' => $reason]); flash('ok', 'Premium bekor qilindi.'); }
    } elseif ($act === 'message' && $canM) {
        $msg = post('message'); if ($msg === '' || mb_strlen($msg) > 3500) flash('err', 'Xabar 1–3500 belgi bo‘lishi kerak.');
        elseif (Queue::direct($id, e($msg))) { Audit::log('users', 'message', ['user' => $id, 'new' => ['len' => mb_strlen($msg)]]); flash('ok', 'Xabar navbatga qo‘yildi.'); }
        else flash('err', 'Faol bo‘lmagan foydalanuvchiga yuborib bo‘lmaydi.');
    } elseif ($act === 'reveal' && $pii) {
        redirect(url('users', ['id' => $id, 'reveal' => 1]));
    }
    redirect($back);
}
$reveal = $pii && getp('reveal') === '1';
if ($reveal) Audit::log('users', 'pii_view', ['user' => $id, 'reason' => 'Maxfiy maydonlar ko‘rildi']);
$cnt = fn(string $t, string $w = '1') => (int)DB::val("SELECT COUNT(*) FROM $t WHERE $w AND user_id=?", [$id]);
$subs = DB::all('SELECT s.*, p.name pn FROM subscriptions s JOIN subscription_plans p ON p.id=s.plan_id WHERE s.user_id=? ORDER BY s.id DESC LIMIT 10', [$id]);
$plans = DB::all('SELECT * FROM subscription_plans WHERE deleted_at IS NULL AND code<>"free" AND is_active=1 ORDER BY sort_order');
$prem = $u['premium_until'] && $u['premium_until'] > now();
$remOn = $cnt('reminders', 'enabled=1'); $remAll = $cnt('reminders');
?>
<p><a href="<?= e(url('users')) ?>">← Foydalanuvchilar</a></p>
<h1><?= e(trim($u['first_name'] . ' ' . $u['last_name']) ?: 'Foydalanuvchi') ?> <?= badge($u['status']) ?></h1>
<div class="grid g2"><div class="card glass"><h2>Ma’lumotlar</h2><dl class="kv">
<dt>ID</dt><dd>#<?= (int)$u['id'] ?></dd>
<dt>Telegram ID</dt><dd><?= $reveal ? e($u['telegram_id']) : e(mask_tg($u['telegram_id'])) ?><?= $u['username'] ? ($reveal ? ' · @' . e($u['username']) : '') : '' ?>
<?php if ($pii && !$reveal): ?><form method="post" class="inline"><?= csrf() ?><input type="hidden" name="act" value="reveal"><button class="btn ghost sm" data-confirm="Maxfiy ma’lumotni ochish audit logga yoziladi. Davom etasizmi?">Ochish</button></form><?php endif; ?></dd>
<dt>Ro‘yxatdan o‘tgan</dt><dd><?= e(dt($u['created_at'])) ?></dd><dt>Oxirgi faollik</dt><dd><?= e(dt($u['last_active_at'])) ?></dd>
<dt>Til / zona</dt><dd><?= e($u['language']) ?> · <?= e($u['timezone']) ?></dd>
<dt>Premium</dt><dd><?= $prem ? badge('active', 'Premium') . ' ' . e(dt($u['premium_until'])) . ' gacha' : badge('mute', 'Free') ?></dd>
<dt>Daraja</dt><dd><?= e(($u['icon'] ?? '') . ' ' . ($u['lvl'] ?? '—')) ?> · <?= n($u['xp_total']) ?> XP · streak <?= (int)$u['streak'] ?></dd>
<dt>Vazifalar (bajarilgan)</dt><dd><?= n($cnt('tasks', 'status="done"')) ?> / <?= n($cnt('tasks')) ?></dd>
<dt>Maqsadlar</dt><dd><?= n($cnt('goals')) ?></dd><dt>Odatlar</dt><dd><?= n($cnt('habits')) ?></dd>
<dt>Eslatmalar</dt><dd><?= n($remOn) ?> faol / <?= n($remAll) ?> jami</dd>
<?php if ($u['status'] === 'blocked'): ?><dt>Bloklash sababi</dt><dd><?= e($u['blocked_reason']) ?></dd><?php endif; ?></dl></div>
<div class="card glass"><h2>Boshqaruv</h2>
<?php if ($canM): ?><form method="post"><?= csrf() ?><input type="hidden" name="act" value="<?= $u['status'] === 'blocked' ? 'unblock' : 'block' ?>"><label>Sabab (majburiy)</label><input name="reason" required maxlength="255">
<p><button class="btn <?= $u['status'] === 'blocked' ? '' : 'danger' ?>"><?= $u['status'] === 'blocked' ? 'Qayta faollashtirish' : 'Hisobni bloklash' ?></button></p></form>
<form method="post"><?= csrf() ?><input type="hidden" name="act" value="message"><label>Foydalanuvchiga xabar (Telegram)</label><textarea name="message" maxlength="3500" required></textarea><p><button class="btn ghost">Yuborish</button></p></form><?php endif; ?></div></div>
<?php if ($canP): ?><div class="card glass"><h2>Premium boshqaruvi</h2><div class="row">
<form method="post"><?= csrf() ?><input type="hidden" name="act" value="grant"><label>Tarif</label><select name="plan"><?php foreach ($plans as $pl): ?><option value="<?= (int)$pl['id'] ?>"><?= e($pl['name']) ?></option><?php endforeach; ?></select>
<label>Kunlar</label><input type="number" name="days" min="1" max="3650" value="30" required><label>Sabab</label><input name="reason" required maxlength="255"><p><button class="btn gold">Premium berish</button></p></form>
<?php if ($prem): ?><form method="post"><?= csrf() ?><input type="hidden" name="act" value="set_expiry"><label>Yangi tugash sanasi</label><input type="datetime-local" name="until" value="<?= e(utc_to_local_input($u['premium_until'])) ?>" required><label>Sabab</label><input name="reason" required maxlength="255"><p><button class="btn ghost">Muddatni o‘zgartirish</button></p></form>
<form method="post"><?= csrf() ?><input type="hidden" name="act" value="revoke"><label>Sabab</label><input name="reason" required maxlength="255"><p><button class="btn danger" data-confirm="Premium bekor qilinsinmi?">Premiumni bekor qilish</button></p></form><?php endif; ?></div></div><?php endif; ?>
<div class="card glass tw"><h2>Obuna tarixi</h2><table><tr><th>Tarif</th><th>Manba</th><th>Boshlanish</th><th>Tugash</th><th>Holat</th><th>Sabab</th></tr>
<?php foreach ($subs as $s): ?><tr><td><?= e($s['pn']) ?><?= $s['is_trial'] ? ' (sinov)' : '' ?></td><td><?= e($s['source']) ?></td><td><?= e(dt($s['starts_at'])) ?></td><td><?= e(dt($s['ends_at'])) ?></td><td><?= badge($s['status']) ?></td><td><?= e($s['reason']) ?></td></tr><?php endforeach; if (!$subs) echo '<tr><td colspan="6" class="muted">Obuna yo‘q</td></tr>'; ?></table></div>
