<?php
// Provayder webhooki. Imzo: X-Signature = hex(HMAC-SHA256(raw_body, INTIZOM_PAY_SECRET)). Idempotent.
// Body: {"event":"payment.paid|payment.failed|payment.refunded","payment_id":<bizning id>,"provider_payment_id":"...","amount":"49000","currency":"UZS","reason":"..."}
declare(strict_types=1);
require dirname(__DIR__) . '/src/bootstrap.php';
header('Content-Type: application/json');
$secret = cfg('payments.webhook_secret'); $raw = file_get_contents('php://input') ?: '';
if (!$secret || !hash_equals(hash_hmac('sha256', $raw, $secret), strtolower($_SERVER['HTTP_X_SIGNATURE'] ?? ''))) { http_response_code(401); slog('warning', 'payments', 'Webhook imzosi noto‘g‘ri', ['ip' => client_ip()]); exit('{"ok":false}'); }
$j = json_decode($raw, true); $ev = $j['event'] ?? '';
$map = ['payment.paid' => 'paid', 'payment.failed' => 'failed', 'payment.refunded' => 'refunded'];
if (!is_array($j) || !isset($map[$ev]) || empty($j['payment_id'])) { http_response_code(400); exit('{"ok":false}'); }
try {
    $res = DB::tx(function () use ($j, $map, $ev) {
        $p = DB::one('SELECT * FROM payments WHERE id=? FOR UPDATE', [(int)$j['payment_id']]);
        if (!$p) return 'unknown';
        $new = $map[$ev]; $pp = isset($j['provider_payment_id']) ? mb_substr((string)$j['provider_payment_id'], 0, 100) : null;
        if ($p['status'] === $new) return 'duplicate';                                   // idempotent
        if (isset($j['amount']) && $new === 'paid' && (abs((float)$j['amount'] - (float)$p['amount']) > 0.009 || ($j['currency'] ?? $p['currency']) !== $p['currency'])) return 'amount_mismatch';
        $allowed = ['pending' => ['paid', 'failed'], 'failed' => ['paid'], 'paid' => ['refunded'], 'cancelled' => ['paid']];
        if (!in_array($new, $allowed[$p['status']] ?? [], true)) return 'bad_transition';
        DB::exec('UPDATE payments SET status=?, provider_payment_id=COALESCE(provider_payment_id,?), paid_at=IF(?="paid",UTC_TIMESTAMP(),paid_at), fail_reason=? WHERE id=?',
            [$new, $pp, $new, $new === 'failed' ? mb_substr((string)($j['reason'] ?? ''), 0, 255) : null, $p['id']]);
        if ($new === 'paid' && $p['plan_id']) { $pl = DB::one('SELECT * FROM subscription_plans WHERE id=?', [$p['plan_id']]);
            if ($pl && (int)$pl['duration_days'] > 0) Subs::grant((int)$p['user_id'], (int)$pl['id'], (int)$pl['duration_days'], 'payment', (int)$p['id']); }
        if ($new === 'refunded') { DB::exec('UPDATE subscriptions SET status="cancelled", cancelled_at=UTC_TIMESTAMP() WHERE payment_id=? AND status="active"', [$p['id']]); Subs::recompute((int)$p['user_id']); }
        return $new;
    });
    if ($res === 'paid') { $p = DB::one('SELECT user_id FROM payments WHERE id=?', [(int)$j['payment_id']]); Queue::direct((int)$p['user_id'], '✅ To‘lov qabul qilindi. Premium faollashtirildi!', 'direct'); }
    if (in_array($res, ['unknown', 'amount_mismatch', 'bad_transition'], true)) slog('warning', 'payments', "Webhook rad etildi: $res", ['payment_id' => $j['payment_id']]);
    echo json_encode(['ok' => !in_array($res, ['unknown', 'amount_mismatch', 'bad_transition'], true), 'result' => $res]);
} catch (Throwable $e) { slog('error', 'payments', $e->getMessage()); http_response_code(500); echo '{"ok":false}'; }
