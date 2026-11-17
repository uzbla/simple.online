<?php
declare(strict_types=1);
define('ROOT', dirname(__DIR__));
$f = ROOT . '/config/config.php';
$GLOBALS['cfg'] = require is_file($f) ? $f : ROOT . '/config/config.example.php';
date_default_timezone_set('UTC');
mb_internal_encoding('UTF-8');
spl_autoload_register(function (string $c) { $p = ROOT . '/src/' . $c . '.php'; if (is_file($p)) require $p; });
require ROOT . '/src/helpers.php';
