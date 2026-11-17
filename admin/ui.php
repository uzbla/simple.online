<?php
declare(strict_types=1);
function url(string $p, array $q = []): string { return 'index.php?' . http_build_query(['p' => $p] + $q); }
function redirect(string $to): never { header('Location: ' . $to); exit; }
function flash(string $type, string $msg): void { setcookie('iflash', rawurlencode(json_encode([$type, $msg], JSON_UNESCAPED_UNICODE)), ['path' => '/', 'httponly' => true, 'samesite' => 'Strict', 'secure' => Auth::https()]); }
function take_flash(): ?array {
    if (empty($_COOKIE['iflash'])) return null;
    setcookie('iflash', '', time() - 3600, '/'); $j = json_decode(rawurldecode($_COOKIE['iflash']), true);
    return is_array($j) && count($j) === 2 ? $j : null;
}
function csrf(): string { return '<input type="hidden" name="_csrf" value="' . e(Auth::session()['csrf'] ?? '') . '">'; }
function csrf_ok(): bool { $s = Auth::session(); return $s && isset($_POST['_csrf']) && is_string($_POST['_csrf']) && hash_equals($s['csrf'], $_POST['_csrf']); }
/** POST so‘rovlarni CSRF bilan himoyalaydi; muvaffaqiyatsiz bo‘lsa to‘xtaydi */
function post_guard(): bool {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') return false;
    if (!csrf_ok()) { http_response_code(419); exit('CSRF tekshiruvi muvaffaqiyatsiz. Sahifani yangilang.'); }
    return true;
}
function need(string $perm): void { if (!Auth::can($perm)) { http_response_code(403); throw new HttpDenied($perm); } }
final class HttpDenied extends RuntimeException {}
function post(string $k, string $d = ''): string { $v = $_POST[$k] ?? $d; return is_string($v) ? trim($v) : $d; }
function getp(string $k, string $d = ''): string { $v = $_GET[$k] ?? $d; return is_string($v) ? trim($v) : $d; }
function badge(string $s, ?string $label = null): string {
    $m = ['active'=>'ok','paid'=>'ok','done'=>'ok','sent'=>'ok','resolved'=>'ok','ok'=>'ok','pending'=>'warn','new'=>'warn','scheduled'=>'warn','sending'=>'warn','waiting'=>'warn','in_progress'=>'info',
          'blocked'=>'bad','failed'=>'bad','refunded'=>'bad','cancelled'=>'mute','expired'=>'mute','closed'=>'mute','disabled'=>'bad','deleted'=>'mute','error'=>'bad','warning'=>'warn'];
    return '<span class="badge ' . ($m[$s] ?? 'mute') . '">' . e($label ?? $s) . '</span>';
}
function pager(int $total, int $per, int $page, string $p, array $q): string {
    $pages = (int)ceil($total / $per); if ($pages <= 1) return '';
    $o = '<nav class="pager">';
    for ($i = max(1, $page - 3); $i <= min($pages, $page + 3); $i++) $o .= '<a class="' . ($i === $page ? 'cur' : '') . '" href="' . e(url($p, ['page' => $i] + $q)) . '">' . $i . '</a>';
    return $o . '<span class="muted">Jami: ' . n($total) . '</span></nav>';
}
function stat_card(string $label, $value, string $hint = ''): string {
    return '<div class="stat glass"><div class="stat-v">' . e($value) . '</div><div class="stat-l">' . e($label) . '</div>' . ($hint ? '<div class="stat-h">' . e($hint) . '</div>' : '') . '</div>';
}
/** Sana oralig‘i filtri: today|7|30|90|year|custom -> [fromUtc, toUtc, label, key] */
function date_range(): array {
    $k = getp('range', '30'); $tz = new DateTimeZone(Settings::get('timezone', 'Asia/Tashkent'));
    $today = new DateTime('today', $tz); $to = (clone $today)->modify('+1 day');
    switch ($k) {
        case 'today': $from = clone $today; break;
        case '7': $from = (clone $today)->modify('-6 days'); break;
        case '90': $from = (clone $today)->modify('-89 days'); break;
        case 'year': $from = (clone $today)->modify('-364 days'); break;
        case 'custom':
            try { $from = new DateTime(getp('from', 'today'), $tz); $to = (new DateTime(getp('to', 'today'), $tz))->modify('+1 day'); } catch (Throwable) { $from = (clone $today)->modify('-29 days'); }
            if ($to <= $from || $from->diff($to)->days > 800) { $from = (clone $today)->modify('-29 days'); $to = (clone $today)->modify('+1 day'); }
            break;
        default: $k = '30'; $from = (clone $today)->modify('-29 days');
    }
    $u = new DateTimeZone('UTC');
    return [(clone $from)->setTimezone($u)->format('Y-m-d H:i:s'), (clone $to)->setTimezone($u)->format('Y-m-d H:i:s'), $k, $from, $to];
}
function range_form(string $page): string {
    [, , $k, $f, $t] = date_range(); $o = '<form class="filters" method="get"><input type="hidden" name="p" value="' . e($page) . '"><select name="range" data-autosubmit>';
    foreach (['today' => 'Bugun', '7' => '7 kun', '30' => '30 kun', '90' => '90 kun', 'year' => 'Yil', 'custom' => 'Maxsus'] as $v => $l) $o .= '<option value="' . $v . '"' . ($k === (string)$v ? ' selected' : '') . '>' . $l . '</option>';
    $o .= '</select>';
    if ($k === 'custom') $o .= '<input type="date" name="from" value="' . e($f->format('Y-m-d')) . '"><input type="date" name="to" value="' . e((clone $t)->modify('-1 day')->format('Y-m-d')) . '"><button class="btn sm">OK</button>';
    return $o . '</form>';
}
/** Kunlar bo‘yicha qatorlarni to‘ldiradi: ['Y-m-d'=>value] */
function series(string $sql, array $params, DateTime $from, DateTime $to): array {
    $tz = Settings::get('timezone', 'Asia/Tashkent'); $off = (new DateTime('now', new DateTimeZone($tz)))->format('P');
    $sql = preg_replace('/\{DAY:(\w+)\}/', "DATE(CONVERT_TZ($1,'+00:00','$off'))", $sql);
    $raw = []; foreach (DB::all($sql, $params) as $r) $raw[$r['d']] = (float)$r['v'];
    $out = []; for ($d = (clone $from); $d < $to; $d->modify('+1 day')) { $k = $d->format('Y-m-d'); $out[$k] = $raw[$k] ?? 0; }
    return $out;
}
/** Inline SVG chart (CSP uchun style atributlarisiz) */
function chart(array $data, string $type = 'line', string $color = 'var(--ice)'): string {
    $w = 640; $h = 160; $pad = 22; $n = count($data); if (!$n) return '<p class="muted">Ma’lumot yo‘q</p>';
    $max = max(1, max($data)); $vals = array_values($data); $keys = array_keys($data);
    $svg = '<svg class="chart" viewBox="0 0 ' . $w . ' ' . ($h + $pad) . '" role="img" preserveAspectRatio="none">';
    for ($i = 0; $i <= 3; $i++) { $y = $h - $h * $i / 3 + 4; $svg .= '<line x1="0" x2="' . $w . '" y1="' . $y . '" y2="' . $y . '" class="grid"/>'; }
    $step = $w / max(1, $n);
    if ($type === 'bar') {
        foreach ($vals as $i => $v) { $bh = $v / $max * $h; $svg .= '<rect x="' . round($i * $step + 1, 1) . '" y="' . round($h - $bh + 4, 1) . '" width="' . round(max(1, $step - 2), 1) . '" height="' . round($bh, 1) . '" rx="2" fill="' . $color . '"><title>' . e($keys[$i]) . ': ' . e($v) . '</title></rect>'; }
    } else {
        $pts = []; foreach ($vals as $i => $v) $pts[] = round($i * $w / max(1, $n - 1), 1) . ',' . round($h - $v / $max * $h + 4, 1);
        $svg .= '<polygon points="0,' . ($h + 4) . ' ' . implode(' ', $pts) . ' ' . $w . ',' . ($h + 4) . '" fill="' . $color . '" opacity=".15"/><polyline points="' . implode(' ', $pts) . '" fill="none" stroke="' . $color . '" stroke-width="2.5" stroke-linejoin="round"/>';
    }
    $svg .= '<text x="2" y="' . ($h + 18) . '" class="axis">' . e($keys[0]) . '</text><text x="' . ($w - 2) . '" y="' . ($h + 18) . '" text-anchor="end" class="axis">' . e($keys[$n - 1]) . '</text>';
    return $svg . '<text x="' . ($w - 2) . '" y="14" text-anchor="end" class="axis">max ' . e(rtrim(rtrim(number_format($max, 2, '.', ''), '0'), '.')) . '</text></svg>';
}
function mask_tg($id): string { $s = (string)$id; return '••••' . substr($s, -3); }
function csv_out(string $name, array $header, array $rows): never {
    header('Content-Type: text/csv; charset=utf-8'); header('Content-Disposition: attachment; filename="' . preg_replace('/[^a-z0-9_.-]/i', '_', $name) . '"');
    echo "\xEF\xBB\xBF"; $f = fopen('php://output', 'w');   // BOM — Excel to‘g‘ri ochadi
    $safe = fn($v) => is_string($v) && preg_match('/^[=+\-@\t\r]/', $v) ? "'" . $v : $v;   // CSV/formula injection
    fputcsv($f, $header, ',', '"', ''); foreach ($rows as $r) fputcsv($f, array_map($safe, array_values($r)), ',', '"', ''); exit;
}
