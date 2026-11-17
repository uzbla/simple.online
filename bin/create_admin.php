<?php
// Birinchi Super Admin yaratish:  php bin/create_admin.php <login> "<Ism>"   (parol so‘raladi yoki INTIZOM_ADMIN_PASSWORD env)
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit(1);
require dirname(__DIR__) . '/src/bootstrap.php';
[$_, $login, $name] = $argv + [null, null, 'Super Admin'];
if (!$login) exit("Foydalanish: php bin/create_admin.php <login> \"<Ism>\"\n");
$pw = getenv('INTIZOM_ADMIN_PASSWORD') ?: '';
if ($pw === '') { fwrite(STDERR, 'Parol: '); system('stty -echo 2>/dev/null'); $pw = trim((string)fgets(STDIN)); system('stty echo 2>/dev/null'); echo "\n"; }
if ($err = Auth::policyError($pw)) exit("$err\n");
if (DB::val('SELECT COUNT(*) FROM admins WHERE role_id=1') > 0 && getenv('INTIZOM_FORCE') !== '1') exit("Super Admin allaqachon mavjud. Boshqa adminlarni panel orqali yarating.\n");
DB::exec('INSERT INTO admins (login,name,password_hash,role_id) VALUES (?,?,?,1)', [$login, $name, password_hash($pw, PASSWORD_DEFAULT)]);
echo "Super Admin yaratildi. Birinchi kirishda 2FA sozlanadi.\n";
