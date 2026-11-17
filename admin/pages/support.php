<?php
$ST = ['new', 'in_progress', 'waiting', 'resolved', 'closed']; $id = (int)getp('id'); $me = Auth::admin();
if (post_guard()) {
    $t = DB::one('SELECT * FROM support_tickets WHERE id=?', [(int)post('id')]); $act = post('act');
    if ($t) {
        if ($act === 'reply' && post('body') !== '' && mb_strlen(post('body')) <= 3500) {
            DB::tx(function () use ($t, $me) { DB::exec('INSERT INTO support_messages (ticket_id,sender,admin_id,body) VALUES (?,?,?,?)', [$t['id'], 'admin', $me['id'], post('body')]);
                DB::exec('UPDATE support_tickets SET status="waiting", assigned_admin_id=COALESCE(assigned_admin_id,?) WHERE id=? AND status IN ("new","in_progress")', [$me['id'], $t['id']]); });
            Queue::direct((int)$t['user_id'], "💬 <b>Support javobi</b> (#{$t['id']})\n" . e(post('body')), 'support'); Audit::log('support', 'reply', ['ref' => $t['id'], 'user' => $t['user_id']]); flash('ok', 'Javob yuborildi.');
        } elseif ($act === 'status' && in_array(post('status'), $ST, true)) {
            DB::exec('UPDATE support_tickets SET status=? WHERE id=?', [post('status'), $t['id']]); Audit::log('support', 'status', ['ref' => $t['id'], 'old' => ['status' => $t['status']], 'new' => ['status' => post('status')], 'user' => $t['user_id']]); flash('ok', 'Holat yangilandi.');
        } elseif ($act === 'assign') {
            $aid = (int)post('admin_id') ?: null;
            if ($aid && !DB::val('SELECT id FROM admins a WHERE a.id=? AND a.status="active"', [$aid])) flash('err', 'Admin topilmadi.');
            else { DB::exec('UPDATE support_tickets SET assigned_admin_id=? WHERE id=?', [$aid, $t['id']]); Audit::log('support', 'assign', ['ref' => $t['id'], 'old' => ['admin' => $t['assigned_admin_id']], 'new' => ['admin' => $aid]]); flash('ok', 'Biriktirildi.'); }
        }
    }
    redirect(url('support', ['id' => (int)post('id')]));
}
if ($id): $t = DB::one('SELECT t.*, u.first_name, a.login alog FROM support_tickets t JOIN users u ON u.id=t.user_id LEFT JOIN admins a ON a.id=t.assigned_admin_id WHERE t.id=?', [$id]);
 if (!$t) { echo '<div class="card glass">Topilmadi</div>'; return; } ?>
<p><a href="<?= e(url('support')) ?>">← Murojaatlar</a></p><h1>#<?= (int)$t['id'] ?> · <?= e($t['subject']) ?> <?= badge($t['status']) ?></h1>
<p class="sub"><a href="<?= e(url('users', ['id' => $t['user_id']])) ?>"><?= e($t['first_name']) ?> (#<?= (int)$t['user_id'] ?>)</a> · <?= e(dt($t['created_at'])) ?> · Mas’ul: <?= e($t['alog'] ?? '—') ?></p>
<div class="card glass"><?php foreach (DB::all('SELECT m.*, a.login FROM support_messages m LEFT JOIN admins a ON a.id=m.admin_id WHERE ticket_id=? ORDER BY m.id', [$id]) as $m): ?>
<p><strong><?= $m['sender'] === 'admin' ? e($m['login'] ?? 'Admin') : 'Foydalanuvchi' ?></strong> <span class="muted"><?= e(dt($m['created_at'])) ?></span><br><?= nl2br(e($m['body'])) ?></p><hr><?php endforeach; ?>
<form method="post"><?= csrf() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="act" value="reply"><label>Javob</label><textarea name="body" maxlength="3500" required></textarea><p><button>Yuborish (Telegram orqali)</button></p></form></div>
<div class="grid g2"><form class="card glass" method="post"><?= csrf() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="act" value="status"><label>Holat</label><select name="status"><?php foreach ($ST as $s) echo '<option' . ($t['status'] === $s ? ' selected' : '') . '>' . $s . '</option>'; ?></select><p><button class="btn ghost"><?= in_array($t['status'], ['resolved', 'closed'], true) ? 'Qayta ochish / o‘zgartirish' : 'Yangilash' ?></button></p></form>
<form class="card glass" method="post"><?= csrf() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="act" value="assign"><label>Biriktirish</label><select name="admin_id"><option value="">—</option><?php foreach (DB::all('SELECT id,login,name FROM admins WHERE status="active" ORDER BY name') as $a) echo '<option value="' . (int)$a['id'] . '"' . ($t['assigned_admin_id'] == $a['id'] ? ' selected' : '') . '>' . e($a['name'] . ' (' . $a['login'] . ')') . '</option>'; ?></select><p><button class="btn ghost">Saqlash</button></p></form></div>
<?php else: $st = getp('status'); $w = in_array($st, $ST, true) ? 'WHERE t.status=' . DB::pdo()->quote($st) : ''; ?>
<h1>Support</h1><div class="tabs"><a class="<?= $st === '' ? 'cur' : '' ?>" href="<?= e(url('support')) ?>">Hammasi</a><?php foreach ($ST as $s) echo '<a class="' . ($st === $s ? 'cur' : '') . '" href="' . e(url('support', ['status' => $s])) . '">' . e($s) . ' (' . (int)DB::val('SELECT COUNT(*) FROM support_tickets WHERE status=?', [$s]) . ')</a>'; ?></div>
<div class="card glass tw"><table><tr><th>#</th><th>Mavzu</th><th>Foydalanuvchi</th><th>Sana</th><th>Mas’ul</th><th>Holat</th></tr>
<?php foreach (DB::all("SELECT t.*, u.first_name, a.login alog FROM support_tickets t JOIN users u ON u.id=t.user_id LEFT JOIN admins a ON a.id=t.assigned_admin_id $w ORDER BY FIELD(t.status,'new','in_progress','waiting','resolved','closed'), t.updated_at DESC LIMIT 60") as $t): ?>
<tr><td><a href="<?= e(url('support', ['id' => $t['id']])) ?>">#<?= (int)$t['id'] ?></a></td><td><a href="<?= e(url('support', ['id' => $t['id']])) ?>"><?= e($t['subject']) ?></a></td><td><?= e($t['first_name']) ?></td><td><?= e(dt($t['created_at'])) ?></td><td><?= e($t['alog'] ?? '—') ?></td><td><?= badge($t['status']) ?></td></tr><?php endforeach; ?></table></div><?php endif; ?>
