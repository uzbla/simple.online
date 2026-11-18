<?php
declare(strict_types=1);
final class ApiError extends RuntimeException { function __construct(string $m, public int $http = 400) { parent::__construct($m); } }

/** Foydalanuvchi REST API (Telegram Mini App). Barcha so‘rovlar user_id bo‘yicha ajratilgan. */
final class UserApi {
    private static array $u = [];     // joriy foydalanuvchi
    private static array $in = [];    // JSON body
    private static ?DateTimeZone $tz = null;

    // ---------- kirish nuqtasi ----------
    static function run(): void {
        header('Content-Type: application/json; charset=utf-8'); header('X-Content-Type-Options: nosniff'); header('Cache-Control: no-store');
        $origin = cfg('app.origin');
        if ($origin) { header('Access-Control-Allow-Origin: ' . $origin); header('Access-Control-Allow-Headers: Authorization, Content-Type'); header('Access-Control-Allow-Methods: GET,POST,PATCH,DELETE,OPTIONS'); header('Vary: Origin'); }
        $m = $_SERVER['REQUEST_METHOD'];
        if ($m === 'OPTIONS') { http_response_code(204); return; }
        try {
            $raw = file_get_contents('php://input') ?: '';
            if ($raw !== '') { if (strlen($raw) > 100000) throw new ApiError('So‘rov juda katta', 413); $j = json_decode($raw, true); if (!is_array($j)) throw new ApiError('JSON noto‘g‘ri'); self::$in = $j; }
            $r = $_GET['r'] ?? ''; if (!is_string($r) || !preg_match('#^[a-z_/]{1,40}$#', $r)) throw new ApiError('Not found', 404);
            if ($r === 'auth' && $m === 'POST') { self::rate('ip:' . client_ip(), 20); self::out(self::auth()); }
            self::authenticate();
            self::rate('u:' . self::$u['id'], 240);
            $fn = 'h_' . str_replace('/', '_', $r);
            if (!method_exists(self::class, $fn)) throw new ApiError('Not found', 404);
            self::out(self::$fn($m));
        } catch (ApiError $e) { http_response_code($e->http); echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE); }
        catch (Throwable $e) { slog('error', 'api', $e->getMessage(), ['line' => $e->getLine(), 'file' => basename($e->getFile())]); http_response_code(500); echo json_encode(['ok' => false, 'error' => 'Ichki xato']); }
    }
    private static function out($data): never { echo json_encode(['ok' => true, 'data' => $data], JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR); exit; }
    private static function rate(string $key, int $perMin): void {
        $w = intdiv(time(), 60);
        DB::exec('INSERT INTO api_rate (k,win,hits) VALUES (?,?,1) ON DUPLICATE KEY UPDATE hits=hits+1', [$key, $w]);
        if ((int)DB::val('SELECT hits FROM api_rate WHERE k=? AND win=?', [$key, $w]) > $perMin) throw new ApiError('Juda ko‘p so‘rov', 429);
        if (random_int(1, 200) === 1) DB::exec('DELETE FROM api_rate WHERE win<?', [$w - 5]);
    }

    // ---------- autentifikatsiya ----------
    /** Telegram WebApp initData imzosini tekshiradi */
    static function verifyInitData(string $initData, string $botToken, int $maxAge = 86400, ?int $now = null): ?array {
        parse_str($initData, $p);
        if (!isset($p['hash']) || !is_string($p['hash']) || $botToken === '') return null;
        $hash = $p['hash']; unset($p['hash']); ksort($p); $lines = [];
        foreach ($p as $k => $v) { if (!is_string($v)) return null; $lines[] = "$k=$v"; }
        $secret = hash_hmac('sha256', $botToken, 'WebAppData', true);
        if (!hash_equals(hash_hmac('sha256', implode("\n", $lines), $secret), strtolower($hash))) return null;
        if (($now ?? time()) - (int)($p['auth_date'] ?? 0) > $maxAge) return null;
        $u = json_decode((string)($p['user'] ?? ''), true);
        return is_array($u) && !empty($u['id']) ? $u : null;
    }
    private static function auth(): array {
        $tg = null;
        if (cfg('app.dev_login') && isset(self::$in['dev_telegram_id'])) $tg = ['id' => (int)self::$in['dev_telegram_id'], 'first_name' => 'Dev'];
        else $tg = self::verifyInitData((string)(self::$in['initData'] ?? ''), (string)cfg('telegram.bot_token'));
        if (!$tg || $tg['id'] < 1) throw new ApiError('Telegram tasdiqlashi muvaffaqiyatsiz', 401);
        DB::exec('INSERT INTO users (telegram_id,first_name,last_name,username,photo_url,language) VALUES (?,?,?,?,?,?)
                  ON DUPLICATE KEY UPDATE first_name=VALUES(first_name), last_name=VALUES(last_name), username=VALUES(username), photo_url=VALUES(photo_url), last_active_at=UTC_TIMESTAMP()',
            [$tg['id'], mb_substr((string)($tg['first_name'] ?? ''), 0, 100), mb_substr((string)($tg['last_name'] ?? ''), 0, 100), isset($tg['username']) ? mb_substr((string)$tg['username'], 0, 64) : null,
             isset($tg['photo_url']) && safe_https_url($tg['photo_url']) ? $tg['photo_url'] : null, 'uz']);
        $u = DB::one('SELECT * FROM users WHERE telegram_id=?', [$tg['id']]);
        if ($u['status'] === 'blocked') throw new ApiError('Hisobingiz bloklangan. Support bilan bog‘laning.', 403);
        if ($u['status'] === 'deleted') DB::exec('UPDATE users SET status="active" WHERE id=?', [$u['id']]);
        DB::exec('INSERT IGNORE INTO user_settings (user_id) VALUES (?)', [$u['id']]);
        Subs::trialIfNew((int)$u['id']);
        $tok = bin2hex(random_bytes(32));
        DB::exec('INSERT INTO user_sessions (user_id,token_hash,expires_at) VALUES (?,?,?)', [$u['id'], hash('sha256', $tok), gmdate('Y-m-d H:i:s', time() + 7 * 86400)]);
        DB::exec('DELETE FROM user_sessions WHERE expires_at<UTC_TIMESTAMP()');
        return ['token' => $tok];
    }
    private static function authenticate(): void {
        $h = $_SERVER['HTTP_AUTHORIZATION'] ?? ($_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
        if (!preg_match('/^Bearer ([a-f0-9]{64})$/', $h, $m)) throw new ApiError('Avtorizatsiya talab qilinadi', 401);
        $u = DB::one('SELECT u.* FROM user_sessions s JOIN users u ON u.id=s.user_id WHERE s.token_hash=? AND s.expires_at>UTC_TIMESTAMP()', [hash('sha256', $m[1])]);
        if (!$u || $u['status'] !== 'active') throw new ApiError('Sessiya yaroqsiz', 401);
        self::$u = $u;
        try { self::$tz = new DateTimeZone($u['timezone']); } catch (Throwable) { self::$tz = new DateTimeZone('Asia/Tashkent'); }
        DB::exec('INSERT IGNORE INTO user_activity (user_id,day) VALUES (?,UTC_DATE())', [$u['id']]);
        if (!$u['last_active_at'] || strtotime($u['last_active_at'] . ' UTC') < time() - 300) DB::exec('UPDATE users SET last_active_at=UTC_TIMESTAMP() WHERE id=?', [$u['id']]);
    }

    // ---------- yordamchilar ----------
    private static function uid(): int { return (int)self::$u['id']; }
    private static function today(): string { return (new DateTime('now', self::$tz))->format('Y-m-d'); }
    /** Mahalliy sana → [UTC boshi, UTC oxiri) */
    private static function dayRange(string $from, ?string $to = null): array {
        $a = new DateTime($from . ' 00:00:00', self::$tz); $b = new DateTime(($to ?? $from) . ' 00:00:00', self::$tz); $b->modify('+1 day');
        $u = new DateTimeZone('UTC'); return [$a->setTimezone($u)->format('Y-m-d H:i:s'), $b->setTimezone($u)->format('Y-m-d H:i:s')];
    }
    private static function s(string $k, int $max, bool $req = false, ?array $src = null): ?string {
        $v = ($src ?? self::$in)[$k] ?? null;
        if ($v === null || $v === '') { if ($req) throw new ApiError("«$k» majburiy"); return null; }
        if (!is_scalar($v)) throw new ApiError("«$k» noto‘g‘ri");
        $v = trim((string)$v); if (mb_strlen($v) > $max) throw new ApiError("«$k» juda uzun (maks. $max)");
        return $v;
    }
    private static function has(string $k): bool { return array_key_exists($k, self::$in); }
    private static function i(string $k, int $min, int $max, ?int $def = null): int {
        $v = self::$in[$k] ?? $def; if ($v === null || !is_numeric($v) || (int)$v != $v || $v < $min || $v > $max) throw new ApiError("«$k» $min–$max oralig‘ida bo‘lishi kerak"); return (int)$v;
    }
    private static function enum(string $k, array $vals, ?string $def = null): string {
        $v = self::$in[$k] ?? $def; if (!is_string($v) || !in_array($v, $vals, true)) throw new ApiError("«$k» noto‘g‘ri"); return $v;
    }
    private static function date(string $k, bool $req = false, ?array $src = null): ?string {
        $v = self::s($k, 10, $req, $src); if ($v === null) return null;
        $d = DateTime::createFromFormat('!Y-m-d', $v); if (!$d || $d->format('Y-m-d') !== $v) throw new ApiError("«$k» sana YYYY-MM-DD bo‘lishi kerak"); return $v;
    }
    private static function utc(string $k): ?string {
        $v = self::s($k, 40); if ($v === null) return null;
        try { return (new DateTime($v))->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'); } catch (Throwable) { throw new ApiError("«$k» vaqt noto‘g‘ri"); }
    }
    private static function id(): int { $i = (int)($_GET['id'] ?? self::$in['id'] ?? 0); if ($i < 1) throw new ApiError('id kerak'); return $i; }
    private static function own(string $table, int $id, string $col = 'user_id'): array {
        $r = DB::one("SELECT * FROM $table WHERE id=? AND $col=?", [$id, self::uid()]); if (!$r) throw new ApiError('Topilmadi', 404); return $r;
    }
    private static function xp(string $action, string $ref): ?array {
        $given = Xp::award(self::uid(), $action, $ref);
        return $given ? ['action' => $action, 'xp' => (int)DB::val('SELECT xp FROM xp_rules WHERE action_type=?', [$action])] : null;
    }
    private static function levelInfo(?array $u = null): array {
        $u ??= DB::one('SELECT * FROM users WHERE id=?', [self::uid()]);
        $levels = DB::all('SELECT id,name,min_xp,icon FROM level_rules ORDER BY min_xp'); $cur = $levels[0]; $next = null;
        foreach ($levels as $i => $l) if ($u['xp_total'] >= $l['min_xp']) { $cur = $l; $next = $levels[$i + 1] ?? null; }
        return ['name' => $cur['name'], 'icon' => $cur['icon'], 'index' => array_search($cur, $levels, true) + 1, 'total_levels' => count($levels), 'xp' => (int)$u['xp_total'],
                'next_name' => $next['name'] ?? null, 'next_xp' => $next ? (int)$next['min_xp'] : null, 'from_xp' => (int)$cur['min_xp']];
    }
    private static function premium(): bool { $p = DB::val('SELECT premium_until FROM users WHERE id=?', [self::uid()]); return $p && $p > now(); }

    // ---------- profil ----------
    private static function h_me(string $m): array {
        $uid = self::uid();
        if ($m === 'PATCH') {
            $set = []; $p = [];
            if (self::has('first_name')) { $set[] = 'first_name=?'; $p[] = self::s('first_name', 100, true); }
            if (self::has('last_name')) { $set[] = 'last_name=?'; $p[] = self::s('last_name', 100) ?? ''; }
            if (self::has('language')) { $set[] = 'language=?'; $p[] = self::enum('language', ['uz', 'ru', 'en']); }
            if (self::has('timezone')) { $tz = self::s('timezone', 64, true); if (!in_array($tz, DateTimeZone::listIdentifiers(), true)) throw new ApiError('Vaqt zonasi noto‘g‘ri'); $set[] = 'timezone=?'; $p[] = $tz; }
            if ($set) { $p[] = $uid; DB::exec('UPDATE users SET ' . implode(',', $set) . ' WHERE id=?', $p);
                if (self::has('timezone')) foreach (DB::all('SELECT * FROM reminders WHERE user_id=? AND enabled=1', [$uid]) as $r) DB::exec('UPDATE reminders SET next_run_at=? WHERE id=?', [Reminders::next($r, self::s('timezone', 64)), $r['id']]); }
            foreach (['notifications_enabled', 'daily_report', 'weekly_report'] as $f) if (self::has($f)) DB::exec("UPDATE user_settings SET $f=? WHERE user_id=?", [self::$in[$f] ? 1 : 0, $uid]);
        } elseif ($m === 'DELETE') {
            if (($_GET['confirm'] ?? self::$in['confirm'] ?? '') !== 'DELETE') throw new ApiError('Tasdiqlash kerak: confirm=DELETE');
            DB::tx(function () use ($uid) {
                foreach (['tasks', 'goals', 'habits', 'reminders', 'financial_records', 'budgets', 'savings_goals', 'daily_plans'] as $t) DB::exec("DELETE FROM $t WHERE user_id=?", [$uid]);
                DB::exec('DELETE FROM notification_queue WHERE user_id=? AND status="pending"', [$uid]); DB::exec('DELETE FROM user_sessions WHERE user_id=?', [$uid]);
                DB::exec('UPDATE users SET status="deleted", first_name="", last_name="", username=NULL, photo_url=NULL, xp_total=0, level_id=NULL, streak=0 WHERE id=?', [$uid]);
            });
            return ['deleted' => true];
        }
        $u = DB::one('SELECT * FROM users WHERE id=?', [$uid]); $st = DB::one('SELECT * FROM user_settings WHERE user_id=?', [$uid]) ?: [];
        return ['id' => $uid, 'telegram_id' => (int)$u['telegram_id'], 'first_name' => $u['first_name'], 'last_name' => $u['last_name'], 'username' => $u['username'], 'photo_url' => $u['photo_url'],
            'language' => $u['language'], 'timezone' => $u['timezone'], 'created_at' => $u['created_at'], 'premium_until' => $u['premium_until'] > now() ? $u['premium_until'] : null,
            'streak' => (int)$u['streak'], 'level' => self::levelInfo($u), 'limits' => Limits::of($uid), 'today' => self::today(),
            'settings' => ['notifications_enabled' => (bool)($st['notifications_enabled'] ?? 1), 'daily_report' => (bool)($st['daily_report'] ?? 1), 'weekly_report' => (bool)($st['weekly_report'] ?? 1)]];
    }

    // ---------- dashboard ----------
    private static function h_dashboard(string $m): array {
        $uid = self::uid(); $t = self::today(); [$a, $b] = self::dayRange($t);
        $tasks = DB::all('SELECT * FROM tasks WHERE user_id=? AND ((due_at>=? AND due_at<?) OR (status="overdue") OR (status="done" AND completed_at>=? AND completed_at<?)) ORDER BY status="done", due_at LIMIT 100', [$uid, $a, $b, $a, $b]);
        $done = count(array_filter($tasks, fn($x) => $x['status'] === 'done'));
        $rem = DB::all('SELECT id,type,message,next_run_at FROM reminders WHERE user_id=? AND enabled=1 AND next_run_at IS NOT NULL ORDER BY next_run_at LIMIT 4', [$uid]);
        $goals = DB::all('SELECT id,title,progress,end_date FROM goals WHERE user_id=? AND status="active" ORDER BY end_date IS NULL, end_date LIMIT 4', [$uid]);
        $bal = DB::all('SELECT currency, SUM(IF(type="income",amount,-amount)) b FROM financial_records WHERE user_id=? GROUP BY currency', [$uid]);
        return ['me' => self::h_me('GET'), 'tasks' => $tasks, 'done' => $done, 'total' => count($tasks), 'habits_today' => self::habitsToday(), 'reminders' => $rem, 'goals' => $goals, 'balance' => $bal, 'quote' => self::quote(),
            'announcements' => self::activeContent('announcements'), 'banners' => self::activeContent('banners')];
    }
    private static function habitsToday(): array {
        $t = self::today(); $dow = (int)(new DateTime($t))->format('N');
        return array_values(array_filter(array_map(fn($h) => ['id' => (int)$h['id'], 'title' => $h['title'], 'streak' => (int)$h['streak'], 'done' => (bool)$h['done']],
            DB::all('SELECT h.*, EXISTS(SELECT 1 FROM habit_logs l WHERE l.habit_id=h.id AND l.log_date=?) done FROM habits h WHERE h.user_id=? AND h.status="active" AND (h.days_mask & ?)>0', [$t, self::uid(), 1 << ($dow - 1)])), fn($x) => true));
    }

    // ---------- tasks ----------
    private const CATS = ['work', 'study', 'sport', 'personal', 'family', 'finance', 'other'];
    private static function h_tasks(string $m): array {
        $uid = self::uid();
        if ($m === 'GET') {
            $w = ['user_id=?']; $p = [$uid];
            if (!empty($_GET['from'])) { [$a, $b] = self::dayRange(self::date('from', true, $_GET), self::date('to', false, $_GET) ?? self::date('from', true, $_GET)); $w[] = '(due_at>=? AND due_at<?)'; array_push($p, $a, $b); }
            if (!empty($_GET['status']) && in_array($_GET['status'], ['todo', 'done', 'overdue'], true)) { $w[] = 'status=?'; $p[] = $_GET['status']; }
            if (!empty($_GET['category']) && in_array($_GET['category'], self::CATS, true)) { $w[] = 'category=?'; $p[] = $_GET['category']; }
            return DB::all('SELECT * FROM tasks WHERE ' . implode(' AND ', $w) . ' ORDER BY (due_at IS NULL), due_at, id DESC LIMIT 500', $p);
        }
        if ($m === 'POST') {
            if ((int)DB::val('SELECT COUNT(*) FROM tasks WHERE user_id=? AND status<>"done"', [$uid]) >= 2000) throw new ApiError('Faol vazifalar limiti (2000)');
            $id = DB::insert('INSERT INTO tasks (user_id,title,note,category,priority,due_at,repeat_rule) VALUES (?,?,?,?,?,?,?)', [$uid, self::s('title', 255, true), self::s('note', 2000), self::enum('category', self::CATS, 'other'),
                self::i('priority', 1, 3, 2), self::utc('due_at'), self::enum('repeat_rule', ['none', 'daily', 'weekly', 'monthly'], 'none') === 'none' ? null : self::$in['repeat_rule']]);
            return DB::one('SELECT * FROM tasks WHERE id=?', [$id]);
        }
        $id = self::id(); $t = self::own('tasks', $id);
        if ($m === 'DELETE') { DB::exec('DELETE FROM tasks WHERE id=?', [$id]); return ['deleted' => true]; }
        if ($m !== 'PATCH') throw new ApiError('Method not allowed', 405);
        $set = []; $p = []; $xp = []; $new = null;
        if (self::has('title')) { $set[] = 'title=?'; $p[] = self::s('title', 255, true); }
        if (self::has('note')) { $set[] = 'note=?'; $p[] = self::s('note', 2000); }
        if (self::has('category')) { $set[] = 'category=?'; $p[] = self::enum('category', self::CATS); }
        if (self::has('priority')) { $set[] = 'priority=?'; $p[] = self::i('priority', 1, 3); }
        if (self::has('due_at')) { $set[] = 'due_at=?'; $p[] = self::utc('due_at'); if ($t['status'] === 'overdue') { $set[] = 'status="todo"'; } }
        if (self::has('repeat_rule')) { $r = self::enum('repeat_rule', ['none', 'daily', 'weekly', 'monthly']); $set[] = 'repeat_rule=?'; $p[] = $r === 'none' ? null : $r; }
        if (self::has('status')) {
            $st = self::enum('status', ['todo', 'done']);
            if ($st === 'done' && $t['status'] !== 'done') { $set[] = 'status="done", completed_at=UTC_TIMESTAMP()'; DB::exec('INSERT INTO task_logs (task_id,action) VALUES (?,"done")', [$id]); $xp[] = self::xp('task', "task:$id");
                if ($t['repeat_rule'] && $t['due_at']) { $d = new DateTime($t['due_at'], new DateTimeZone('UTC')); $d->modify(['daily' => '+1 day', 'weekly' => '+1 week', 'monthly' => '+1 month'][$t['repeat_rule']]);
                    $new = DB::insert('INSERT INTO tasks (user_id,title,note,category,priority,due_at,repeat_rule) VALUES (?,?,?,?,?,?,?)', [$uid, $t['title'], $t['note'], $t['category'], $t['priority'], $d->format('Y-m-d H:i:s'), $t['repeat_rule']]); } }
            elseif ($st === 'todo' && $t['status'] === 'done') { $set[] = 'status="todo", completed_at=NULL'; DB::exec('INSERT INTO task_logs (task_id,action) VALUES (?,"reopen")', [$id]); }
        }
        if ($set) { $p[] = $id; DB::exec('UPDATE tasks SET ' . implode(',', $set) . ' WHERE id=?', $p); }
        if (self::has('status') && self::$in['status'] === 'done') {   // kunlik reja: bugungi barcha vazifalar bajarilsa
            [$a, $b] = self::dayRange(self::today());
            $left = (int)DB::val('SELECT COUNT(*) FROM tasks WHERE user_id=? AND status<>"done" AND due_at>=? AND due_at<?', [$uid, $a, $b]);
            if (!$left && (int)DB::val('SELECT COUNT(*) FROM tasks WHERE user_id=? AND status="done" AND due_at>=? AND due_at<?', [$uid, $a, $b])) $xp[] = self::xp('daily_plan', 'day:' . self::today());
        }
        return ['task' => DB::one('SELECT * FROM tasks WHERE id=?', [$id]), 'xp' => array_values(array_filter($xp)), 'next_id' => $new];
    }

    // ---------- goals ----------
    private static function goalProgress(int $gid): array {
        $g = DB::one('SELECT * FROM goals WHERE id=?', [$gid]); $tot = (int)DB::val('SELECT COUNT(*) FROM goal_milestones WHERE goal_id=?', [$gid]); $xp = null;
        if ($tot) { $done = (int)DB::val('SELECT COUNT(*) FROM goal_milestones WHERE goal_id=? AND done_at IS NOT NULL', [$gid]); $pr = (int)floor($done * 100 / $tot);
            DB::exec('UPDATE goals SET progress=? WHERE id=?', [$pr, $gid]); $g['progress'] = $pr; }
        if ($g['progress'] >= 100 && $g['status'] === 'active') { DB::exec('UPDATE goals SET status="done" WHERE id=?', [$gid]); $xp = self::xp('goal', "goal:$gid"); }
        return ['progress' => (int)$g['progress'], 'xp' => $xp];
    }
    private static function h_goals(string $m): array {
        $uid = self::uid(); $terms = ['daily', 'weekly', 'monthly', 'yearly', 'long'];
        if ($m === 'GET') { $gs = DB::all('SELECT * FROM goals WHERE user_id=? AND status<>"archived" ORDER BY status="done", end_date IS NULL, end_date, id DESC', [$uid]); $ms = [];
            if ($gs) foreach (DB::all('SELECT * FROM goal_milestones WHERE goal_id IN (' . implode(',', array_map(fn($g) => (int)$g['id'], $gs)) . ') ORDER BY id') as $x) $ms[$x['goal_id']][] = $x;
            foreach ($gs as &$g) $g['milestones'] = $ms[$g['id']] ?? []; return $gs; }
        $fields = function () use ($terms) { $s = self::date('start_date'); $e = self::date('end_date'); if ($s && $e && $e < $s) throw new ApiError('Tugash sanasi boshlanishidan oldin');
            return [self::s('title', 255, true), self::s('description', 2000), self::enum('term', $terms, 'monthly'), $s, $e, self::s('expected_result', 500)]; };
        if ($m === 'POST') {
            $lim = Limits::of($uid)['max_goals'];
            if (!Limits::allows($lim, (int)DB::val('SELECT COUNT(*) FROM goals WHERE user_id=? AND status="active"', [$uid]))) throw new ApiError("Free tarifda faol maqsadlar limiti: $lim. Premium oling.", 402);
            $id = DB::insert('INSERT INTO goals (user_id,title,description,term,start_date,end_date,expected_result) VALUES (?,?,?,?,?,?,?)', array_merge([$uid], $fields()));
            foreach ((array)(self::$in['milestones'] ?? []) as $t) if (is_string($t) && trim($t) !== '') DB::exec('INSERT INTO goal_milestones (goal_id,title) VALUES (?,?)', [$id, mb_substr(trim($t), 0, 255)]);
            return DB::one('SELECT * FROM goals WHERE id=?', [$id]);
        }
        $id = self::id(); self::own('goals', $id);
        if ($m === 'DELETE') { DB::exec('DELETE FROM goals WHERE id=?', [$id]); return ['deleted' => true]; }
        if ($m === 'PATCH') {
            if (self::has('title')) { $f = $fields(); DB::exec('UPDATE goals SET title=?,description=?,term=?,start_date=?,end_date=?,expected_result=? WHERE id=?', array_merge($f, [$id])); }
            if (self::has('progress') && !(int)DB::val('SELECT COUNT(*) FROM goal_milestones WHERE goal_id=?', [$id])) DB::exec('UPDATE goals SET progress=? WHERE id=?', [self::i('progress', 0, 100), $id]);
            if (self::has('status')) DB::exec('UPDATE goals SET status=? WHERE id=?', [self::enum('status', ['active', 'archived']), $id]);
            return array_merge(DB::one('SELECT * FROM goals WHERE id=?', [$id]), ['result' => self::goalProgress($id)]);
        }
        throw new ApiError('Method not allowed', 405);
    }
    private static function h_goals_milestone(string $m): array {
        if ($m === 'POST') { $gid = self::i('goal_id', 1, PHP_INT_MAX); self::own('goals', $gid);
            if ((int)DB::val('SELECT COUNT(*) FROM goal_milestones WHERE goal_id=?', [$gid]) >= 50) throw new ApiError('Bosqichlar limiti (50)');
            $id = DB::insert('INSERT INTO goal_milestones (goal_id,title) VALUES (?,?)', [$gid, self::s('title', 255, true)]); return ['id' => $id, 'result' => self::goalProgress($gid)]; }
        $id = self::id(); $ms = DB::one('SELECT m.*, g.user_id FROM goal_milestones m JOIN goals g ON g.id=m.goal_id WHERE m.id=?', [$id]);
        if (!$ms || (int)$ms['user_id'] !== self::uid()) throw new ApiError('Topilmadi', 404);
        if ($m === 'DELETE') { DB::exec('DELETE FROM goal_milestones WHERE id=?', [$id]); return ['result' => self::goalProgress((int)$ms['goal_id'])]; }
        if ($m === 'PATCH') { DB::exec('UPDATE goal_milestones SET done_at=' . (self::$in['done'] ?? false ? 'UTC_TIMESTAMP()' : 'NULL') . ' WHERE id=?', [$id]); return ['result' => self::goalProgress((int)$ms['goal_id'])]; }
        throw new ApiError('Method not allowed', 405);
    }

    // ---------- habits ----------
    private static function h_habits(string $m): array {
        $uid = self::uid();
        if ($m === 'GET') { $hs = DB::all('SELECT * FROM habits WHERE user_id=? AND status<>"archived" ORDER BY id', [$uid]); $logs = [];
            if ($hs) foreach (DB::all('SELECT habit_id, log_date FROM habit_logs WHERE habit_id IN (' . implode(',', array_map(fn($h) => (int)$h['id'], $hs)) . ') AND log_date>=DATE_SUB(CURDATE(), INTERVAL 90 DAY)') as $l) $logs[$l['habit_id']][] = $l['log_date'];
            foreach ($hs as &$h) { $h['logs'] = $logs[$h['id']] ?? []; $h['total'] = (int)DB::val('SELECT COUNT(*) FROM habit_logs WHERE habit_id=?', [$h['id']]); } return $hs; }
        if ($m === 'POST') {
            $lim = Limits::of($uid)['max_habits'];
            if (!Limits::allows($lim, (int)DB::val('SELECT COUNT(*) FROM habits WHERE user_id=? AND status<>"archived"', [$uid]))) throw new ApiError("Free tarifda odatlar limiti: $lim. Premium oling.", 402);
            $id = DB::insert('INSERT INTO habits (user_id,title,days_mask) VALUES (?,?,?)', [$uid, self::s('title', 255, true), self::i('days_mask', 1, 127, 127)]); return DB::one('SELECT * FROM habits WHERE id=?', [$id]);
        }
        $id = self::id(); self::own('habits', $id);
        if ($m === 'DELETE') { DB::exec('DELETE FROM habits WHERE id=?', [$id]); return ['deleted' => true]; }
        if ($m === 'PATCH') {
            if (self::has('title')) DB::exec('UPDATE habits SET title=? WHERE id=?', [self::s('title', 255, true), $id]);
            if (self::has('days_mask')) DB::exec('UPDATE habits SET days_mask=? WHERE id=?', [self::i('days_mask', 1, 127), $id]);
            if (self::has('status')) DB::exec('UPDATE habits SET status=? WHERE id=?', [self::enum('status', ['active', 'paused', 'archived']), $id]);
            return DB::one('SELECT * FROM habits WHERE id=?', [$id]);
        }
        throw new ApiError('Method not allowed', 405);
    }
    private static function h_habits_log(string $m): array {
        if ($m !== 'POST') throw new ApiError('Method not allowed', 405);
        $id = self::i('habit_id', 1, PHP_INT_MAX); $h = self::own('habits', $id); $d = self::date('date') ?? self::today();
        if ($d > self::today() || $d < (new DateTime(self::today()))->modify('-60 days')->format('Y-m-d')) throw new ApiError('Sana oralig‘i: oxirgi 60 kun va bugun');
        if ($h['status'] !== 'active') throw new ApiError('Odat faol emas');
        $xp = null;
        if (self::$in['done'] ?? true) { DB::exec('INSERT IGNORE INTO habit_logs (habit_id,log_date) VALUES (?,?)', [$id, $d]); $xp = self::xp('habit', "habit:$id:$d"); } else DB::exec('DELETE FROM habit_logs WHERE habit_id=? AND log_date=?', [$id, $d]);
        $st = Jobs::habitStreak($id, (int)$h['days_mask'], new DateTime(self::today(), self::$tz)); DB::exec('UPDATE habits SET streak=? WHERE id=?', [$st, $id]);
        return ['streak' => $st, 'xp' => $xp];
    }

    // ---------- reminders ----------
    private const RTYPES = ['morning_plan', 'task', 'sport', 'reading', 'water', 'wake', 'sleep', 'day_end', 'weekly_report', 'goal_deadline', 'custom'];
    private static function h_reminders(string $m): array {
        $uid = self::uid();
        if ($m === 'GET') return DB::all('SELECT id,type,message,time_of_day,days_mask,once_date,lead_minutes,enabled,next_run_at FROM reminders WHERE user_id=? ORDER BY time_of_day', [$uid]);
        $build = function (array $base) {
            $time = self::s('time_of_day', 5, !isset($base['time_of_day'])) ?? substr($base['time_of_day'], 0, 5);
            if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $time)) throw new ApiError('Vaqt HH:MM bo‘lishi kerak');
            $mask = self::has('days_mask') ? self::i('days_mask', 0, 127) : (int)($base['days_mask'] ?? 127);
            $once = self::has('once_date') ? self::date('once_date') : ($base['once_date'] ?? null);
            if ($mask === 0 && !$once) throw new ApiError('Bir martalik eslatma uchun sana kerak');
            $r = ['type' => self::has('type') ? self::enum('type', self::RTYPES) : ($base['type'] ?? 'custom'), 'message' => self::has('message') ? self::s('message', 500, true) : ($base['message'] ?? ''),
                'time_of_day' => $time . ':00', 'days_mask' => $mask, 'once_date' => $mask === 0 ? $once : null, 'lead_minutes' => self::has('lead_minutes') ? self::i('lead_minutes', 0, 1440) : (int)($base['lead_minutes'] ?? 0),
                'enabled' => self::has('enabled') ? (self::$in['enabled'] ? 1 : 0) : (int)($base['enabled'] ?? 1)];
            if ($r['message'] === '') throw new ApiError('Xabar matni majburiy');
            $r['next_run_at'] = $r['enabled'] ? Reminders::next($r, self::$u['timezone'], now()) : null; return $r; };
        if ($m === 'POST') {
            $lim = min(Limits::of($uid)['max_reminders'] < 0 ? PHP_INT_MAX : Limits::of($uid)['max_reminders'], (int)Settings::get('reminder_max_per_user', '20'));
            if ((int)DB::val('SELECT COUNT(*) FROM reminders WHERE user_id=?', [$uid]) >= $lim) throw new ApiError("Eslatmalar limiti: $lim. Premium bilan ko‘proq.", 402);
            $r = $build([]); $id = DB::insert('INSERT INTO reminders (user_id,type,message,time_of_day,days_mask,once_date,lead_minutes,enabled,next_run_at) VALUES (?,?,?,?,?,?,?,?,?)',
                [$uid, $r['type'], $r['message'], $r['time_of_day'], $r['days_mask'], $r['once_date'], $r['lead_minutes'], $r['enabled'], $r['next_run_at']]); return DB::one('SELECT * FROM reminders WHERE id=?', [$id]);
        }
        $id = self::id(); $cur = self::own('reminders', $id);
        if ($m === 'DELETE') { DB::exec('DELETE FROM reminders WHERE id=?', [$id]); return ['deleted' => true]; }
        if ($m === 'PATCH') { $r = $build($cur); DB::exec('UPDATE reminders SET type=?,message=?,time_of_day=?,days_mask=?,once_date=?,lead_minutes=?,enabled=?,next_run_at=? WHERE id=?',
            [$r['type'], $r['message'], $r['time_of_day'], $r['days_mask'], $r['once_date'], $r['lead_minutes'], $r['enabled'], $r['next_run_at'], $id]); return DB::one('SELECT * FROM reminders WHERE id=?', [$id]); }
        throw new ApiError('Method not allowed', 405);
    }
    private static function h_reminders_history(string $m): array {
        return DB::all('SELECT l.scheduled_for, l.status, r.message, r.type FROM reminder_logs l JOIN reminders r ON r.id=l.reminder_id WHERE r.user_id=? ORDER BY l.id DESC LIMIT 50', [self::uid()]);
    }

    // ---------- finance ----------
    private static function h_finance(string $m): array {
        $uid = self::uid();
        if ($m === 'GET') {
            $mon = preg_match('/^\d{4}-\d{2}$/', $_GET['month'] ?? '') ? $_GET['month'] : substr(self::today(), 0, 7); $from = "$mon-01"; $to = (new DateTime($from))->modify('first day of next month')->format('Y-m-d');
            $recs = DB::all('SELECT * FROM financial_records WHERE user_id=? AND record_date>=? AND record_date<? ORDER BY record_date DESC, id DESC LIMIT 500', [$uid, $from, $to]);
            $sum = DB::all('SELECT currency, SUM(IF(type="income",amount,0)) income, SUM(IF(type="expense",amount,0)) expense FROM financial_records WHERE user_id=? AND record_date>=? AND record_date<? GROUP BY currency', [$uid, $from, $to]);
            $bal = DB::all('SELECT currency, SUM(IF(type="income",amount,-amount)) balance FROM financial_records WHERE user_id=? GROUP BY currency', [$uid]);   // Balans = jami daromad − jami xarajat
            $out = ['month' => $mon, 'records' => $recs, 'month_summary' => $sum, 'balance' => $bal, 'budgets' => DB::all('SELECT b.id,b.category,b.amount, COALESCE((SELECT SUM(amount) FROM financial_records r WHERE r.user_id=b.user_id AND r.type="expense" AND r.category=b.category AND r.record_date>=? AND r.record_date<?),0) spent FROM budgets b WHERE b.user_id=? AND b.month=?', [$from, $to, $uid, $mon]),
                'savings' => DB::all('SELECT * FROM savings_goals WHERE user_id=? ORDER BY id', [$uid])];
            if (Limits::of($uid)['finance_reports']) $out['by_category'] = DB::all('SELECT category, type, currency, SUM(amount) total FROM financial_records WHERE user_id=? AND record_date>=? AND record_date<? GROUP BY category,type,currency ORDER BY total DESC', [$uid, $from, $to]);
            return $out;
        }
        if ($m === 'POST') {
            $amt = self::$in['amount'] ?? null; if (!is_numeric($amt) || $amt <= 0 || $amt > 1e12) throw new ApiError('Summa musbat son bo‘lishi kerak');
            $cur = strtoupper(self::s('currency', 3) ?? 'UZS'); if (!preg_match('/^[A-Z]{3}$/', $cur)) throw new ApiError('Valyuta ISO kodi');
            $id = DB::insert('INSERT INTO financial_records (user_id,type,amount,currency,category,note,record_date) VALUES (?,?,?,?,?,?,?)', [$uid, self::enum('type', ['income', 'expense']), round((float)$amt, 2), $cur, self::s('category', 64) ?? '', self::s('note', 255), self::date('record_date') ?? self::today()]);
            return DB::one('SELECT * FROM financial_records WHERE id=?', [$id]);
        }
        if ($m === 'DELETE') { $id = self::id(); self::own('financial_records', $id); DB::exec('DELETE FROM financial_records WHERE id=?', [$id]); return ['deleted' => true]; }
        throw new ApiError('Method not allowed', 405);
    }
    private static function h_finance_budget(string $m): array {
        if ($m === 'DELETE') { $id = self::id(); self::own('budgets', $id); DB::exec('DELETE FROM budgets WHERE id=?', [$id]); return ['deleted' => true]; }
        if ($m !== 'POST') throw new ApiError('Method not allowed', 405);
        $mon = self::s('month', 7) ?? substr(self::today(), 0, 7); if (!preg_match('/^\d{4}-\d{2}$/', $mon)) throw new ApiError('Oy YYYY-MM');
        $amt = self::$in['amount'] ?? null; if (!is_numeric($amt) || $amt <= 0) throw new ApiError('Summa noto‘g‘ri');
        DB::exec('INSERT INTO budgets (user_id,category,month,amount) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE amount=VALUES(amount)', [self::uid(), self::s('category', 64, true), $mon, round((float)$amt, 2)]); return ['ok' => true];
    }
    private static function h_finance_savings(string $m): array {
        $uid = self::uid();
        if ($m === 'POST') { $t = self::$in['target'] ?? null; if (!is_numeric($t) || $t <= 0) throw new ApiError('Maqsad summasi noto‘g‘ri'); return ['id' => DB::insert('INSERT INTO savings_goals (user_id,title,target) VALUES (?,?,?)', [$uid, self::s('title', 255, true), round((float)$t, 2)])]; }
        $id = self::id(); self::own('savings_goals', $id);
        if ($m === 'DELETE') { DB::exec('DELETE FROM savings_goals WHERE id=?', [$id]); return ['deleted' => true]; }
        if ($m === 'PATCH') { $a = self::$in['add'] ?? null; if (!is_numeric($a) || abs((float)$a) > 1e12) throw new ApiError('Summa noto‘g‘ri'); DB::exec('UPDATE savings_goals SET saved=GREATEST(0,saved+?) WHERE id=?', [round((float)$a, 2), $id]); return DB::one('SELECT * FROM savings_goals WHERE id=?', [$id]); }
        throw new ApiError('Method not allowed', 405);
    }

    // ---------- statistics ----------
    private static function h_stats(string $m): array {
        $uid = self::uid(); $per = $_GET['period'] ?? 'week'; if (!in_array($per, ['day', 'week', 'month', 'year'], true)) $per = 'week';
        $lim = Limits::of($uid);
        if (in_array($per, ['month', 'year'], true) && !$lim['full_stats']) throw new ApiError('Oylik va yillik statistika Premium uchun', 402);
        $days = ['day' => 1, 'week' => 7, 'month' => 30, 'year' => 365][$per];
        $to = new DateTime(self::today(), self::$tz); $from = (clone $to)->modify('-' . ($days - 1) . ' days'); [$a] = self::dayRange($from->format('Y-m-d')); [, $b] = self::dayRange($to->format('Y-m-d'));
        $off = (new DateTime('now', self::$tz))->format('P'); $fill = function (array $rows) use ($from, $days) { $m = array_column($rows, 'v', 'd'); $o = []; $d = clone $from; for ($i = 0; $i < $days; $i++, $d->modify('+1 day')) $o[$d->format('Y-m-d')] = (int)($m[$d->format('Y-m-d')] ?? 0); return $o; };
        $tz = fn(string $c) => "DATE(CONVERT_TZ($c,'+00:00','$off'))";
        $out = ['period' => $per, 'from' => $from->format('Y-m-d'), 'to' => $to->format('Y-m-d'),
            'tasks_done' => $fill(DB::all('SELECT ' . $tz('completed_at') . ' d, COUNT(*) v FROM tasks WHERE user_id=? AND status="done" AND completed_at>=? AND completed_at<? GROUP BY d', [$uid, $a, $b])),
            'tasks_created' => (int)DB::val('SELECT COUNT(*) FROM tasks WHERE user_id=? AND created_at>=? AND created_at<?', [$uid, $a, $b]),
            'habit_logs' => $fill(DB::all('SELECT l.log_date d, COUNT(*) v FROM habit_logs l JOIN habits h ON h.id=l.habit_id WHERE h.user_id=? AND l.log_date>=? AND l.log_date<=? GROUP BY d', [$uid, $from->format('Y-m-d'), $to->format('Y-m-d')])),
            'xp' => $fill(DB::all('SELECT ' . $tz('created_at') . ' d, SUM(amount) v FROM xp_transactions WHERE user_id=? AND created_at>=? AND created_at<? GROUP BY d', [$uid, $a, $b])),
            'goals' => ['active' => (int)DB::val('SELECT COUNT(*) FROM goals WHERE user_id=? AND status="active"', [$uid]), 'done' => (int)DB::val('SELECT COUNT(*) FROM goals WHERE user_id=? AND status="done"', [$uid]), 'avg_progress' => (int)DB::val('SELECT COALESCE(AVG(progress),0) FROM goals WHERE user_id=? AND status="active"', [$uid])],
            'streak' => (int)self::$u['streak'], 'xp_total' => (int)DB::val('SELECT xp_total FROM users WHERE id=?', [$uid])];
        if ($lim['finance_reports']) $out['finance'] = DB::all('SELECT currency, SUM(IF(type="income",amount,0)) income, SUM(IF(type="expense",amount,0)) expense FROM financial_records WHERE user_id=? AND record_date>=? AND record_date<=? GROUP BY currency', [$uid, $from->format('Y-m-d'), $to->format('Y-m-d')]);
        return $out;
    }
    private static function h_xp_history(string $m): array { return DB::all('SELECT action_type, amount, reason, created_at FROM xp_transactions WHERE user_id=? ORDER BY id DESC LIMIT 50', [self::uid()]); }

    // ---------- content, plans, support, export ----------
    private static function activeContent(string $tbl): array {
        return DB::all("SELECT * FROM $tbl WHERE is_active=1 AND (publish_from IS NULL OR publish_from<=UTC_TIMESTAMP()) AND (publish_to IS NULL OR publish_to>UTC_TIMESTAMP()) ORDER BY id DESC LIMIT 10");
    }
    private static function quote(): ?array {
        $q = DB::one('SELECT text, author FROM motivational_quotes WHERE is_active=1 AND show_date=? LIMIT 1', [self::today()]);
        if ($q) return $q; $n = (int)DB::val('SELECT COUNT(*) FROM motivational_quotes WHERE is_active=1 AND show_date IS NULL'); if (!$n) return null;
        return DB::one('SELECT text, author FROM motivational_quotes WHERE is_active=1 AND show_date IS NULL ORDER BY id LIMIT 1 OFFSET ' . (crc32(self::today()) % $n));
    }
    private static function h_content(string $m): array {
        if (!empty($_GET['page'])) { $p = DB::one('SELECT slug,title,body FROM content_pages WHERE slug=?', [$_GET['page']]); if (!$p) throw new ApiError('Topilmadi', 404); return $p; }
        return ['quote' => self::quote(), 'announcements' => self::activeContent('announcements'), 'banners' => self::activeContent('banners')];
    }
    private static function h_plans(string $m): array {
        return DB::all('SELECT code,name,price,currency,duration_days,trial_days,limits_json FROM subscription_plans WHERE is_active=1 AND deleted_at IS NULL ORDER BY sort_order');
    }
    private static function h_support(string $m): array {
        $uid = self::uid();
        if ($m === 'GET') {
            if (!empty($_GET['id'])) { $t = self::own('support_tickets', (int)$_GET['id']); $t['messages'] = DB::all('SELECT sender, body, created_at FROM support_messages WHERE ticket_id=? ORDER BY id', [$t['id']]); return $t; }
            return DB::all('SELECT id,subject,status,created_at,updated_at FROM support_tickets WHERE user_id=? ORDER BY id DESC LIMIT 50', [$uid]);
        }
        if ($m !== 'POST') throw new ApiError('Method not allowed', 405);
        if (self::has('ticket_id')) {   // mavjud murojaatga xabar
            $t = self::own('support_tickets', self::i('ticket_id', 1, PHP_INT_MAX));
            if ((int)DB::val('SELECT COUNT(*) FROM support_messages WHERE ticket_id=? AND sender="user" AND created_at>?', [$t['id'], gmdate('Y-m-d H:i:s', time() - 3600)]) >= 10) throw new ApiError('Juda ko‘p xabar', 429);
            DB::exec('INSERT INTO support_messages (ticket_id,sender,body) VALUES (?,?,?)', [$t['id'], 'user', self::s('body', 3000, true)]);
            DB::exec('UPDATE support_tickets SET status=IF(status IN ("resolved","closed","waiting"),"new",status) WHERE id=?', [$t['id']]); return ['ticket_id' => (int)$t['id']];
        }
        if ((int)DB::val('SELECT COUNT(*) FROM support_tickets WHERE user_id=? AND status NOT IN ("resolved","closed")', [$uid]) >= 5) throw new ApiError('Ochiq murojaatlar limiti (5)');
        $id = DB::tx(function () use ($uid) { $id = DB::insert('INSERT INTO support_tickets (user_id,subject) VALUES (?,?)', [$uid, self::s('subject', 200, true)]);
            DB::exec('INSERT INTO support_messages (ticket_id,sender,body) VALUES (?,?,?)', [$id, 'user', self::s('body', 3000, true)]); return $id; });
        return ['ticket_id' => $id];
    }
    private static function h_export(string $m): array {
        $uid = self::uid(); if (!Limits::of($uid)['export_data']) throw new ApiError('Eksport Premium uchun', 402);
        $o = ['exported_at' => now()]; foreach (['tasks', 'goals', 'habits', 'reminders', 'financial_records', 'budgets', 'savings_goals', 'xp_transactions'] as $t) $o[$t] = DB::all("SELECT * FROM $t WHERE user_id=?", [$uid]);
        return $o;
    }
}
