<?php
declare(strict_types=1);
require dirname(__DIR__) . '/src/bootstrap.php';
require __DIR__ . '/ui.php';

if (cfg('admin.force_https') && !Auth::https()) { http_response_code(400); exit('HTTPS talab qilinadi.'); }
header('Content-Security-Policy: default-src \'self\'; img-src \'self\' data: https:; style-src \'self\'; script-src \'self\'; frame-ancestors \'none\'; form-action \'self\'; base-uri \'none\'');
header('X-Frame-Options: DENY'); header('X-Content-Type-Options: nosniff'); header('Referrer-Policy: same-origin');
header('Cache-Control: no-store'); header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
if (Auth::https()) header('Strict-Transport-Security: max-age=31536000');

const PAGES = [  // kalit => [permission, sarlavha, ikonka, guruh]
  'dashboard' => ['dashboard.view', 'Dashboard', '📊', 'Boshqaruv'],
  'analytics' => ['analytics.view', 'Analytics', '📈', 'Boshqaruv'],
  'users' => ['users.view', 'Foydalanuvchilar', '👥', 'Foydalanuvchilar'],
  'support' => ['support.manage', 'Support', '💬', 'Foydalanuvchilar'],
  'plans' => ['payments.view', 'Obunalar', '💎', 'Premium'],
  'payments' => ['payments.view', 'To‘lovlar', '💳', 'Premium'],
  'notify' => ['notify.send', 'Bildirishnomalar', '📣', 'Kommunikatsiya'],
  'content' => ['content.manage', 'Kontent', '🗂️', 'Kommunikatsiya'],
  'reminders' => ['reminders.view', 'Eslatmalar nazorati', '⏰', 'Platforma'],
  'xp' => ['xp.manage', 'XP & Everest', '🏔️', 'Platforma'],
  'admins' => ['admins.manage', 'Adminlar', '🛡️', 'Xavfsizlik'],
  'logs' => ['logs.view', 'Admin loglari', '📜', 'Xavfsizlik'],
  'security' => ['security.view', 'Security Center', '🔐', 'Xavfsizlik'],
  'settings' => ['settings.manage', 'Sozlamalar', '⚙️', 'Tizim'],
  'system' => ['system.view', 'Monitoring', '🖥️', 'Tizim'],
  'backup' => ['backup.manage', 'Backup', '💾', 'Tizim'],
];
$p = getp('p', 'dashboard');
try {
    // ---- ochiq sahifalar ----
    if (in_array($p, ['login', 'forgot', 'reset'], true)) { if (Auth::full() && $p === 'login') redirect(url('dashboard')); require __DIR__ . "/pages/$p.php"; exit; }
    if ($p === 'logout') { if (post_guard()) { $a = Auth::admin(); Auth::logout(); } redirect(url('login')); }

    $admin = Auth::admin();
    if (!$admin) redirect(url('login'));
    if (!Auth::full()) {                       // 2FA bosqichi
        if ($admin['totp_enabled']) { require __DIR__ . '/pages/twofa.php'; exit; }
        $p = 'profile'; $_GET['setup'] = '1';  // 2FA majburiy: avval sozlanadi
    } elseif ($admin['must_change_password']) { $p = 'profile'; }

    $isProfile = $p === 'profile';
    if (!$isProfile && !isset(PAGES[$p])) { http_response_code(404); $p = 'dashboard'; }
    if (!$isProfile) need(PAGES[$p][0]);
    $title = $isProfile ? 'Profil va xavfsizlik' : PAGES[$p][1];
    ob_start();
    require __DIR__ . "/pages/$p.php";
    $content = ob_get_clean();
} catch (HttpDenied $d) {
    $title = 'Ruxsat yo‘q'; $content = '<div class="card glass"><h1>403</h1><p>Bu bo‘limga kirish huquqingiz yo‘q.</p></div>';
} catch (Throwable $ex) {
    slog('error', 'admin', $ex->getMessage(), ['file' => basename($ex->getFile()), 'line' => $ex->getLine(), 'page' => $p]);
    http_response_code(500); $title = 'Xato'; $content = '<div class="card glass"><h1>500</h1><p>Ichki xato yuz berdi. U jurnalga yozildi.</p></div>';
    $admin ??= Auth::admin();
}
if (!isset($admin)) $admin = Auth::admin();
$full = Auth::full(); $fl = take_flash();
?><!doctype html>
<html lang="uz"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= e($title) ?> · <?= e(Settings::get('platform_name', 'INTIZOM OS')) ?> Admin</title>
<meta name="robots" content="noindex,nofollow"><link rel="stylesheet" href="assets/admin.css"></head>
<body><div class="shell">
<aside class="side glass"><div class="brand">INTIZOM OS<small>Reach Your Summit.</small></div>
<?php if ($full): $g = ''; foreach (PAGES as $k => [$perm, $label, $ico, $grp]) { if (!Auth::can($perm)) continue;
  if ($grp !== $g) { $g = $grp; echo '<div class="grp">' . e($grp) . '</div>'; }
  echo '<a class="nav' . ($p === $k ? ' cur' : '') . '" href="' . e(url($k)) . '"><span>' . $ico . '</span>' . e($label) . '</a>'; } endif; ?>
<div class="grp">Akkaunt</div><a class="nav<?= $p === 'profile' ? ' cur' : '' ?>" href="<?= e(url('profile')) ?>"><span>👤</span>Profil</a>
<form method="post" action="<?= e(url('logout')) ?>"><?= csrf() ?><button class="btn ghost sm" type="submit">Chiqish</button></form></aside>
<div class="main"><div class="top glass"><button class="menu-btn btn ghost sm" type="button">☰</button><strong><?= e($title) ?></strong>
<span class="who"><?= e($admin['name'] ?? '') ?> · <?= e($admin['role_name'] ?? '') ?></span></div>
<?php if ($fl): ?><div class="flash <?= $fl[0] === 'ok' ? 'ok' : 'err' ?>"><?= e($fl[1]) ?></div><?php endif; ?>
<?= $content ?></div></div><script src="assets/admin.js"></script></body></html>
