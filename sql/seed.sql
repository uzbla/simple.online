SET NAMES utf8mb4;
INSERT INTO admin_roles (id,code,name) VALUES (1,'super_admin','Super Admin'),(2,'administrator','Administrator'),(3,'moderator','Moderator'),(4,'analyst','Analyst');
-- Super Admin huquqlari kodda (barchasi). Qolgan rollar:
INSERT INTO admin_permissions (role_id,permission) VALUES
 (2,'dashboard.view'),(2,'users.view'),(2,'users.manage'),(2,'premium.manage'),(2,'payments.view'),(2,'payments.manage'),
 (2,'notify.send'),(2,'content.manage'),(2,'analytics.view'),(2,'analytics.export'),(2,'reminders.view'),
 (3,'dashboard.view'),(3,'users.view'),(3,'support.manage'),
 (4,'dashboard.view'),(4,'analytics.view'),(4,'analytics.export');
INSERT INTO subscription_plans (code,name,price,currency,duration_days,trial_days,limits_json,sort_order) VALUES
 ('free','Free',0,'UZS',0,0,'{"max_goals":3,"max_habits":3,"max_reminders":5,"full_stats":0,"finance_reports":0,"export_data":0}',1),
 ('monthly','Monthly Premium',49000,'UZS',30,3,'{"max_goals":-1,"max_habits":-1,"max_reminders":-1,"full_stats":1,"finance_reports":1,"export_data":1}',2),
 ('yearly','Yearly Premium',490000,'UZS',365,3,'{"max_goals":-1,"max_habits":-1,"max_reminders":-1,"full_stats":1,"finance_reports":1,"export_data":1}',3),
 ('custom','Custom Premium',0,'UZS',0,0,'{"max_goals":-1,"max_habits":-1,"max_reminders":-1,"full_stats":1,"finance_reports":1,"export_data":1}',4);
INSERT INTO level_rules (name,min_xp,icon) VALUES ('Base Camp',0,'⛺'),('Camp I',500,'🥾'),('Camp II',1500,'🧗'),('Camp III',3500,'🏔️'),('Camp IV',7000,'❄️'),('Summit',12000,'🚩');
INSERT INTO xp_rules (action_type,label,xp) VALUES ('task','Vazifa bajarilganda',10),('habit','Odat bajarilganda',5),('goal','Maqsad yakunlanganda',100),('daily_plan','Kunlik reja bajarilganda',25),('weekly_result','Haftalik natijaga erishilganda',75);
INSERT INTO content_pages (slug,title,body) VALUES ('help','Yordam',''),('terms','Foydalanish qoidalari',''),('privacy','Maxfiylik siyosati','');
INSERT INTO system_settings (k,v) VALUES
 ('platform_name','INTIZOM OS'),('logo_url',''),('default_language','uz'),('timezone','Asia/Tashkent'),('bot_username',''),
 ('support_contact',''),('currency','UZS'),('session_minutes','30'),('trial_days','3'),
 ('reminder_max_per_user','20'),('queue_rate_per_sec','20'),('queue_max_attempts','3'),('premium_warn_days','3');
