<?php
$can = Auth::can('payments.manage');
if (post_guard() && $can && post('act') === 'cancel') {
    $id = (int)post('id');
    if (DB::exec('UPDATE payments SET status="cancelled" WHERE id=? AND status="pending"', [$id]) === 1) { Audit::log('payments', 'cancel_pending', ['ref' => $id, 'old' => ['status' => 'pending'], 'new' => ['status' => 'cancelled']]); flash('ok', 'Bekor qilindi.'); }
    else flash('err', 'Faqat kutilayotgan to‘lovni bekor qilish mumkin. To‘lov tasdig‘i faqat provayder webhooki orqali amalga oshadi.');
    redirect(url('payments'));
}
$st = getp('status'); $q = getp('q'); $page = max(1, (int)getp('page', '1'));
$w = ['1']; $p = [];
if (in_array($st, ['pending', 'paid', 'failed', 'refunded', 'cancelled'], true)) { $w[] = 'p.status=?'; $p[] = $st; }
if ($q !== '') { if (ctype_digit($q)) { $w[] = '(p.id=? OR p.user_id=?)'; array_push($p, $q, $q); } else { $w[] = 'p.provider_payment_id LIKE ?'; $p[] = '%' . addcslashes($q, '%_\\') . '%'; } }
$W = implode(' AND ', $w); $tot = (int)DB::val("SELECT COUNT(*) FROM payments p WHERE $W", $p);
$rows = DB::all("SELECT p.*, pl.name pn FROM payments p LEFT JOIN subscription_plans pl ON pl.id=p.plan_id WHERE $W ORDER BY p.id DESC LIMIT 30 OFFSET " . (($page - 1) * 30), $p);
$sum = DB::all('SELECT status, COUNT(*) c, COALESCE(SUM(amount),0) s FROM payments GROUP BY status'); $sm = []; foreach ($sum as $r) $sm[$r['status']] = $r;
?>
<h1>To‘lovlar</h1><p class="sub">To‘lov tasdig‘i provayderning imzolangan webhooki orqali amalga oshadi; admin qo‘lda “paid” qila olmaydi.</p>
<div class="grid g4"><?php foreach (['paid' => 'To‘langan', 'pending' => 'Kutilmoqda', 'failed' => 'Muvaffaqiyatsiz', 'refunded' => 'Qaytarilgan'] as $k => $l) echo stat_card($l, n($sm[$k]['c'] ?? 0), money($sm[$k]['s'] ?? 0, Settings::get('currency', 'UZS'))); ?></div><br>
<form class="filters" method="get"><input type="hidden" name="p" value="payments"><input name="q" value="<?= e($q) ?>" placeholder="To‘lov ID, user ID yoki provayder ID"><select name="status" data-autosubmit><option value="">Holat: hammasi</option>
<?php foreach (['pending', 'paid', 'failed', 'refunded', 'cancelled'] as $s) echo '<option' . ($st === $s ? ' selected' : '') . '>' . $s . '</option>'; ?></select><button>Qidirish</button></form>
<div class="card glass tw"><table><tr><th>ID</th><th>Foydalanuvchi</th><th>Tarif</th><th>Summa</th><th>Usul</th><th>Provayder ID</th><th>Sana</th><th>Holat</th><th></th></tr>
<?php foreach ($rows as $r): ?><tr><td>#<?= (int)$r['id'] ?></td><td><a href="<?= e(url('users', ['id' => $r['user_id']])) ?>">#<?= (int)$r['user_id'] ?></a></td><td><?= e($r['pn'] ?? '—') ?></td><td><?= e(money($r['amount'], $r['currency'])) ?></td><td><?= e($r['provider'] . ' ' . $r['method']) ?></td><td><code><?= e($r['provider_payment_id'] ?? '—') ?></code></td>
<td><?= e(dt($r['paid_at'] ?? $r['created_at'])) ?></td><td><?= badge($r['status']) ?><?= $r['fail_reason'] ? '<div class="muted">' . e($r['fail_reason']) . '</div>' : '' ?></td>
<td><?php if ($can && $r['status'] === 'pending'): ?><form method="post" class="inline"><?= csrf() ?><input type="hidden" name="act" value="cancel"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="btn ghost sm" data-confirm="Bekor qilinsinmi?">Bekor qilish</button></form><?php endif; ?></td></tr>
<?php endforeach; if (!$rows) echo '<tr><td colspan="9" class="muted">To‘lovlar yo‘q</td></tr>'; ?></table></div><?= pager($tot, 30, $page, 'payments', ['q' => $q, 'status' => $st]) ?>
