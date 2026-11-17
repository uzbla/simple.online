<?php
declare(strict_types=1);
final class Queue {
    /** Scheduled kampaniyalarni qabul qiluvchilarga yoyadi (INSERT..SELECT, UNIQUE bilan idempotent) */
    static function expandCampaigns(): int {
        $c = 0;
        foreach (DB::all('SELECT * FROM notifications WHERE status="scheduled" AND send_at<=?', [now()]) as $n) {
            if (DB::exec('UPDATE notifications SET status="sending" WHERE id=? AND status="scheduled"', [$n['id']]) !== 1) continue;
            $where = 'u.status="active"'; $p = [$n['id']];
            if ($n['audience'] === 'premium') $where .= ' AND u.premium_until>UTC_TIMESTAMP()';
            elseif ($n['audience'] === 'free') $where .= ' AND (u.premium_until IS NULL OR u.premium_until<=UTC_TIMESTAMP())';
            elseif ($n['audience'] === 'selected') {
                $ids = array_values(array_filter(array_map('intval', json_decode((string)$n['audience_ids'], true) ?: [])));
                if (!$ids) { DB::exec('UPDATE notifications SET status="done" WHERE id=?', [$n['id']]); continue; }
                $where .= ' AND u.id IN (' . implode(',', $ids) . ')';
            }
            $t = DB::exec("INSERT IGNORE INTO notification_queue (kind,notification_id,user_id,chat_id) SELECT 'campaign',?,u.id,u.telegram_id FROM users u WHERE $where", $p);
            DB::exec('UPDATE notifications SET total=? WHERE id=?', [$t, $n['id']]);
            $c++;
        }
        return $c;
    }
    /** Yakunlangan kampaniyalarni "done" qiladi */
    static function finalize(): void {
        DB::exec('UPDATE notifications n SET status="done" WHERE status="sending" AND NOT EXISTS
                  (SELECT 1 FROM notification_queue q WHERE q.notification_id=n.id AND q.status IN ("pending","sending"))');
    }
    /** Navbatni Telegram limitlariga rioya qilib yuboradi. */
    static function process(int $maxSeconds = 45): array {
        $rate = max(1, min(28, (int)Settings::get('queue_rate_per_sec', '20')));
        $maxAtt = max(1, (int)Settings::get('queue_max_attempts', '3'));
        $start = microtime(true); $sent = 0; $failed = 0; $delay = (int)(1e6 / $rate);
        DB::exec('UPDATE notification_queue SET status="pending" WHERE status="sending" AND next_attempt_at<?', [gmdate('Y-m-d H:i:s', time() - 300)]); // osilib qolganlar
        while (microtime(true) - $start < $maxSeconds) {
            $rows = DB::all('SELECT q.*, n.body, n.button_text, n.button_url, n.image_url FROM notification_queue q
                             LEFT JOIN notifications n ON n.id=q.notification_id
                             WHERE q.status="pending" AND q.next_attempt_at<=? ORDER BY q.id LIMIT 20', [now()]);
            if (!$rows) break;
            foreach ($rows as $r) {
                if (DB::exec('UPDATE notification_queue SET status="sending", next_attempt_at=? WHERE id=? AND status="pending"', [now(), $r['id']]) !== 1) continue;
                $text = $r['kind'] === 'campaign' ? Telegram::sanitize((string)$r['body']) : (string)$r['text'];
                $res = Telegram::send((int)$r['chat_id'], $text, ['button_text' => $r['button_text'], 'button_url' => $r['button_url'], 'image_url' => $r['image_url']]);
                $att = (int)$r['attempts'] + 1;
                if ($res['ok']) {
                    DB::exec('UPDATE notification_queue SET status="sent", attempts=?, sent_at=?, error=NULL WHERE id=?', [$att, now(), $r['id']]); $sent++;
                    if ($r['reminder_id']) DB::exec('UPDATE reminder_logs SET status="sent" WHERE reminder_id=? AND status="queued" ORDER BY id DESC LIMIT 1', [$r['reminder_id']]);
                } else {
                    $retry = $res['retry_after'] ?? 0;
                    if ($retry > 0) { // 429 — navbat holatini saqlab kutamiz
                        DB::exec('UPDATE notification_queue SET status="pending", next_attempt_at=? WHERE id=?', [gmdate('Y-m-d H:i:s', time() + $retry + 1), $r['id']]);
                        usleep(min($retry, 5) * 1000000); continue;
                    }
                    if (!empty($res['fatal']) || $att >= $maxAtt) {
                        DB::exec('UPDATE notification_queue SET status="failed", attempts=?, error=? WHERE id=?', [$att, $res['error'] ?? '', $r['id']]); $failed++;
                        if ($r['reminder_id']) DB::exec('UPDATE reminder_logs SET status="failed", error=? WHERE reminder_id=? AND status="queued" ORDER BY id DESC LIMIT 1', [$res['error'] ?? '', $r['reminder_id']]);
                    } else {
                        DB::exec('UPDATE notification_queue SET status="pending", attempts=?, error=?, next_attempt_at=? WHERE id=?',
                            [$att, $res['error'] ?? '', gmdate('Y-m-d H:i:s', time() + 60 * $att), $r['id']]);
                    }
                }
                usleep($delay);
                if (microtime(true) - $start >= $maxSeconds) break;
            }
        }
        return ['sent' => $sent, 'failed' => $failed];
    }
    static function direct(int $userId, string $text, string $kind = 'direct'): bool {
        $u = DB::one('SELECT telegram_id FROM users WHERE id=? AND status="active"', [$userId]);
        if (!$u) return false;
        DB::exec('INSERT INTO notification_queue (kind,user_id,chat_id,text) VALUES (?,?,?,?)', [$kind, $userId, $u['telegram_id'], Telegram::sanitize($text)]);
        return true;
    }
}
