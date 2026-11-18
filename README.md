# INTIZOM OS — Reach Your Summit.

Backend (PHP 8.3 + PDO + MySQL/MariaDB), Telegram bot va **Admin Panel** (V3.0 Everest Edition).

> Tarkibi: **User App (Telegram Mini App)**, **foydalanuvchi REST API**, **Telegram bot**, **Admin Panel**, cron va MySQL sxemasi.

## Tuzilma
```
app/          Mini App (HTML/CSS/JS, Liquid Glass, Telegram WebApp SDK)  index.html, app.js, app.css, config.js
admin/        Admin Panel (document root: admin.yourdomain.com)  index.php + pages/ + assets/
api/          index.php (foydalanuvchi REST API), payment_webhook.php (imzolangan provayder webhooki)
bot/          webhook.php          (Telegram bot buyruqlari)
cron/run.php  har daqiqada: eslatmalar, navbat, kunlik/haftalik/oylik ishlar
src/          DB, Auth, Totp, Perm (RBAC), Audit, Xp, Reminders, Queue, Subs, Backup, Jobs, Telegram
sql/          schema.sql, seed.sql
config/       config.example.php  → config.php (git'da yo‘q)
storage/      backuplar (web-root'dan tashqarida)
```

## O‘rnatish
```bash
mariadb -e "CREATE DATABASE intizom CHARACTER SET utf8mb4"
mariadb intizom < sql/schema.sql && mariadb intizom < sql/seed.sql   # schema'ni DB'ning triggerlar yarata oladigan foydalanuvchisi bilan
cp config/config.example.php config/config.php                        # DB, bot token, webhook sirlari
php bin/create_admin.php root "Ism Familiya"                          # birinchi Super Admin (parol so‘raladi)
# crontab:  * * * * * php /path/cron/run.php >> /path/storage/logs/cron.log 2>&1
# Telegram: setWebhook url=https://api.yourdomain.com/bot/webhook.php  secret_token=<webhook_secret>
```
Web-server document root: `admin/` (panel), `api/` va `bot/` alohida host/yo‘l orqali. `src/`, `config/`, `storage/` ochiq bo‘lmasligi shart. Panel HTTPS talab qiladi (`INTIZOM_FORCE_HTTPS=0` faqat lokal).

## User App va API
Bo‘limlar: Dashboard (Everest tog‘i, XP, streak), Kalendar (oy/hafta/kun), Vazifalar (kategoriya, muhimlik, takrorlanish, kechikkanlar), Maqsadlar (bosqichlar, avtomatik progress), Odatlar (streak, hafta kunlari, pauza), Moliya (daromad/xarajat, balans, budjet, jamg‘arma, kategoriya hisoboti), Statistika, Eslatmalar (+tarix), Premium, Support, Profil (sozlamalar, eksport, hisobni o‘chirish).
- Kirish: Telegram `initData` HMAC imzosi serverda tekshiriladi (24 soat, soxta/eskirgan rad etiladi) → 7 kunlik Bearer token (DB'da hash).
- Har bir so‘rov `user_id` bo‘yicha ajratilgan, rate limit (240/daq), kiritish qat’iy validatsiya qilinadi.
- Free/Premium limitlari (`max_goals`, `max_habits`, `max_reminders`, `full_stats`, `finance_reports`, `export_data`) Admin Paneldagi tarifdan olinadi; limit oshsa `402`.
- XP: vazifa/odat/maqsad/kunlik reja — `UNIQUE(user,action,ref)` bilan takrorlanmaydi.
- Joylashtirish: `app/` va `api/` bitta hostda (`config.js` → `../api/index.php`) yoki alohida hostda (`INTIZOM_APP_ORIGIN` — CORS). Telegram BotFather'da Mini App URL = `app/index.html`. `INTIZOM_DEV_LOGIN=1` faqat lokal sinov uchun (`?dev=<telegram_id>`), productionda **o‘chiq** bo‘lsin. Web-serverda `Content-Security-Policy: frame-ancestors https://web.telegram.org https://*.telegram.org` headerini qo‘shing.

## Admin Panel bo‘limlari
Dashboard · Analytics (CSV) · Foydalanuvchilar (+profil, bloklash, Premium, xabar) · Support · Obunalar/tariflar · To‘lovlar · Bildirishnomalar (navbat, rejalashtirish) · Kontent · Eslatmalar nazorati · XP & Everest · Adminlar (rollar + individual huquqlar) · Admin loglari · Security Center · Sozlamalar · Monitoring · Backup/Restore.

## Xavfsizlik (amalda tekshirilgan)
- Parol siyosati, 5 ta xato/15 daqiqa limit (login va IP bo‘yicha), TOTP 2FA **majburiy** (replay himoyasi), sessiya tokeni DB'da hash, 2FA'dan keyin token almashadi, harakatsizlik muddati.
- CSRF token (barcha POST), qat’iy CSP (inline script/style yo‘q), `SameSite=Strict`, `HttpOnly`, prepared statements, chiqishda `e()`.
- RBAC: rol huquqlari + individual allow/deny; `admins.manage`, `xp.adjust`, `backup.restore` faqat Super Admin (delegatsiya qilinmaydi).
- `admin_logs` DB triggerlari bilan append-only; maxfiy maydonlarni ochish, qo‘lda Premium/XP, backup — hammasi sabab bilan auditga yoziladi.
- To‘lov faqat HMAC-SHA256 imzolangan webhook orqali tasdiqlanadi (idempotent, summa tekshiriladi); admin "paid" qila olmaydi.
- Bir eslatma ikki marta yuborilmaydi (`UNIQUE(reminder_id, scheduled_for)` + atomik claim); XP takrorlanmaydi (`UNIQUE(user, action, ref)`).
- Backup: checksum, yuklab olish, tiklash (parol + tasdiq matni + sabab, avtomatik pre-restore zaxira, xatoda avtomatik qaytarish).
- Maxfiy tokenlar faqat `config.php`/env'da; interfeysda faqat "sozlangan/yo‘q" ko‘rsatiladi.

## Ma’lum cheklovlar
- Parolni tiklash admin Telegram'ga bog‘langan bo‘lishini talab qiladi (Profil → bog‘lash kodi → `/admin KOD`); aks holda Super Admin tiklaydi.
- "Excel" eksporti UTF-8 BOM'li CSV (Excel to‘g‘ri ochadi).
- Premium sotib olish ilovada emas, bot orqali / support bilan; to‘lov provayderiga xos checkout (Click/Payme) ulanmagan — webhook formati `api/payment_webhook.php` boshida.
