<?php
// Foydalanuvchi API:  /api/index.php?r=<resurs>   (Authorization: Bearer <token>)
declare(strict_types=1);
require dirname(__DIR__) . '/src/bootstrap.php';
UserApi::run();
