<?php
$id = (int)getp('id');
if ($id) { require __DIR__ . '/user_detail.php'; return; }
$pii = Auth::can('users.pii');
$q = getp('q'); $st = getp('status'); $pr = getp('premium'); $page = max(1, (int)getp('page', '1')); $per = 25;
$w = ['u.status<>"deleted"']; $p = [];
if ($q !== '') {
    if (ctype_digit($q) && $pii) { $w[] = '(u.telegram_id=? OR u.id=?)'; array_push($p, $q, $q); }
    elseif (ctype_digit($q)) { $w[] = 'u.id=?'; $p[] = $q; }
    else { $w[] = '(CONCAT(u.first_name," ",u.last_name) LIKE ?' . ($pii ? ' OR u.username LIKE ?' : '') . ')'; $l = '%' . addcslashes(ltrim($q, '@'), '%_\\') . '%'; $p[] = $l; if ($pii) $p[] = $l; }
}
if (in_array($st, ['active', 'blocked'], true)) { $w[] = 'u.status=?'; $p[] = $st; }
if ($pr === 'yes') $w[] = 'u.premium_until>UTC_TIMESTAMP()'; elseif ($pr === 'no') $w[] = '(u.premium_until IS NULL OR u.premium_until<=UTC_TIMESTAMP())';
$W = implode(' AND ', $w);
$total = (int)DB::val("SELECT COUNT(*) FROM users u WHERE $W", $p);
$rows = DB::all("SELECT u.*, l.name AS lvl FROM users u LEFT JOIN level_rules l ON l.id=u.level_id WHERE $W ORDER BY u.id DESC LIMIT $per OFFSET " . (($page - 1) * $per), $p);
$qs = ['q' => $q, 'status' => $st, 'premium' => $pr];
?>
<h1>Foydalanuvchilar</h1><p class="sub">Qidirish, filtrlash va boshqarish</p>
<form class="filters" method="get"><input type="hidden" name="p" value="users"><input name="q" value="<?= e($q) ?>" placeholder="Ism<?= $pii ? ', @username yoki Telegram ID' : '' ?>">
<select name="status"><option value="">Holat: hammasi</option><option value="active"<?= $st === 'active' ? ' selected' : '' ?>>Faol</option><option value="blocked"<?= $st === 'blocked' ? ' selected' : '' ?>>Bloklangan</option></select>
<select name="premium"><option value="">Tarif: hammasi</option><option value="yes"<?= $pr === 'yes' ? ' selected' : '' ?>>Premium</option><option value="no"<?= $pr === 'no' ? ' selected' : '' ?>>Free</option></select><button>Qidirish</button></form>
<div class="card glass tw"><table><tr><th>ID</th><th>Foydalanuvchi</th><?php if ($pii): ?><th>Telegram</th><?php endif; ?><th>Ro‘yxatdan o‘tgan</th><th>Oxirgi faollik</th><th>Tarif</th><th>Daraja</th><th>Holat</th></tr>
<?php foreach ($rows as $u): $prem = $u['premium_until'] && $u['premium_until'] > now(); ?>
<tr><td><a href="<?= e(url('users', ['id' => $u['id']])) ?>">#<?= (int)$u['id'] ?></a></td><td><a href="<?= e(url('users', ['id' => $u['id']])) ?>"><?= e(trim($u['first_name'] . ' ' . $u['last_name']) ?: '—') ?></a></td>
<?php if ($pii): ?><td><?= e($u['telegram_id']) ?><?= $u['username'] ? ' <span class="muted">@' . e($u['username']) . '</span>' : '' ?></td><?php endif; ?>
<td><?= e(dt($u['created_at'])) ?></td><td><?= e(dt($u['last_active_at'])) ?></td><td><?= $prem ? badge('active', 'Premium · ' . dt($u['premium_until'], 'd.m.Y')) : badge('mute', 'Free') ?></td>
<td><?= e($u['lvl'] ?? '—') ?> · <?= n($u['xp_total']) ?> XP</td><td><?= badge($u['status']) ?></td></tr>
<?php endforeach; if (!$rows): ?><tr><td colspan="8" class="muted">Hech narsa topilmadi</td></tr><?php endif; ?></table></div>
<?= pager($total, $per, $page, 'users', $qs) ?>
