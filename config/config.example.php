<?php
// Nusxa oling: config/config.php (git'ga kiritilmaydi). Maxfiy qiymatlar FAQAT shu yerda — interfeysda ko‘rsatilmaydi.
$e = fn(string $k, $d = '') => getenv($k) !== false ? getenv($k) : $d;
return [
  'db' => ['host' => $e('INTIZOM_DB_HOST', '127.0.0.1'), 'name' => $e('INTIZOM_DB_NAME', 'intizom'),
           'user' => $e('INTIZOM_DB_USER', 'intizom'), 'pass' => $e('INTIZOM_DB_PASS', '')],
  'telegram' => [
    'bot_token'      => $e('INTIZOM_BOT_TOKEN'),
    'api_base'       => $e('INTIZOM_TG_API', 'https://api.telegram.org'),
    'webhook_secret' => $e('INTIZOM_WEBHOOK_SECRET'),   // setWebhook secret_token
    'miniapp_url'    => $e('INTIZOM_MINIAPP_URL', 'https://app.yourdomain.com'),
  ],
  'app' => ['origin' => $e('INTIZOM_APP_ORIGIN', ''), 'dev_login' => $e('INTIZOM_DEV_LOGIN', '0') === '1'],   // dev_login FAQAT lokal sinov uchun
  'payments' => ['webhook_secret' => $e('INTIZOM_PAY_SECRET')],   // HMAC-SHA256 kaliti
  'admin' => ['base_url' => $e('INTIZOM_ADMIN_URL', ''), 'force_https' => $e('INTIZOM_FORCE_HTTPS', '1') === '1',
              'ip_header' => $e('INTIZOM_IP_HEADER', '')],      // proxy orqasida: masalan HTTP_X_FORWARDED_FOR
  'mysqldump' => $e('INTIZOM_MYSQLDUMP', 'mysqldump'), 'mysql' => $e('INTIZOM_MYSQL', 'mysql'),
];
