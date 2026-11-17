<?php
$me = Auth::admin(); $super = (int)$me['role_id'] === 1 && Auth::can('backup.restore');
if (post_guard()) {
    $act = post('act');
    if ($act === 'create') { $r = Backup::create('manual', (int)$me['id']); Audit::log('backup', 'create', ['ref' => $r['id'], 'new' => ['ok' => $r['ok']]]); flash($r['ok'] ? 'ok' : 'err', $r['ok'] ? 'Backup yaratildi.' : 'Backup xatosi: ' . $r['error']); }
    elseif ($act === 'verify') { $row = DB::one('SELECT * FROM backup_logs WHERE id=?', [(int)post('id')]); flash($row && Backup::verify($row) ? 'ok' : 'err', $row && Backup::verify($row) ? 'Checksum mos: fayl butun.' : 'Fayl yo‘q yoki o‘zgargan!'); }
    elseif ($act === 'restore' && $super) {
        $row = DB::one('SELECT * FROM backup_logs WHERE id=? AND status<>"failed"', [(int)post('id')]);
        if (!$row) flash('err', 'Backup topilmadi.');
        elseif (!password_verify((string)($_POST['pw'] ?? ''), $me['password_hash'])) { Audit::log('backup', 'restore_denied', ['ref' => $row['id'], 'reason' => 'Parol noto‘g‘ri']); flash('err', 'Parol noto‘g‘ri.'); }
        elseif (post('confirm') !== 'TIKLASH ' . $row['id']) flash('err', 'Tasdiqlash matni noto‘g‘ri.');
        elseif (post('reason') === '') flash('err', 'Sabab majburiy.');
        else {
            $pre = Backup::create('pre_restore', (int)$me['id']);
            if (!$pre['ok']) flash('err', 'Tiklashdan oldingi zaxira yaratilmadi: ' . $pre['error']);
            else { $res = Backup::restore($row, DB::one('SELECT * FROM backup_logs WHERE id=?', [$pre['id']]));
                if ($res['ok']) DB::exec('UPDATE backup_logs SET status="restored" WHERE id=?', [$row['id']]);
                Audit::log('backup', $res['ok'] ? 'restore' : 'restore_failed', ['ref' => $row['id'], 'new' => ['file' => $row['filename'], 'pre_restore_backup' => $pre['id']], 'reason' => post('reason')]);
                flash($res['ok'] ? 'ok' : 'err', $res['ok'] ? 'Tiklandi. Eslatma: tiklangan bazadagi sessiyalar/loglar backup vaqtidagi holatda.' : 'Tiklashda xato: ' . $res['error']); }
        }
    }
    redirect(url('backup'));
}
if (getp('dl') !== '') { $row = DB::one('SELECT * FROM backup_logs WHERE id=? AND status<>"failed"', [(int)getp('dl')]);
    $path = $row ? Backup::dir() . '/' . basename($row['filename']) : '';
    if ($row && is_file($path) && Backup::verify($row)) { Audit::log('backup', 'download', ['ref' => $row['id']]); while (ob_get_level()) ob_end_clean();
        header('Content-Type: application/octet-stream'); header('Content-Length: ' . filesize($path)); header('Content-Disposition: attachment; filename="' . basename($row['filename']) . '"'); readfile($path); exit; }
    flash('err', 'Fayl topilmadi yoki yaroqsiz.'); redirect(url('backup')); }
?>
<h1>Backup & Restore</h1><form method="post" class="card glass"><?= csrf() ?><input type="hidden" name="act" value="create"><button>Hozir backup yaratish</button> <span class="muted">Avtomatik: har oy (cron). Fayllar web-root'dan tashqarida saqlanadi.</span></form>
<div class="card glass tw"><table><tr><th>#</th><th>Fayl</th><th>Hajm</th><th>Tur</th><th>Sana</th><th>Holat</th><th></th></tr>
<?php foreach (DB::all('SELECT * FROM backup_logs ORDER BY id DESC LIMIT 30') as $b): ?><tr><td><?= (int)$b['id'] ?></td><td><code><?= e($b['filename']) ?></code></td><td><?= e(round($b['size_bytes'] / 1024)) ?> KB</td><td><?= e($b['kind']) ?></td><td><?= e(dt($b['created_at'])) ?></td><td><?= badge($b['status'] === 'ok' ? 'ok' : ($b['status'] === 'failed' ? 'failed' : 'info'), $b['status']) ?><?= $b['note'] ? '<div class="muted">' . e($b['note']) . '</div>' : '' ?></td>
<td><?php if ($b['status'] !== 'failed'): ?><a class="btn ghost sm" href="<?= e(url('backup', ['dl' => $b['id']])) ?>">Yuklab olish</a>
<form method="post" class="inline"><?= csrf() ?><input type="hidden" name="act" value="verify"><input type="hidden" name="id" value="<?= (int)$b['id'] ?>"><button class="btn ghost sm">Tekshirish</button></form><?php endif; ?></td></tr><?php endforeach; ?></table></div>
<?php if ($super): ?><form method="post" class="card glass"><?= csrf() ?><input type="hidden" name="act" value="restore"><h2>Tiklash <span class="badge bad">Xavfli amal · faqat Super Admin</span></h2>
<p class="muted">Joriy baza tanlangan backup bilan almashtiriladi. Avval avtomatik “pre_restore” zaxira yaratiladi.</p><div class="row"><div><label>Backup ID</label><input type="number" name="id" required></div><div><label>Tasdiqlash: <code>TIKLASH &lt;ID&gt;</code> deb yozing</label><input name="confirm" required autocomplete="off"></div>
<div><label>Parolingiz</label><input type="password" name="pw" required autocomplete="current-password"></div><div><label>Sabab</label><input name="reason" required maxlength="255"></div></div><p><button class="btn danger" data-confirm="Baza tiklanadi. Davom etasizmi?">Bazani tiklash</button></p></form><?php endif; ?>
