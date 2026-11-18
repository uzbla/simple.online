<?php
// setWebhook: url=https://api.yourdomain.com/bot/webhook.php&secret_token=<INTIZOM_WEBHOOK_SECRET>
declare(strict_types=1);
require dirname(__DIR__) . '/src/bootstrap.php';
$secret = cfg('telegram.webhook_secret');
if (!$secret || !hash_equals($secret, $_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? '')) { http_response_code(403); exit; }
$u = json_decode(file_get_contents('php://input') ?: '', true);
http_response_code(200); header('Content-Type: application/json');
$m = $u['message'] ?? null; if (!$m || empty($m['from']['id']) || ($m['chat']['type'] ?? '') !== 'private') { echo '{}'; exit; }
$from = $m['from']; $chat = (int)$m['chat']['id']; $text = trim((string)($m['text'] ?? ''));
if (!preg_match('/^\/(\w+)(?:@\w+)?(?:\s+(.*))?$/su', $text, $c)) { echo '{}'; exit; }
$cmd = strtolower($c[1]); $arg = trim($c[2] ?? '');
$reply = fn(string $t, array $o = []) => Telegram::send($chat, $t, $o);
try {
    // /admin KOD — admin akkauntini parolni tiklash uchun bog‘lash
    if ($cmd === 'admin') {
        $a = preg_match('/^[A-F0-9]{10}$/', strtoupper($arg)) ? DB::one('SELECT id FROM admins WHERE link_code=? AND status="active"', [strtoupper($arg)]) : null;
        if ($a) { DB::exec('UPDATE admins SET telegram_chat_id=?, link_code=NULL WHERE id=?', [$chat, $a['id']]); $reply('✅ Admin akkaunti bog‘landi.'); } else $reply('Kod noto‘g‘ri.');
        echo '{}'; exit;
    }
    DB::exec('INSERT INTO users (telegram_id,first_name,last_name,username,language) VALUES (?,?,?,?,?)
              ON DUPLICATE KEY UPDATE first_name=VALUES(first_name), last_name=VALUES(last_name), username=VALUES(username), last_active_at=UTC_TIMESTAMP()',
        [$from['id'], mb_substr($from['first_name'] ?? '', 0, 100), mb_substr($from['last_name'] ?? '', 0, 100), $from['username'] ?? null, 'uz']);
    $u = DB::one('SELECT * FROM users WHERE telegram_id=?', [$from['id']]);
    if ($u['status'] === 'blocked') { $reply('⛔ Hisobingiz bloklangan. Support: ' . e(Settings::get('support_contact', ''))); echo '{}'; exit; }
    if ($u['status'] === 'deleted') { DB::exec('UPDATE users SET status="active" WHERE id=?', [$u['id']]); }
    DB::exec('INSERT IGNORE INTO user_settings (user_id) VALUES (?)', [$u['id']]);
    DB::exec('INSERT IGNORE INTO user_activity (user_id, day) VALUES (?, UTC_DATE())', [$u['id']]);
    $app = ['reply_markup' => ['inline_keyboard' => [[['text' => '🏔️ INTIZOM OS ni ochish', 'web_app' => ['url' => cfg('telegram.miniapp_url')]]]]]];
    $name = e($from['first_name'] ?? '');
    switch ($cmd) {
        case 'start':
            Subs::trialIfNew((int)$u['id']);
            $reply("Salom, <b>$name</b>! 👋\n<b>INTIZOM OS</b> — Reach Your Summit.\nBoshlash uchun ilovani oching.", $app); break;
        case 'app': $reply('Ilovani ochish:', $app); break;
        case 'today': case 'tasks':
            $t = DB::all('SELECT title, status FROM tasks WHERE user_id=? AND status<>"done" AND (due_at IS NULL OR DATE(due_at)<=UTC_DATE()) ORDER BY priority DESC, due_at LIMIT 15', [$u['id']]);
            $reply($t ? "📋 <b>" . ($cmd === 'today' ? 'Bugungi reja' : 'Vazifalar') . "</b>\n" . implode("\n", array_map(fn($x) => '• ' . e($x['title']) . ($x['status'] === 'overdue' ? ' ⚠️' : ''), $t)) : 'Vazifalar yo‘q. 🎉', $app); break;
        case 'goals':
            $g = DB::all('SELECT title, progress FROM goals WHERE user_id=? AND status="active" ORDER BY end_date LIMIT 10', [$u['id']]);
            $reply($g ? "🎯 <b>Maqsadlar</b>\n" . implode("\n", array_map(fn($x) => '• ' . e($x['title']) . ' — ' . (int)$x['progress'] . '%', $g)) : 'Hozircha maqsad yo‘q.', $app); break;
        case 'stats':
            $d = (int)DB::val('SELECT COUNT(*) FROM tasks WHERE user_id=? AND status="done"', [$u['id']]); $lv = DB::val('SELECT name FROM level_rules WHERE id=?', [$u['level_id']]) ?: 'Base Camp';
            $reply("📊 <b>Statistika</b>\nBajarilgan vazifalar: $d\nXP: " . (int)$u['xp_total'] . " · $lv\nStreak: " . (int)$u['streak'] . ' 🔥', $app); break;
        case 'premium':
            $pl = DB::all('SELECT name, price, currency, duration_days FROM subscription_plans WHERE is_active=1 AND deleted_at IS NULL AND price>0 ORDER BY sort_order');
            $reply(($u['premium_until'] && $u['premium_until'] > now() ? '💎 Premium: ' . dt($u['premium_until'], 'd.m.Y') . " gacha\n\n" : '') . implode("\n", array_map(fn($p) => '• <b>' . e($p['name']) . '</b> — ' . money($p['price'], $p['currency']) . " / {$p['duration_days']} kun", $pl)), $app); break;
        default: $reply("/start — ro‘yxatdan o‘tish\n/app — ilova\n/today — bugungi reja\n/tasks — vazifalar\n/goals — maqsadlar\n/stats — statistika\n/premium — obuna\n/help — yordam");
    }
} catch (Throwable $e) { slog('error', 'bot', $e->getMessage(), ['cmd' => $cmd]); }
echo '{}';
