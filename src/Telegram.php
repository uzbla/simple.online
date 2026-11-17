<?php
declare(strict_types=1);
final class Telegram {
    /** @return array{ok:bool, retry_after?:int, error?:string, fatal?:bool, data?:array} */
    static function call(string $method, array $params): array {
        $tok = cfg('telegram.bot_token');
        if (!$tok) return ['ok' => false, 'error' => 'Bot token sozlanmagan', 'fatal' => true];
        $ch = curl_init(rtrim((string)cfg('telegram.api_base', 'https://api.telegram.org'), '/') . "/bot$tok/$method");
        curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15, CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_POSTFIELDS => json_encode($params, JSON_UNESCAPED_UNICODE), CURLOPT_HTTPHEADER => ['Content-Type: application/json']]);
        $raw = curl_exec($ch); $err = curl_error($ch); curl_close($ch);
        if ($raw === false) return ['ok' => false, 'error' => 'Tarmoq: ' . $err];
        $j = json_decode($raw, true);
        if (!empty($j['ok'])) return ['ok' => true, 'data' => $j['result'] ?? []];
        $code = (int)($j['error_code'] ?? 0);
        return ['ok' => false, 'error' => mb_substr($j['description'] ?? 'Noma’lum xato', 0, 300),
                'retry_after' => (int)($j['parameters']['retry_after'] ?? 0),
                'fatal' => in_array($code, [400, 403], true)];   // bloklagan / chat yo‘q — qayta urinish befoyda
    }
    static function send(int $chatId, string $text, array $o = []): array {
        $kb = !empty($o['button_url']) ? ['inline_keyboard' => [[['text' => $o['button_text'] ?: 'Ochish', 'url' => $o['button_url']]]]] : null;
        if (!empty($o['image_url'])) {
            $p = ['chat_id' => $chatId, 'photo' => $o['image_url'], 'caption' => mb_substr($text, 0, 1024), 'parse_mode' => 'HTML'];
            if ($kb) $p['reply_markup'] = $kb; return self::call('sendPhoto', $p);
        }
        $p = ['chat_id' => $chatId, 'text' => mb_substr($text, 0, 4096), 'parse_mode' => 'HTML', 'disable_web_page_preview' => true];
        if (!empty($o['reply_markup'])) $p['reply_markup'] = $o['reply_markup']; elseif ($kb) $p['reply_markup'] = $kb;
        return self::call('sendMessage', $p);
    }
    /** Telegram qo‘llaydigan teglardan boshqasini olib tashlaydi */
    static function sanitize(string $html): string {
        $h = strip_tags($html, '<b><i><u><s><code><pre><a>');
        return preg_replace_callback('#<a\s+[^>]*>#i', function ($m) {
            return preg_match('#href=["\'](https://[^"\'\s<>]+)["\']#i', $m[0], $x) ? '<a href="' . e($x[1]) . '">' : '<a>';
        }, $h);
    }
}
