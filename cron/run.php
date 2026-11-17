<?php
// crontab:  * * * * * /usr/bin/php /path/to/cron/run.php >> /path/to/storage/logs/cron.log 2>&1
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/src/bootstrap.php';
if (!DB::val('SELECT GET_LOCK("intizom_cron", 0)')) exit("boshqa jarayon ishlayapti\n");   // ustma-ust ishga tushishdan himoya
try {
    Settings::set('cron_heartbeat', now());
    $r = Jobs::tick();
    echo gmdate('c') . ' ' . json_encode($r) . "\n";
} catch (Throwable $e) { slog('error', 'cron', $e->getMessage(), ['line' => $e->getLine()]); echo 'XATO: ' . $e->getMessage() . "\n"; }
DB::val('SELECT RELEASE_LOCK("intizom_cron")');
