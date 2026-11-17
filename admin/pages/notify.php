<?php
$id = (int)getp('id'); $prev = null; $f = ['title' => '', 'body' => '', 'audience' => 'all', 'ids' => '', 'btn_text' => '', 'btn_url' => '', 'img' => '', 'send_at' => ''];
if (post_guard()) {
    $act = post('act');
    if ($act === 'cancel') {
        if (DB::exec('UPDATE notifications SET status="cancelled" WHERE id=? AND status="scheduled"', [(int)post('nid')]) === 1) { Audit::log('notifications', 'cancel', ['ref' => post('nid')]); flash('ok', 'Bekor qilindi.'); } redirect(url('notify'));
    }
    if ($act === 'retry') {
        $n = DB::exec('UPDATE notification_queue SET status="pending", attempts=0, next_attempt_at=? WHERE notification_id=? AND status="failed"', [now(), (int)post('nid')]);
        DB::exec('UPDATE notifications SET status="sending" WHERE id=? AND status="done"', [(int)post('nid')]); Audit::log('notifications', 'retry_failed', ['ref' => post('nid'), 'new' => ['count' => $n]]); flash('ok', "$n ta xabar qayta navbatga qo‘yildi."); redirect(url('notify', ['id' => (int)post('nid')]));
    }
    $f = ['title' => post('title'), 'body' => Telegram::sanitize(post('body')), 'audience' => post('audience'), 'ids' => post('ids'), 'btn_text' => post('btn_text'), 'btn_url' => post('btn_url'), 'img' => post('img'), 'send_at' => post('send_at')];
    $errs = []; $ids = [];
    if ($f['title'] === '' || mb_strlen($f['title']) > 150) $errs[] = 'Sarlavha 1–150 belgi.';
    if ($f['body'] === '' || mb_strlen($f['body']) > 3500) $errs[] = 'Matn 1–3500 belgi.';
    if (!in_array($f['audience'], ['all', 'premium', 'free', 'selected'], true)) $errs[] = 'Auditoriya noto‘g‘ri.';
    if ($f['audience'] === 'selected') { $ids = array_slice(array_unique(array_filter(array_map('intval', preg_split('/[\s,;]+/', $f['ids'])))), 0, 5000); if (!$ids) $errs[] = 'Foydalanuvchi ID larini kiriting.'; }
    if ($f['btn_url'] !== '' && !safe_https_url($f['btn_url'])) $errs[] = 'Tugma havolasi https:// bo‘lishi kerak.';
    if ($f['img'] !== '' && !safe_https_url($f['img'])) $errs[] = 'Rasm havolasi https:// bo‘lishi kerak.';
    if ($f['btn_url'] !== '' && $f['btn_text'] === '') $errs[] = 'Tugma matnini kiriting.';
    $when = $f['send_at'] !== '' ? local_to_utc($f['send_at']) : now();
    if (!$when) $errs[] = 'Sana noto‘g‘ri.';
    if ($errs) flash('err', implode(' ', $errs));
    else {
        $cnt = (function () use ($f, $ids) {
            $w = 'status="active"'; $pp = [];
            if ($f['audience'] === 'premium') $w .= ' AND premium_until>UTC_TIMESTAMP()'; elseif ($f['audience'] === 'free') $w .= ' AND (premium_until IS NULL OR premium_until<=UTC_TIMESTAMP())';
            elseif ($f['audience'] === 'selected') { $w .= ' AND id IN (' . implode(',', array_map('intval', $ids)) . ')'; }
            return (int)DB::val("SELECT COUNT(*) FROM users WHERE $w", $pp); })();
        if ($act === 'preview') { $prev = ['cnt' => $cnt, 'when' => $when, 'ids' => $ids]; }
        elseif ($act === 'send') {
            $nid = DB::insert('INSERT INTO notifications (title,body,button_text,button_url,image_url,audience,audience_ids,send_at,created_by) VALUES (?,?,?,?,?,?,?,?,?)',
                [$f['title'], $f['body'], $f['btn_text'] ?: null, safe_https_url($f['btn_url']), safe_https_url($f['img']), $f['audience'], $ids ? json_encode($ids) : null, $when, Auth::admin()['id']]);
            Audit::log('notifications', 'create', ['ref' => $nid, 'new' => ['audience' => $f['audience'], 'recipients' => $cnt, 'send_at' => $when, 'title' => $f['title']]]);
            flash('ok', "Xabar rejalashtirildi (≈ $cnt ta qabul qiluvchi). Navbat Telegram limitlariga rioya qilib yuboradi."); redirect(url('notify', ['id' => $nid]));
        }
    }
}
?>
<h1>Bildirishnomalar</h1><p class="sub">Ommaviy xabarlar navbat orqali, Telegram limitlariga rioya qilib yuboriladi. Ruxsat etilgan teglar: &lt;b&gt; &lt;i&gt; &lt;u&gt; &lt;s&gt; &lt;code&gt; &lt;pre&gt; &lt;a href="https://…"&gt;</p>
<?php if ($id): $n = DB::one('SELECT * FROM notifications WHERE id=?', [$id]); if ($n):
  $c = []; foreach (DB::all('SELECT status, COUNT(*) c FROM notification_queue WHERE notification_id=? GROUP BY status', [$id]) as $r) $c[$r['status']] = (int)$r['c']; ?>
<p><a href="<?= e(url('notify')) ?>">← Ro‘yxat</a></p><div class="card glass"><h2><?= e($n['title']) ?> <?= badge($n['status']) ?></h2><p class="muted">Yuborish: <?= e(dt($n['send_at'])) ?> · Auditoriya: <?= e($n['audience']) ?> · Jami: <?= n($n['total']) ?></p>
<div class="grid g4"><?php foreach (['sent' => 'Yuborildi', 'pending' => 'Navbatda', 'failed' => 'Xato', 'cancelled' => 'Bekor'] as $k => $l) echo stat_card($l, n($c[$k] ?? 0)); ?></div>
<?php if (!empty($c['failed'])): ?><form method="post"><?= csrf() ?><input type="hidden" name="act" value="retry"><input type="hidden" name="nid" value="<?= $id ?>"><p><button class="btn ghost">Xatolarni qayta yuborish</button></p></form>
<div class="tw"><table><tr><th>User</th><th>Xato</th></tr><?php foreach (DB::all('SELECT user_id, error FROM notification_queue WHERE notification_id=? AND status="failed" LIMIT 50', [$id]) as $r) echo '<tr><td>#' . (int)$r['user_id'] . '</td><td>' . e($r['error']) . '</td></tr>'; ?></table></div><?php endif; ?></div>
<?php endif; else: ?>
<div class="grid g2"><div class="card glass"><h2>Yangi xabar</h2><form method="post"><?= csrf() ?>
<label>Sarlavha (ichki)</label><input name="title" value="<?= e($f['title']) ?>" maxlength="150" required><label>Xabar matni</label><textarea name="body" maxlength="3500" required><?= e($f['body']) ?></textarea>
<label>Auditoriya</label><select name="audience"><?php foreach (['all' => 'Barcha foydalanuvchilar', 'premium' => 'Faqat Premium', 'free' => 'Faqat Free', 'selected' => 'Tanlangan foydalanuvchilar'] as $k => $l) echo '<option value="' . $k . '"' . ($f['audience'] === $k ? ' selected' : '') . '>' . $l . '</option>'; ?></select>
<label>Tanlangan foydalanuvchi ID lari (vergul/probel bilan)</label><input name="ids" value="<?= e($f['ids']) ?>"><div class="row"><div><label>Tugma matni</label><input name="btn_text" value="<?= e($f['btn_text']) ?>" maxlength="64"></div><div><label>Tugma havolasi (https)</label><input name="btn_url" value="<?= e($f['btn_url']) ?>"></div></div>
<label>Rasm havolasi (https, ixtiyoriy)</label><input name="img" value="<?= e($f['img']) ?>"><label>Yuborish vaqti (bo‘sh = hozir)</label><input type="datetime-local" name="send_at" value="<?= e($f['send_at']) ?>">
<p><button name="act" value="preview">Ko‘rib chiqish</button></p>
<?php if ($prev): ?><div class="flash ok"><strong>Oldindan ko‘rish:</strong><br><?= nl2br(Telegram::sanitize($f['body'])) ?><br><br>Qabul qiluvchilar: <strong><?= n($prev['cnt']) ?></strong> · Yuborish: <?= e(dt($prev['when'])) ?></div>
<button name="act" value="send" class="btn gold" data-confirm="<?= n($prev['cnt']) ?> ta foydalanuvchiga yuborilsinmi?">Tasdiqlash va rejalashtirish</button><?php endif; ?></form></div>
<div class="card glass tw"><h2>Oxirgi xabarlar</h2><table><tr><th>Sarlavha</th><th>Vaqt</th><th>Holat</th><th></th></tr>
<?php foreach (DB::all('SELECT * FROM notifications ORDER BY id DESC LIMIT 15') as $r): ?><tr><td><a href="<?= e(url('notify', ['id' => $r['id']])) ?>"><?= e($r['title']) ?></a></td><td><?= e(dt($r['send_at'])) ?></td><td><?= badge($r['status']) ?></td>
<td><?php if ($r['status'] === 'scheduled'): ?><form method="post" class="inline"><?= csrf() ?><input type="hidden" name="act" value="cancel"><input type="hidden" name="nid" value="<?= (int)$r['id'] ?>"><button class="btn ghost sm" data-confirm="Bekor qilinsinmi?">Bekor</button></form><?php endif; ?></td></tr><?php endforeach; ?></table></div></div>
<?php endif; ?>
