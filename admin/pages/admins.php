<?php
$me = Auth::admin(); $id = (int)getp('id'); $sections = [];
foreach (Perm::ALL as $k => [$sec, $desc, $deleg]) $sections[$sec][$k] = [$desc, $deleg];
if (post_guard()) {
    $act = post('act'); $tid = (int)post('id'); $t = $tid ? DB::one('SELECT * FROM admins WHERE id=?', [$tid]) : null;
    $superCount = fn() => (int)DB::val('SELECT COUNT(*) FROM admins WHERE role_id=1 AND status="active"');
    if ($act === 'create') {
        $login = post('login'); $role = (int)post('role'); $pw = bin2hex(random_bytes(3)) . 'Aa#' . random_int(100, 999) . bin2hex(random_bytes(3));
        if (!preg_match('/^[a-zA-Z0-9._-]{3,64}$/', $login) || post('name') === '' || !DB::val('SELECT id FROM admin_roles WHERE id=?', [$role])) flash('err', 'Login (3–64, harf/raqam/._-), ism va rol kerak.');
        elseif (DB::val('SELECT id FROM admins WHERE login=?', [$login])) flash('err', 'Bunday login mavjud.');
        else { $nid = DB::insert('INSERT INTO admins (login,name,password_hash,role_id,must_change_password) VALUES (?,?,?,?,1)', [$login, post('name'), password_hash($pw, PASSWORD_DEFAULT), $role]);
            Audit::log('admins', 'create', ['ref' => $nid, 'new' => ['login' => $login, 'role' => $role]]); flash('ok', "Admin yaratildi. Vaqtinchalik parol (bir marta ko‘rsatiladi): $pw"); }
        redirect(url('admins'));
    }
    if (!$t) redirect(url('admins'));
    if ($act === 'update') {
        $role = (int)post('role'); $status = post('status') === 'disabled' ? 'disabled' : 'active';
        if (!DB::val('SELECT id FROM admin_roles WHERE id=?', [$role])) flash('err', 'Rol noto‘g‘ri.');
        elseif ($t['role_id'] == 1 && ($role != 1 || $status !== 'active') && $superCount() <= 1) flash('err', 'Oxirgi Super Adminni o‘zgartirib bo‘lmaydi.');
        elseif ($t['id'] == $me['id'] && ($role != $me['role_id'] || $status !== 'active')) flash('err', 'O‘zingizning rolingiz/holatingizni o‘zgartira olmaysiz.');
        else {
            DB::tx(function () use ($t, $role, $status) { DB::exec('UPDATE admins SET role_id=?, status=?, name=? WHERE id=?', [$role, $status, post('name') ?: $t['name'], $t['id']]);
                DB::exec('DELETE FROM admin_permissions WHERE admin_id=?', [$t['id']]);
                foreach ((array)($_POST['ov'] ?? []) as $perm => $val) { if (!isset(Perm::ALL[$perm]) || !Perm::ALL[$perm][2] || !in_array($val, ['allow', 'deny'], true)) continue;
                    DB::exec('INSERT INTO admin_permissions (admin_id,permission,granted) VALUES (?,?,?)', [$t['id'], $perm, $val === 'allow' ? 1 : 0]); }
                if ($status !== 'active') Auth::revokeAllOf((int)$t['id']); });
            Audit::log('admins', 'update', ['ref' => $t['id'], 'old' => ['role' => $t['role_id'], 'status' => $t['status']], 'new' => ['role' => $role, 'status' => $status, 'overrides' => $_POST['ov'] ?? []]]); flash('ok', 'Saqlandi.');
        }
    } elseif ($act === 'reset_pw') {
        $pw = bin2hex(random_bytes(3)) . 'Aa#' . random_int(100, 999) . bin2hex(random_bytes(3));
        DB::exec('UPDATE admins SET password_hash=?, must_change_password=1 WHERE id=?', [password_hash($pw, PASSWORD_DEFAULT), $t['id']]); Auth::revokeAllOf((int)$t['id']);
        Audit::log('admins', 'reset_password', ['ref' => $t['id']]); flash('ok', "Yangi vaqtinchalik parol (bir marta): $pw");
    } elseif ($act === 'reset_2fa') {
        DB::exec('UPDATE admins SET totp_secret=NULL, totp_enabled=0, totp_last_step=NULL WHERE id=?', [$t['id']]); Auth::revokeAllOf((int)$t['id']);
        Audit::log('admins', 'reset_2fa', ['ref' => $t['id']]); flash('ok', '2FA tiklandi; keyingi kirishda qayta sozlanadi.');
    } elseif ($act === 'delete') {
        if ($t['id'] == $me['id']) flash('err', 'O‘zingizni o‘chira olmaysiz.');
        elseif ($t['role_id'] == 1 && $superCount() <= 1) flash('err', 'Oxirgi Super Adminni o‘chirib bo‘lmaydi.');
        else { DB::exec('DELETE FROM admins WHERE id=?', [$t['id']]); Audit::log('admins', 'delete', ['ref' => $t['id'], 'old' => ['login' => $t['login'], 'role' => $t['role_id']]]); flash('ok', 'O‘chirildi (loglar saqlanadi).'); }
    }
    redirect(url('admins'));
}
$roles = DB::all('SELECT * FROM admin_roles ORDER BY id');
if ($id): $t = DB::one('SELECT * FROM admins WHERE id=?', [$id]); if (!$t) { echo 'Topilmadi'; return; }
  $ov = []; foreach (DB::all('SELECT permission,granted FROM admin_permissions WHERE admin_id=?', [$id]) as $r) $ov[$r['permission']] = $r['granted'] ? 'allow' : 'deny'; ?>
<p><a href="<?= e(url('admins')) ?>">← Adminlar</a></p><h1><?= e($t['name']) ?> <span class="muted">(<?= e($t['login']) ?>)</span></h1>
<form class="card glass" method="post"><?= csrf() ?><input type="hidden" name="act" value="update"><input type="hidden" name="id" value="<?= $id ?>">
<div class="row"><div><label>Ism</label><input name="name" value="<?= e($t['name']) ?>" maxlength="100"></div><div><label>Rol</label><select name="role"><?php foreach ($roles as $r) echo '<option value="' . (int)$r['id'] . '"' . ($t['role_id'] == $r['id'] ? ' selected' : '') . '>' . e($r['name']) . '</option>'; ?></select></div>
<div><label>Holat</label><select name="status"><option value="active"<?= $t['status'] === 'active' ? ' selected' : '' ?>>Faol</option><option value="disabled"<?= $t['status'] === 'disabled' ? ' selected' : '' ?>>O‘chirilgan</option></select></div></div>
<h2>Individual huquqlar <span class="muted">(Super Admin uchun e’tiborga olinmaydi)</span></h2><div class="tw"><table><tr><th>Bo‘lim</th><th>Huquq</th><th>Rol bo‘yicha</th><th>Override</th></tr>
<?php foreach ($sections as $sec => $perms) foreach ($perms as $k => [$desc, $deleg]): if (!$deleg) continue; $base = DB::val('SELECT COUNT(*) FROM admin_permissions WHERE role_id=? AND permission=? AND granted=1', [$t['role_id'], $k]); ?>
<tr><td><?= e($sec) ?></td><td><?= e($desc) ?> <code><?= e($k) ?></code></td><td><?= $t['role_id'] == 1 || $base ? badge('ok', 'Ha') : badge('mute', 'Yo‘q') ?></td><td><select name="ov[<?= e($k) ?>]"><option value="">Rolga ergashadi</option><option value="allow"<?= ($ov[$k] ?? '') === 'allow' ? ' selected' : '' ?>>Ruxsat</option><option value="deny"<?= ($ov[$k] ?? '') === 'deny' ? ' selected' : '' ?>>Taqiq</option></select></td></tr>
<?php endforeach; ?></table></div><p class="muted">Faqat Super Admin: admins.manage, xp.adjust, backup.restore — delegatsiya qilinmaydi.</p><p><button>Saqlash</button></p></form>
<div class="row"><form method="post" class="card glass"><?= csrf() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="act" value="reset_pw"><button class="btn ghost" data-confirm="Parol tiklansinmi? Barcha sessiyalar yopiladi.">Parolni tiklash</button></form>
<form method="post" class="card glass"><?= csrf() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="act" value="reset_2fa"><button class="btn ghost" data-confirm="2FA tiklansinmi?">2FA ni tiklash</button></form>
<form method="post" class="card glass"><?= csrf() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="act" value="delete"><button class="btn danger" data-confirm="Admin o‘chirilsinmi?">Adminni o‘chirish</button></form></div>
<?php else: ?>
<h1>Adminlar</h1><div class="grid g2"><div class="card glass tw"><table><tr><th>Login</th><th>Ism</th><th>Rol</th><th>2FA</th><th>Oxirgi kirish</th><th>Holat</th></tr>
<?php foreach (DB::all('SELECT a.*, r.name rn FROM admins a JOIN admin_roles r ON r.id=a.role_id ORDER BY a.id') as $a): ?><tr><td><a href="<?= e(url('admins', ['id' => $a['id']])) ?>"><?= e($a['login']) ?></a></td><td><?= e($a['name']) ?></td><td><?= e($a['rn']) ?></td><td><?= $a['totp_enabled'] ? badge('ok', 'Yoqilgan') : badge('warn', 'Yo‘q') ?></td><td><?= e(dt($a['last_login_at'])) ?></td><td><?= badge($a['status']) ?></td></tr><?php endforeach; ?></table></div>
<form class="card glass" method="post"><?= csrf() ?><input type="hidden" name="act" value="create"><h2>Yangi admin</h2><label>Login</label><input name="login" required pattern="[a-zA-Z0-9._-]{3,64}"><label>Ism</label><input name="name" required maxlength="100"><label>Rol</label><select name="role"><?php foreach ($roles as $r) echo '<option value="' . (int)$r['id'] . '"' . ($r['id'] == 3 ? ' selected' : '') . '>' . e($r['name']) . '</option>'; ?></select><p><button>Yaratish</button></p><p class="muted">Vaqtinchalik parol bir marta ko‘rsatiladi; birinchi kirishda almashtirish va 2FA majburiy.</p></form></div><?php endif; ?>
