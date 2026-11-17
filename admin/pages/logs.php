<?php
$f = ['admin' => (int)getp('admin'), 'section' => getp('section'), 'action' => getp('action'), 'user' => (int)getp('user'), 'from' => getp('from'), 'to' => getp('to')];
$w = ['1']; $p = [];
if ($f['admin']) { $w[] = 'l.admin_id=?'; $p[] = $f['admin']; }
if ($f['section'] !== '') { $w[] = 'l.section=?'; $p[] = $f['section']; }
if ($f['action'] !== '') { $w[] = 'l.action LIKE ?'; $p[] = '%' . addcslashes($f['action'], '%_\\') . '%'; }
if ($f['user']) { $w[] = 'l.target_user_id=?'; $p[] = $f['user']; }
if ($f['from'] !== '' && ($t = local_to_utc($f['from'] . ' 00:00'))) { $w[] = 'l.created_at>=?'; $p[] = $t; }
if ($f['to'] !== '' && ($t = local_to_utc($f['to'] . ' 23:59:59'))) { $w[] = 'l.created_at<=?'; $p[] = $t; }
$W = implode(' AND ', $w); $page = max(1, (int)getp('page', '1')); $tot = (int)DB::val("SELECT COUNT(*) FROM admin_logs l WHERE $W", $p);
$rows = DB::all("SELECT l.* FROM admin_logs l WHERE $W ORDER BY l.id DESC LIMIT 40 OFFSET " . (($page - 1) * 40), $p);
$secs = array_column(DB::all('SELECT DISTINCT section FROM admin_logs ORDER BY section'), 'section');
?>
<h1>Admin loglari</h1><p class="sub">Loglar faqat qo‘shiladi: bazada UPDATE/DELETE triggerlar bilan bloklangan.</p>
<form class="filters" method="get"><input type="hidden" name="p" value="logs"><select name="admin"><option value="">Admin: hammasi</option><?php foreach (DB::all('SELECT id,login FROM admins ORDER BY login') as $a) echo '<option value="' . (int)$a['id'] . '"' . ($f['admin'] == $a['id'] ? ' selected' : '') . '>' . e($a['login']) . '</option>'; ?></select>
<select name="section"><option value="">Bo‘lim: hammasi</option><?php foreach ($secs as $s) echo '<option' . ($f['section'] === $s ? ' selected' : '') . '>' . e($s) . '</option>'; ?></select><input name="action" value="<?= e($f['action']) ?>" placeholder="Amal"><input name="user" value="<?= $f['user'] ?: '' ?>" placeholder="User ID" type="number">
<input type="date" name="from" value="<?= e($f['from']) ?>"><input type="date" name="to" value="<?= e($f['to']) ?>"><button>Filtr</button></form>
<div class="card glass tw"><table><tr><th>Vaqt</th><th>Admin</th><th>Bo‘lim / amal</th><th>Obyekt</th><th>Oldingi → Yangi</th><th>Sabab</th><th>IP / sessiya</th></tr>
<?php foreach ($rows as $r): ?><tr><td><?= e(dt($r['created_at'], 'd.m.Y H:i:s')) ?></td><td><?= e($r['admin_login']) ?></td><td><?= e($r['section']) ?> / <strong><?= e($r['action']) ?></strong></td>
<td><?= $r['target_user_id'] ? '<a href="' . e(url('users', ['id' => $r['target_user_id']])) . '">user #' . (int)$r['target_user_id'] . '</a>' : '' ?><?= $r['target_ref'] ? ' <code>' . e($r['target_ref']) . '</code>' : '' ?></td>
<td><?php if ($r['old_value']): ?><pre class="json"><?= e($r['old_value']) ?></pre><?php endif; if ($r['new_value']): ?><pre class="json">→ <?= e($r['new_value']) ?></pre><?php endif; ?></td><td><?= e($r['reason']) ?></td><td><?= e($r['ip']) ?><br><span class="muted">#<?= (int)$r['session_id'] ?></span></td></tr>
<?php endforeach; if (!$rows) echo '<tr><td colspan="7" class="muted">Yozuv yo‘q</td></tr>'; ?></table></div><?= pager($tot, 40, $page, 'logs', array_filter($f)) ?>
