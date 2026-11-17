<?php
$can = Auth::can('premium.manage'); $tab = getp('tab', 'plans');
const LIMIT_KEYS = ['max_goals' => 'Maqsadlar soni (-1 = cheksiz)', 'max_habits' => 'Odatlar soni (-1 = cheksiz)', 'max_reminders' => 'Eslatmalar soni (-1 = cheksiz)',
    'full_stats' => 'To‘liq statistika (0/1)', 'finance_reports' => 'Moliyaviy hisobotlar (0/1)', 'export_data' => 'Ma’lumot eksporti (0/1)'];
if (post_guard() && $can) {
    $act = post('act'); $id = (int)post('id');
    if ($act === 'save') {
        $lim = []; foreach (LIMIT_KEYS as $k => $_) { $lim[$k] = max(-1, min(100000, (int)($_POST['lim'][$k] ?? 0))); }
        $code = strtolower(post('code')); $name = post('name'); $price = (float)post('price'); $dur = (int)post('duration_days'); $trial = (int)post('trial_days');
        $cur = strtoupper(post('currency', 'UZS'));
        if (!preg_match('/^[a-z0-9_]{2,32}$/', $code) || $name === '' || $price < 0 || $dur < 0 || $dur > 3650 || $trial < 0 || $trial > 365 || !preg_match('/^[A-Z]{3}$/', $cur)) flash('err', 'Maydonlar noto‘g‘ri.');
        else {
            $old = $id ? DB::one('SELECT * FROM subscription_plans WHERE id=?', [$id]) : null;
            try {
                if ($id) DB::exec('UPDATE subscription_plans SET name=?,price=?,currency=?,duration_days=?,trial_days=?,limits_json=?,is_active=? WHERE id=?', [$name, $price, $cur, $dur, $trial, json_encode($lim), isset($_POST['is_active']) ? 1 : 0, $id]);
                else $id = DB::insert('INSERT INTO subscription_plans (code,name,price,currency,duration_days,trial_days,limits_json,is_active,sort_order) VALUES (?,?,?,?,?,?,?,?,50)', [$code, $name, $price, $cur, $dur, $trial, json_encode($lim), isset($_POST['is_active']) ? 1 : 0]);
                Audit::log('premium', $old ? 'plan_update' : 'plan_create', ['ref' => $id, 'old' => $old, 'new' => compact('code', 'name', 'price', 'dur', 'trial', 'lim')]); flash('ok', 'Saqlandi.');
            } catch (PDOException $e) { flash('err', 'Bunday kod mavjud.'); }
        }
    } elseif ($act === 'delete') {
        $pl = DB::one('SELECT * FROM subscription_plans WHERE id=? AND deleted_at IS NULL', [$id]);
        if (!$pl || $pl['code'] === 'free') flash('err', 'Free tarifni o‘chirib bo‘lmaydi.');
        else { $used = (int)DB::val('SELECT COUNT(*) FROM subscriptions WHERE plan_id=?', [$id]);
            if ($used) DB::exec('UPDATE subscription_plans SET deleted_at=?, is_active=0 WHERE id=?', [now(), $id]); else DB::exec('DELETE FROM subscription_plans WHERE id=?', [$id]);
            Audit::log('premium', 'plan_delete', ['ref' => $id, 'old' => $pl, 'reason' => $used ? 'soft-delete (obunalar bor)' : 'hard-delete']); flash('ok', 'O‘chirildi.'); }
    }
    redirect(url('plans'));
}
$edit = (int)getp('edit'); $ep = $edit ? DB::one('SELECT * FROM subscription_plans WHERE id=?', [$edit]) : null; $new = getp('new') === '1';
$el = $ep ? (json_decode($ep['limits_json'], true) ?: []) : [];
?>
<h1>Obunalar</h1><div class="tabs"><a class="<?= $tab === 'plans' ? 'cur' : '' ?>" href="<?= e(url('plans')) ?>">Tariflar</a><a class="<?= $tab === 'subs' ? 'cur' : '' ?>" href="<?= e(url('plans', ['tab' => 'subs'])) ?>">Obuna tarixi</a><a class="<?= $tab === 'manual' ? 'cur' : '' ?>" href="<?= e(url('plans', ['tab' => 'manual'])) ?>">Qo‘lda berilganlar</a></div>
<?php if ($tab === 'plans'): ?>
<div class="card glass tw"><table><tr><th>Kod</th><th>Nomi</th><th>Narx</th><th>Muddat</th><th>Sinov</th><th>Limitlar</th><th>Holat</th><th></th></tr>
<?php foreach (DB::all('SELECT * FROM subscription_plans WHERE deleted_at IS NULL ORDER BY sort_order,id') as $pl): $l = json_decode($pl['limits_json'], true) ?: []; ?>
<tr><td><code><?= e($pl['code']) ?></code></td><td><?= e($pl['name']) ?></td><td><?= e(money($pl['price'], $pl['currency'])) ?></td><td><?= (int)$pl['duration_days'] ?> kun</td><td><?= (int)$pl['trial_days'] ?> kun</td>
<td class="muted"><?php foreach ($l as $k => $v) echo e($k) . '=' . e($v) . ' '; ?></td><td><?= badge($pl['is_active'] ? 'active' : 'disabled', $pl['is_active'] ? 'Faol' : 'Nofaol') ?></td>
<td><?php if ($can): ?><a class="btn sm ghost" href="<?= e(url('plans', ['edit' => $pl['id']])) ?>">Tahrirlash</a>
<?php if ($pl['code'] !== 'free'): ?><form method="post" class="inline"><?= csrf() ?><input type="hidden" name="act" value="delete"><input type="hidden" name="id" value="<?= (int)$pl['id'] ?>"><button class="btn danger sm" data-confirm="Tarif o‘chirilsinmi?">O‘chirish</button></form><?php endif; endif; ?></td></tr><?php endforeach; ?></table>
<?php if ($can): ?><p><a class="btn" href="<?= e(url('plans', ['new' => 1])) ?>">+ Yangi tarif</a></p><?php endif; ?></div>
<?php if ($can && ($ep || $new)): ?><div class="card glass"><h2><?= $ep ? 'Tarifni tahrirlash' : 'Yangi tarif' ?></h2><form method="post"><?= csrf() ?><input type="hidden" name="act" value="save"><input type="hidden" name="id" value="<?= (int)($ep['id'] ?? 0) ?>">
<div class="row"><div><label>Kod</label><input name="code" value="<?= e($ep['code'] ?? '') ?>" <?= $ep ? 'readonly' : '' ?> required pattern="[a-z0-9_]{2,32}"></div><div><label>Nomi</label><input name="name" value="<?= e($ep['name'] ?? '') ?>" required maxlength="100"></div>
<div><label>Narx</label><input type="number" step="0.01" min="0" name="price" value="<?= e($ep['price'] ?? 0) ?>"></div><div><label>Valyuta</label><input name="currency" value="<?= e($ep['currency'] ?? 'UZS') ?>" maxlength="3"></div>
<div><label>Muddat (kun)</label><input type="number" min="0" max="3650" name="duration_days" value="<?= e($ep['duration_days'] ?? 30) ?>"></div><div><label>Sinov (kun)</label><input type="number" min="0" max="365" name="trial_days" value="<?= e($ep['trial_days'] ?? 0) ?>"></div></div>
<div class="row"><?php foreach (LIMIT_KEYS as $k => $lab): ?><div><label><?= e($lab) ?></label><input type="number" min="-1" name="lim[<?= e($k) ?>]" value="<?= e($el[$k] ?? 0) ?>"></div><?php endforeach; ?></div>
<label><input type="checkbox" name="is_active" <?= !$ep || $ep['is_active'] ? 'checked' : '' ?>> Faol</label><p><button>Saqlash</button></p></form></div><?php endif; ?>
<?php else:
  $where = $tab === 'manual' ? 's.source="admin"' : '1'; $page = max(1, (int)getp('page', '1')); $tot = (int)DB::val("SELECT COUNT(*) FROM subscriptions s WHERE $where");
  $rows = DB::all("SELECT s.*, p.name pn, u.first_name, a.login alog FROM subscriptions s JOIN subscription_plans p ON p.id=s.plan_id JOIN users u ON u.id=s.user_id LEFT JOIN admins a ON a.id=s.granted_by WHERE $where ORDER BY s.id DESC LIMIT 30 OFFSET " . (($page - 1) * 30)); ?>
<div class="card glass tw"><table><tr><th>#</th><th>Foydalanuvchi</th><th>Tarif</th><th>Manba</th><th>Boshlanish</th><th>Tugash</th><th>Holat</th><?php if ($tab === 'manual'): ?><th>Kim berdi</th><th>Sabab</th><?php endif; ?></tr>
<?php foreach ($rows as $s): ?><tr><td><?= (int)$s['id'] ?></td><td><a href="<?= e(url('users', ['id' => $s['user_id']])) ?>">#<?= (int)$s['user_id'] ?> <?= e($s['first_name']) ?></a></td><td><?= e($s['pn']) ?><?= $s['is_trial'] ? ' (sinov)' : '' ?></td><td><?= e($s['source']) ?></td><td><?= e(dt($s['starts_at'])) ?></td><td><?= e(dt($s['ends_at'])) ?></td><td><?= badge($s['status']) ?></td>
<?php if ($tab === 'manual'): ?><td><?= e($s['alog']) ?></td><td><?= e($s['reason']) ?></td><?php endif; ?></tr><?php endforeach; ?></table></div><?= pager($tot, 30, $page, 'plans', ['tab' => $tab]) ?>
<?php endif; ?>
