-- INTIZOM OS v3.0 — MySQL 8 / MariaDB 10.5+  (barcha vaqtlar UTC da saqlanadi)
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS=0;

-- ===== USER =====
CREATE TABLE users (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  telegram_id BIGINT UNSIGNED NOT NULL UNIQUE,
  first_name VARCHAR(100) NOT NULL DEFAULT '',
  last_name VARCHAR(100) NOT NULL DEFAULT '',
  username VARCHAR(64) NULL,
  photo_url VARCHAR(500) NULL,
  language VARCHAR(8) NOT NULL DEFAULT 'uz',
  timezone VARCHAR(64) NOT NULL DEFAULT 'Asia/Tashkent',
  status ENUM('active','blocked','deleted') NOT NULL DEFAULT 'active',
  blocked_reason VARCHAR(255) NULL,
  premium_until DATETIME NULL,
  xp_total INT NOT NULL DEFAULT 0,
  level_id INT UNSIGNED NULL,
  streak INT NOT NULL DEFAULT 0,
  last_active_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY (status), KEY (premium_until), KEY (created_at), KEY (last_active_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE user_settings (
  user_id BIGINT UNSIGNED PRIMARY KEY,
  notifications_enabled TINYINT(1) NOT NULL DEFAULT 1,
  daily_report TINYINT(1) NOT NULL DEFAULT 1,
  weekly_report TINYINT(1) NOT NULL DEFAULT 1,
  privacy_json JSON NULL,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE user_sessions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  token_hash CHAR(64) NOT NULL UNIQUE,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  expires_at DATETIME NOT NULL,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE user_activity (
  user_id BIGINT UNSIGNED NOT NULL,
  day DATE NOT NULL,
  PRIMARY KEY (user_id, day), KEY (day),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ===== PLANNER =====
CREATE TABLE tasks (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  title VARCHAR(255) NOT NULL,
  note TEXT NULL,
  category ENUM('work','study','sport','personal','family','finance','other') NOT NULL DEFAULT 'other',
  priority TINYINT NOT NULL DEFAULT 2,
  due_at DATETIME NULL,
  repeat_rule VARCHAR(64) NULL,
  status ENUM('todo','done','overdue') NOT NULL DEFAULT 'todo',
  completed_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY (user_id, status), KEY (due_at), KEY (completed_at),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE task_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  task_id BIGINT UNSIGNED NOT NULL,
  action VARCHAR(32) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE daily_plans (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  plan_date DATE NOT NULL,
  data_json JSON NULL,
  completed TINYINT(1) NOT NULL DEFAULT 0,
  UNIQUE KEY (user_id, plan_date),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ===== GOALS =====
CREATE TABLE goals (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  title VARCHAR(255) NOT NULL,
  description TEXT NULL,
  term ENUM('daily','weekly','monthly','yearly','long') NOT NULL DEFAULT 'monthly',
  start_date DATE NULL, end_date DATE NULL,
  expected_result VARCHAR(500) NULL,
  progress TINYINT UNSIGNED NOT NULL DEFAULT 0,
  status ENUM('active','done','archived') NOT NULL DEFAULT 'active',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY (user_id, status), KEY (end_date), KEY (created_at),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE goal_milestones (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  goal_id BIGINT UNSIGNED NOT NULL,
  title VARCHAR(255) NOT NULL,
  done_at DATETIME NULL,
  FOREIGN KEY (goal_id) REFERENCES goals(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ===== HABITS =====
CREATE TABLE habits (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  title VARCHAR(255) NOT NULL,
  days_mask TINYINT UNSIGNED NOT NULL DEFAULT 127,
  streak INT NOT NULL DEFAULT 0,
  status ENUM('active','paused','archived') NOT NULL DEFAULT 'active',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY (user_id, status),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE habit_logs (
  habit_id BIGINT UNSIGNED NOT NULL,
  log_date DATE NOT NULL,
  PRIMARY KEY (habit_id, log_date), KEY (log_date),
  FOREIGN KEY (habit_id) REFERENCES habits(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ===== REMINDERS =====
CREATE TABLE reminders (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  type ENUM('morning_plan','task','sport','reading','water','wake','sleep','day_end','weekly_report','goal_deadline','custom') NOT NULL DEFAULT 'custom',
  message VARCHAR(500) NOT NULL,
  time_of_day TIME NOT NULL,               -- foydalanuvchi mahalliy vaqti
  days_mask TINYINT UNSIGNED NOT NULL DEFAULT 127,  -- bit0=Du ... bit6=Ya; 0 = bir martalik
  once_date DATE NULL,
  lead_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  enabled TINYINT(1) NOT NULL DEFAULT 1,
  next_run_at DATETIME NULL,               -- UTC
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY (enabled, next_run_at),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE reminder_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  reminder_id BIGINT UNSIGNED NOT NULL,
  scheduled_for DATETIME NOT NULL,
  status ENUM('queued','sent','failed') NOT NULL DEFAULT 'queued',
  error VARCHAR(500) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_once (reminder_id, scheduled_for),   -- bitta eslatma ikki marta yuborilmaydi
  KEY (status, created_at),
  FOREIGN KEY (reminder_id) REFERENCES reminders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE notifications (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(150) NOT NULL,
  body TEXT NOT NULL,
  button_text VARCHAR(64) NULL, button_url VARCHAR(500) NULL, image_url VARCHAR(500) NULL,
  audience ENUM('all','premium','free','selected') NOT NULL,
  audience_ids JSON NULL,
  send_at DATETIME NOT NULL,
  status ENUM('scheduled','sending','done','cancelled') NOT NULL DEFAULT 'scheduled',
  total INT NOT NULL DEFAULT 0,
  created_by INT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY (status, send_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE notification_queue (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  kind ENUM('campaign','reminder','report','direct','support') NOT NULL,
  notification_id BIGINT UNSIGNED NULL,
  reminder_id BIGINT UNSIGNED NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  chat_id BIGINT NOT NULL,
  text TEXT NULL,
  status ENUM('pending','sending','sent','failed','cancelled') NOT NULL DEFAULT 'pending',
  attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
  next_attempt_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  error VARCHAR(500) NULL,
  sent_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY (status, next_attempt_at), KEY (notification_id, status), KEY (sent_at),
  UNIQUE KEY uq_campaign_user (notification_id, user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ===== FINANCE =====
CREATE TABLE financial_records (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  type ENUM('income','expense') NOT NULL,
  amount DECIMAL(16,2) NOT NULL,
  currency CHAR(3) NOT NULL DEFAULT 'UZS',
  category VARCHAR(64) NOT NULL DEFAULT '',
  note VARCHAR(255) NULL,
  record_date DATE NOT NULL,
  KEY (user_id, record_date),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE budgets (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, user_id BIGINT UNSIGNED NOT NULL,
  category VARCHAR(64) NOT NULL, month CHAR(7) NOT NULL, amount DECIMAL(16,2) NOT NULL,
  UNIQUE KEY (user_id, category, month), FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE savings_goals (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, user_id BIGINT UNSIGNED NOT NULL,
  title VARCHAR(255) NOT NULL, target DECIMAL(16,2) NOT NULL, saved DECIMAL(16,2) NOT NULL DEFAULT 0,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ===== GAMIFICATION =====
CREATE TABLE level_rules (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(64) NOT NULL, min_xp INT NOT NULL UNIQUE, icon VARCHAR(16) NOT NULL DEFAULT '⛰️'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE xp_rules (
  action_type VARCHAR(32) PRIMARY KEY, label VARCHAR(100) NOT NULL,
  xp INT NOT NULL DEFAULT 0, active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE xp_transactions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  action_type VARCHAR(32) NOT NULL,
  ref_id VARCHAR(64) NOT NULL,           -- masalan task:123 — takroriy XP oldini oladi
  amount INT NOT NULL,
  reason VARCHAR(255) NULL,
  admin_id INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_xp (user_id, action_type, ref_id), KEY (created_at),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE user_levels (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, user_id BIGINT UNSIGNED NOT NULL,
  level_id INT UNSIGNED NOT NULL, reached_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY (user_id, level_id),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (level_id) REFERENCES level_rules(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ===== PREMIUM =====
CREATE TABLE subscription_plans (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(32) NOT NULL UNIQUE, name VARCHAR(100) NOT NULL,
  price DECIMAL(14,2) NOT NULL DEFAULT 0, currency CHAR(3) NOT NULL DEFAULT 'UZS',
  duration_days INT NOT NULL DEFAULT 0, trial_days INT NOT NULL DEFAULT 0,
  limits_json JSON NOT NULL, is_active TINYINT(1) NOT NULL DEFAULT 1,
  deleted_at DATETIME NULL, sort_order INT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE subscriptions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL, plan_id INT UNSIGNED NOT NULL,
  status ENUM('active','expired','cancelled') NOT NULL DEFAULT 'active',
  is_trial TINYINT(1) NOT NULL DEFAULT 0,
  source ENUM('payment','admin','trial') NOT NULL DEFAULT 'payment',
  payment_id BIGINT UNSIGNED NULL,
  starts_at DATETIME NOT NULL, ends_at DATETIME NOT NULL,
  cancelled_at DATETIME NULL, granted_by INT UNSIGNED NULL, reason VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY (user_id, status), KEY (status, ends_at), KEY (created_at),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (plan_id) REFERENCES subscription_plans(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE payments (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL, plan_id INT UNSIGNED NULL,
  provider VARCHAR(32) NOT NULL DEFAULT '', provider_payment_id VARCHAR(100) NULL UNIQUE,
  amount DECIMAL(14,2) NOT NULL, currency CHAR(3) NOT NULL DEFAULT 'UZS',
  method VARCHAR(32) NOT NULL DEFAULT '',
  status ENUM('pending','paid','failed','refunded','cancelled') NOT NULL DEFAULT 'pending',
  fail_reason VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, paid_at DATETIME NULL,
  KEY (status, created_at), KEY (user_id),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ===== ADMIN =====
CREATE TABLE admin_roles (
  id INT UNSIGNED PRIMARY KEY, code VARCHAR(32) NOT NULL UNIQUE, name VARCHAR(64) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE admins (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  login VARCHAR(64) NOT NULL UNIQUE, name VARCHAR(100) NOT NULL,
  password_hash VARCHAR(255) NOT NULL, must_change_password TINYINT(1) NOT NULL DEFAULT 0,
  role_id INT UNSIGNED NOT NULL,
  status ENUM('active','disabled') NOT NULL DEFAULT 'active',
  totp_secret VARCHAR(64) NULL, totp_enabled TINYINT(1) NOT NULL DEFAULT 0, totp_last_step BIGINT NULL,
  telegram_chat_id BIGINT NULL, link_code CHAR(10) NULL,
  last_login_at DATETIME NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (role_id) REFERENCES admin_roles(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
-- role_id to‘ldirilsa — rol huquqi; admin_id to‘ldirilsa — individual override (granted=0 → taqiqlash)
CREATE TABLE admin_permissions (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  role_id INT UNSIGNED NULL, admin_id INT UNSIGNED NULL,
  permission VARCHAR(48) NOT NULL, granted TINYINT(1) NOT NULL DEFAULT 1,
  UNIQUE KEY (role_id, permission), UNIQUE KEY (admin_id, permission),
  FOREIGN KEY (role_id) REFERENCES admin_roles(id) ON DELETE CASCADE,
  FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE admin_sessions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  admin_id INT UNSIGNED NOT NULL, token_hash CHAR(64) NOT NULL UNIQUE, csrf CHAR(64) NOT NULL,
  twofa_ok TINYINT(1) NOT NULL DEFAULT 0,
  ip VARCHAR(45) NOT NULL, user_agent VARCHAR(255) NOT NULL DEFAULT '',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, last_seen_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  revoked_at DATETIME NULL,
  KEY (admin_id, revoked_at),
  FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE admin_login_attempts (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  login VARCHAR(64) NOT NULL, ip VARCHAR(45) NOT NULL, success TINYINT(1) NOT NULL,
  kind VARCHAR(16) NOT NULL DEFAULT 'password', user_agent VARCHAR(255) NOT NULL DEFAULT '',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY (login, created_at), KEY (ip, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE password_resets (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, admin_id INT UNSIGNED NOT NULL,
  token_hash CHAR(64) NOT NULL UNIQUE, expires_at DATETIME NOT NULL, used_at DATETIME NULL,
  FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE admin_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  admin_id INT UNSIGNED NULL, admin_login VARCHAR(64) NOT NULL DEFAULT '',
  section VARCHAR(32) NOT NULL, action VARCHAR(48) NOT NULL,
  target_user_id BIGINT UNSIGNED NULL, target_ref VARCHAR(64) NULL,
  old_value JSON NULL, new_value JSON NULL, reason VARCHAR(255) NULL,
  ip VARCHAR(45) NOT NULL DEFAULT '', session_id BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY (created_at), KEY (admin_id), KEY (section, action), KEY (target_user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
-- Loglar o‘zgartirilmaydi va o‘chirilmaydi (DB darajasida)
CREATE TRIGGER admin_logs_no_update BEFORE UPDATE ON admin_logs FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='admin_logs append-only';
CREATE TRIGGER admin_logs_no_delete BEFORE DELETE ON admin_logs FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='admin_logs append-only';

-- ===== CONTENT =====
CREATE TABLE announcements (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, title VARCHAR(150) NOT NULL, body TEXT NOT NULL,
  publish_from DATETIME NULL, publish_to DATETIME NULL, is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE motivational_quotes (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, text VARCHAR(500) NOT NULL, author VARCHAR(100) NULL,
  kind ENUM('motivation','quote') NOT NULL DEFAULT 'motivation', show_date DATE NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE banners (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, title VARCHAR(150) NOT NULL, image_url VARCHAR(500) NOT NULL,
  link_url VARCHAR(500) NULL, publish_from DATETIME NULL, publish_to DATETIME NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE content_pages (
  slug ENUM('help','terms','privacy') PRIMARY KEY, title VARCHAR(150) NOT NULL, body MEDIUMTEXT NOT NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ===== SUPPORT =====
CREATE TABLE support_tickets (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, user_id BIGINT UNSIGNED NOT NULL,
  subject VARCHAR(200) NOT NULL,
  status ENUM('new','in_progress','waiting','resolved','closed') NOT NULL DEFAULT 'new',
  assigned_admin_id INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY (status), FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE support_messages (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, ticket_id BIGINT UNSIGNED NOT NULL,
  sender ENUM('user','admin') NOT NULL, admin_id INT UNSIGNED NULL, body TEXT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (ticket_id) REFERENCES support_tickets(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ===== SYSTEM =====
CREATE TABLE system_settings (k VARCHAR(64) PRIMARY KEY, v TEXT NULL, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE system_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, level ENUM('info','warning','error') NOT NULL DEFAULT 'info',
  channel VARCHAR(32) NOT NULL, message VARCHAR(1000) NOT NULL, context JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, KEY (level, created_at), KEY (channel)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE backup_logs (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, filename VARCHAR(120) NOT NULL, size_bytes BIGINT NOT NULL DEFAULT 0,
  sha256 CHAR(64) NULL, kind ENUM('manual','scheduled','pre_restore') NOT NULL DEFAULT 'manual',
  status ENUM('ok','failed','restored') NOT NULL DEFAULT 'ok', note VARCHAR(255) NULL,
  admin_id INT UNSIGNED NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
SET FOREIGN_KEY_CHECKS=1;
