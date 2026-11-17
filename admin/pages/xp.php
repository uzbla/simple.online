<?php
$super = (int)Auth::admin()['role_id'] === 1;
if (post_guard()) {
    $act = post('act');
    if ($act === 'rules') {
        foreach (DB::all('SELECT * FROM xp_rules') as $r) { $k = $r['action_type']; $xp = max(0, min(100000, (int)($_POST['xp'][$k] ?? 0))); $on = isset($_POST['on'][$k]) ? 1 : 0;
            if ($xp !== (int)$r['xp'] || $on !== (int)$r['active']) { DB::exec('UPDATE xp_rules SET xp=?, active=? WHERE action_type=?', [$xp, $on, $k]); Audit::log('xp', 'rule_update', ['ref' => $k, 'old' => ['xp' => (int)$r['xp'], 'active' => (int)$r['active']], 'new' => ['xp' => $xp, 'active' => $on]]); } }
        flash('ok', 'XP qoidalari saqlandi.');
    } elseif ($act === 'levels') {
        $names = $_POST['name'] ?? []; $mins = $_POST['min'] ?? []; $icons = $_POST['icon'] ?? []; $ok = true; $prev = -1; $rows = [];
        foreach (DB::all('SELECT id FROM level_rules ORDER BY min_xp') as $i => $r) { $id = (int)$r['id']; $nm = trim((string)($names[$id] ?? '')); $mn = (int)($mins[$id] ?? 0);
            if ($nm === '' || ($i === 0 ? $mn !== 0 : $mn <= $prev)) { $ok = false; break; } $prev = $mn; $rows[$id] = [$nm, $mn, mb_substr(trim((string)($icons[$id] ?? '⛰️')), 0, 8)]; }
        if (!$ok) flash('err', 'Daraja chegaralari birinchisi 0 va o‘sib boruvchi bo‘lishi, nomlar bo‘sh bo‘lmasligi kerak.');
        else { $old = DB::all('SELECT id,name,min_xp,icon FROM level_rules ORDER BY min_xp');
            DB::tx(function () use ($rows) { DB::exec('UPDATE level_rules SET min_xp=-id'); foreach ($rows as $id => [$nm, $mn, $ic]) DB::exec('UPDATE level_rules SET name=?, min_xp=?, icon=? WHERE id=?', [$nm, $mn, $ic, $id]); });
            Xp::recalcAll(); Audit::log('xp', 'levels_update', ['old' => $old, 'new' => $rows]); flash('ok', 'Darajalar saqlandi, foydalanuvchi darajalari qayta hisoblandi.'); }
    } elseif ($act === 'adjust' && $super) {
        $uid = (int)post('user'); $amt = (int)post('amount'); $reason = post('reason');
        if (!DB::val('SELECT id FROM users WHERE id=?', [$uid]) || $amt === 0 || abs($amt) > 100000 || $reason === '') flash('err', 'Foydalanuvchi ID, summa (≠0) va sabab majburiy.');
        else { $old = (int)DB::val('SELECT xp_total FROM users WHERE id=?', [$uid]);
            Xp::add($uid, 'admin_adjust', bin2hex(random_bytes(8)), $amt, $reason, (int)Auth::admin()['id'], null);
            Audit::log('xp', 'manual_adjust', ['user' => $uid, 'old' => ['xp' => $old], 'new' => ['xp' => (int)DB::val('SELECT xp_total FROM users WHERE id=?', [$uid]), 'delta' => $amt], 'reason' => $reason]); flash('ok', 'XP o‘zgartirildi.'); }
    }
    redirect(url('xp'));
}
?>
<h1>XP & Everest</h1><p class="sub">XP miqdorlari va darajalar. Bir xil amal uchun XP faqat bir marta beriladi (DB unique kalit).</p>
<div class="grid g2"><form class="card glass" method="post"><?= csrf() ?><input type="hidden" name="act" value="rules"><h2>XP qoidalari</h2><table><tr><th>Amal</th><th>XP</th><th>Faol</th></tr>
<?php foreach (DB::all('SELECT * FROM xp_rules ORDER BY action_type') as $r): ?><tr><td><?= e($r['label']) ?></td><td><input type="number" min="0" name="xp[<?= e($r['action_type']) ?>]" value="<?= (int)$r['xp'] ?>"></td><td><input type="checkbox" name="on[<?= e($r['action_type']) ?>]" <?= $r['active'] ? 'checked' : '' ?>></td></tr><?php endforeach; ?></table><p><button>Saqlash</button></p></form>
<form class="card glass" method="post"><?= csrf() ?><input type="hidden" name="act" value="levels"><h2>Everest darajalari</h2><table><tr><th>Ikonka</th><th>Nomi</th><th>Min XP</th><th>Foydalanuvchilar</th></tr>
<?php foreach (DB::all('SELECT l.*, (SELECT COUNT(*) FROM users u WHERE u.level_id=l.id) c FROM level_rules l ORDER BY min_xp') as $l): ?><tr><td><input name="icon[<?= (int)$l['id'] ?>]" value="<?= e($l['icon']) ?>" maxlength="8"></td><td><input name="name[<?= (int)$l['id'] ?>]" value="<?= e($l['name']) ?>" maxlength="64"></td><td><input type="number" min="0" name="min[<?= (int)$l['id'] ?>]" value="<?= (int)$l['min_xp'] ?>"></td><td><?= n($l['c']) ?></td></tr><?php endforeach; ?></table><p><button>Saqlash</button></p></form></div>
<?php if ($super): ?><form class="card glass" method="post"><?= csrf() ?><input type="hidden" name="act" value="adjust"><h2>Qo‘lda XP qo‘shish/ayirish <span class="badge warn">Faqat Super Admin</span></h2>
<div class="row"><div><label>Foydalanuvchi ID</label><input type="number" name="user" min="1" required></div><div><label>Miqdor (manfiy = ayirish)</label><input type="number" name="amount" required></div><div><label>Sabab (majburiy)</label><input name="reason" maxlength="255" required></div></div><p><button class="btn gold" data-confirm="XP o‘zgartirilsinmi? Audit logga yoziladi.">Qo‘llash</button></p></form><?php endif; ?>
<div class="card glass tw"><h2>Oxirgi XP operatsiyalari</h2><table><tr><th>Vaqt</th><th>User</th><th>Amal</th><th>Ref</th><th>XP</th><th>Sabab</th></tr>
<?php foreach (DB::all('SELECT * FROM xp_transactions ORDER BY id DESC LIMIT 25') as $t) echo '<tr><td>' . e(dt($t['created_at'])) . '</td><td><a href="' . e(url('users', ['id' => $t['user_id']])) . '">#' . (int)$t['user_id'] . '</a></td><td>' . e($t['action_type']) . '</td><td><code>' . e($t['ref_id']) . '</code></td><td>' . ($t['amount'] > 0 ? '+' : '') . (int)$t['amount'] . '</td><td>' . e($t['reason']) . '</td></tr>'; ?></table></div>
