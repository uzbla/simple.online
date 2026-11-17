<?php
need('content.manage');
$T = [  // tur => [jadval, sarlavha, maydonlar [nom => [yorliq, tur]], ro‘yxatda ko‘rsatiladigan]
 'quotes' => ['motivational_quotes', 'Motivatsiya & iqtiboslar', ['text' => ['Matn', 'textarea'], 'author' => ['Muallif', 'text'], 'kind' => ['Turi (motivation/quote)', 'text'], 'show_date' => ['Ko‘rsatish sanasi', 'date']], 'text'],
 'ann' => ['announcements', 'E’lonlar', ['title' => ['Sarlavha', 'text'], 'body' => ['Matn', 'textarea'], 'publish_from' => ['Boshlanishi', 'datetime-local'], 'publish_to' => ['Tugashi', 'datetime-local']], 'title'],
 'banners' => ['banners', 'Bannerlar', ['title' => ['Sarlavha', 'text'], 'image_url' => ['Rasm (https)', 'url'], 'link_url' => ['Havola (https)', 'url'], 'publish_from' => ['Boshlanishi', 'datetime-local'], 'publish_to' => ['Tugashi', 'datetime-local']], 'title'],
];
$tab = getp('tab', 'quotes'); $isPage = in_array($tab, ['help', 'terms', 'privacy'], true);
if (!$isPage && !isset($T[$tab])) $tab = 'quotes';
if (post_guard()) {
    if ($isPage) {
        $old = DB::one('SELECT title, LENGTH(body) len FROM content_pages WHERE slug=?', [$tab]);
        DB::exec('UPDATE content_pages SET title=?, body=? WHERE slug=?', [post('title'), mb_substr((string)($_POST['body'] ?? ''), 0, 60000), $tab]);
        Audit::log('content', 'page_update', ['ref' => $tab, 'old' => $old, 'new' => ['len' => strlen((string)($_POST['body'] ?? ''))]]); flash('ok', 'Saqlandi.');
    } else {
        [$tbl, , $fields] = $T[$tab]; $act = post('act'); $rid = (int)post('id');
        if ($act === 'delete') { DB::exec("DELETE FROM $tbl WHERE id=?", [$rid]); Audit::log('content', 'delete', ['ref' => "$tbl:$rid"]); flash('ok', 'O‘chirildi.'); }
        elseif ($act === 'toggle') { DB::exec("UPDATE $tbl SET is_active=1-is_active WHERE id=?", [$rid]); Audit::log('content', 'toggle', ['ref' => "$tbl:$rid"]); }
        elseif ($act === 'save') {
            $vals = []; $bad = false;
            foreach ($fields as $k => [$lab, $type]) { $v = post($k);
                if ($type === 'datetime-local') $v = $v === '' ? null : local_to_utc($v);
                elseif ($type === 'date') $v = preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) ? $v : null;
                elseif ($type === 'url') { $v = $v === '' ? null : safe_https_url($v); if ($v === null && post($k) !== '') $bad = true; }
                elseif ($k === 'kind') $v = in_array($v, ['motivation', 'quote'], true) ? $v : 'motivation';
                $vals[$k] = $v; }
            if ($bad || (($vals['text'] ?? $vals['title'] ?? 'x') === '') || ($tab === 'banners' && empty($vals['image_url']))) flash('err', 'Maydonlar noto‘g‘ri (havolalar https:// bo‘lishi kerak).');
            else {
                if ($rid) { DB::exec("UPDATE $tbl SET " . implode(',', array_map(fn($k) => "$k=?", array_keys($vals))) . ' WHERE id=?', array_merge(array_values($vals), [$rid])); }
                else { $rid = DB::insert("INSERT INTO $tbl (" . implode(',', array_keys($vals)) . ') VALUES (' . implode(',', array_fill(0, count($vals), '?')) . ')', array_values($vals)); }
                Audit::log('content', $rid ? 'save' : 'create', ['ref' => "$tbl:$rid", 'new' => $vals]); flash('ok', 'Saqlandi.');
            }
        }
    }
    redirect(url('content', ['tab' => $tab]));
}
$tabs = ['quotes' => 'Motivatsiya', 'ann' => 'E’lonlar', 'banners' => 'Bannerlar', 'help' => 'Yordam', 'terms' => 'Qoidalar', 'privacy' => 'Maxfiylik'];
?>
<h1>Kontent</h1><div class="tabs"><?php foreach ($tabs as $k => $l) echo '<a class="' . ($tab === $k ? 'cur' : '') . '" href="' . e(url('content', ['tab' => $k])) . '">' . e($l) . '</a>'; ?></div>
<?php if ($isPage): $pg = DB::one('SELECT * FROM content_pages WHERE slug=?', [$tab]); ?>
<div class="card glass"><form method="post"><?= csrf() ?><label>Sarlavha</label><input name="title" value="<?= e($pg['title']) ?>" maxlength="150" required><label>Matn (oddiy matn; ilovada xavfsiz ko‘rsatiladi)</label><textarea name="body" rows="14"><?= e($pg['body']) ?></textarea><p><button>Saqlash</button></p><p class="muted">Oxirgi yangilanish: <?= e(dt($pg['updated_at'])) ?></p></form></div>
<?php else: [$tbl, $label, $fields, $show] = $T[$tab]; $eid = (int)getp('edit'); $er = $eid ? DB::one("SELECT * FROM $tbl WHERE id=?", [$eid]) : null; ?>
<div class="grid g2"><div class="card glass tw"><h2><?= e($label) ?></h2><table><tr><th>Mazmun</th><th>Faol</th><th></th></tr>
<?php foreach (DB::all("SELECT * FROM $tbl ORDER BY id DESC LIMIT 60") as $r): ?><tr><td><?= e(mb_strimwidth((string)$r[$show], 0, 70, '…')) ?><?php if (isset($r['publish_from']) || isset($r['show_date'])): ?><div class="muted"><?= e(isset($r['show_date']) ? ($r['show_date'] ?: '') : dt($r['publish_from']) . ' → ' . dt($r['publish_to'])) ?></div><?php endif; ?></td>
<td><form method="post" class="inline"><?= csrf() ?><input type="hidden" name="act" value="toggle"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="btn ghost sm"><?= $r['is_active'] ? 'Ha' : 'Yo‘q' ?></button></form></td>
<td><a class="btn ghost sm" href="<?= e(url('content', ['tab' => $tab, 'edit' => $r['id']])) ?>">Tahrir</a> <form method="post" class="inline"><?= csrf() ?><input type="hidden" name="act" value="delete"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="btn danger sm" data-confirm="O‘chirilsinmi?">×</button></form></td></tr><?php endforeach; ?></table></div>
<div class="card glass"><h2><?= $er ? 'Tahrirlash' : 'Yangi qo‘shish' ?></h2><form method="post"><?= csrf() ?><input type="hidden" name="act" value="save"><input type="hidden" name="id" value="<?= (int)($er['id'] ?? 0) ?>">
<?php foreach ($fields as $k => [$lab, $type]): $val = $er[$k] ?? ''; if ($type === 'datetime-local') $val = utc_to_local_input($val ?: null); ?><label><?= e($lab) ?></label>
<?php if ($type === 'textarea'): ?><textarea name="<?= e($k) ?>" required><?= e($val) ?></textarea><?php else: ?><input type="<?= e($type) ?>" name="<?= e($k) ?>" value="<?= e($val) ?>"><?php endif; endforeach; ?><p><button>Saqlash</button></p></form></div></div>
<?php endif; ?>
