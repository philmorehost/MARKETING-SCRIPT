-- Admin & Core Tables
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `team_id` int(11) DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `google_id` varchar(255) DEFAULT NULL,
  `avatar_url` varchar(255) DEFAULT NULL,
  `role` enum('admin','user','owner','member') NOT NULL DEFAULT 'user',
  `credit_balance` decimal(10,4) NOT NULL DEFAULT 0.0000,
  `status` enum('active','suspended','pending') NOT NULL DEFAULT 'pending',
  `first_login_wizard_complete` tinyint(1) NOT NULL DEFAULT 0,
  `team_role` enum('owner','admin','member') DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `teams` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `owner_user_id` int(11) NOT NULL,
  `team_name` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `transactions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `team_id` int(11) DEFAULT NULL,
  `type` enum('purchase','spend_verify','spend_email','spend_sms','spend_whatsapp','spend_landing_page','spend_ai','spend_social_post','spend_qr_code') NOT NULL,
  `gateway` varchar(50) DEFAULT NULL,
  `gateway_tx_id` varchar(255) DEFAULT NULL,
  `description` varchar(255) NOT NULL,
  `amount_usd` decimal(10,2) NOT NULL,
  `amount_credits` decimal(10,4) NOT NULL,
  `status` enum('completed','pending','failed') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `manual_payments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `credit_package_id` int(11) DEFAULT NULL,
  `credit_package_name` varchar(100) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `proof_path` varchar(255) NOT NULL,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `reviewed_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(255) NOT NULL,
  `setting_value` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `password_resets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `email` varchar(255) NOT NULL,
  `token` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Email Verification
CREATE TABLE `verification_jobs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `team_id` int(11) DEFAULT NULL,
  `job_name` varchar(255) NOT NULL,
  `total_emails` int(11) NOT NULL,
  `cost_in_credits` decimal(10,4) NOT NULL,
  `status` enum('queued','processing','completed','failed') NOT NULL DEFAULT 'queued',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `verification_queue` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `job_id` int(11) NOT NULL,
  `email_address` varchar(255) NOT NULL,
  `status` enum('pending','valid','invalid','unknown') NOT NULL DEFAULT 'pending',
  `processed_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Contacts
CREATE TABLE `contact_lists` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `team_id` int(11) DEFAULT NULL,
  `list_name` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `contacts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `team_id` int(11) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `first_name` varchar(255) DEFAULT NULL,
  `last_name` varchar(255) DEFAULT NULL,
  `phone_number` varchar(20) DEFAULT NULL,
  `custom_fields_json` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `contact_list_map` (
  `contact_id` int(11) NOT NULL,
  `list_id` int(11) NOT NULL,
  PRIMARY KEY (`contact_id`,`list_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Marketing Services (Email, SMS, WhatsApp)
CREATE TABLE `campaigns` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `team_id` int(11) DEFAULT NULL,
  `subject` varchar(255) NOT NULL,
  `html_content` text NOT NULL,
  `cost_in_credits` decimal(10,4) NOT NULL,
  `status` enum('draft','queued','sending','sent') NOT NULL DEFAULT 'draft',
  `scheduled_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `campaign_queue` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `campaign_id` int(11) NOT NULL,
  `contact_id` int(11) NOT NULL,
  `email_address` varchar(255) NOT NULL,
  `status` enum('queued','sent','failed','bounced') NOT NULL DEFAULT 'queued',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `hourly_email_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `campaign_id` int(11) NOT NULL,
  `sent_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `sms_campaigns` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `team_id` int(11) DEFAULT NULL,
  `sender_id` varchar(11) NOT NULL,
  `message_body` text NOT NULL,
  `list_ids_json` text NOT NULL,
  `total_pages` int(11) NOT NULL,
  `cost_in_credits` decimal(10,4) NOT NULL,
  `status` enum('queued','sending','sent','failed') NOT NULL DEFAULT 'queued',
  `scheduled_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `sms_queue` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sms_campaign_id` int(11) NOT NULL,
  `contact_id` int(11) NOT NULL,
  `phone_number` varchar(20) NOT NULL,
  `message_pages` int(11) NOT NULL,
  `status` enum('queued','sent','failed','delivered') NOT NULL DEFAULT 'queued',
  `api_message_id` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `whatsapp_campaigns` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `team_id` int(11) DEFAULT NULL,
  `template_name` varchar(255) NOT NULL,
  `template_params_json` text DEFAULT NULL,
  `list_ids_json` text NOT NULL,
  `cost_in_credits` decimal(10,4) NOT NULL,
  `status` enum('queued','sending','sent','failed') NOT NULL DEFAULT 'queued',
  `scheduled_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `whatsapp_queue` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `campaign_id` int(11) NOT NULL,
  `contact_id` int(11) NOT NULL,
  `phone_number` varchar(20) NOT NULL,
  `status` enum('queued','sent','delivered','read','failed') NOT NULL DEFAULT 'queued',
  `api_message_id` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- Other Features
CREATE TABLE `landing_pages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `team_id` int(11) DEFAULT NULL,
  `list_id` int(11) NOT NULL,
  `page_slug` varchar(255) NOT NULL,
  `headline` varchar(255) NOT NULL,
  `content` text NOT NULL,
  `status` enum('published','draft') NOT NULL DEFAULT 'draft',
  `cost_in_credits` decimal(10,4) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `page_slug` (`page_slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `qr_codes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `team_id` int(11) DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `url` text NOT NULL,
  `image_path` varchar(255) NOT NULL,
  `cost_in_credits` decimal(10,4) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `social_posts_queue` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `team_id` int(11) DEFAULT NULL,
  `provider` enum('facebook','twitter','linkedin') NOT NULL,
  `account_id` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `image_url` varchar(255) DEFAULT NULL,
  `status` enum('queued','sent','failed') NOT NULL DEFAULT 'queued',
  `scheduled_at` datetime NOT NULL,
  `cost_in_credits` decimal(10,4) NOT NULL,
  `error_message` text DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `social_accounts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `team_id` int(11) DEFAULT NULL,
  `provider` enum('facebook','twitter','linkedin') NOT NULL,
  `account_name` varchar(255) NOT NULL,
  `account_id` varchar(255) NOT NULL,
  `access_token` text NOT NULL,
  `refresh_token` text DEFAULT NULL,
  `expires_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Support & CMS
CREATE TABLE `support_tickets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `team_id` int(11) DEFAULT NULL,
  `guest_name` varchar(100) DEFAULT NULL,
  `guest_email` varchar(100) DEFAULT NULL,
  `subject` varchar(255) NOT NULL,
  `status` enum('open','closed') NOT NULL DEFAULT 'open',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `support_ticket_replies` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ticket_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `message` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `cms_content` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `content_key` varchar(50) NOT NULL,
  `content_value` text NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `content_key` (`content_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `credit_packages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `credits` decimal(10,4) NOT NULL,
  `is_popular` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `testimonials` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `author_name` varchar(255) NOT NULL,
  `author_title` varchar(255) DEFAULT NULL,
  `quote` text NOT NULL,
  `star_rating` int(11) NOT NULL DEFAULT 5,
  `display_order` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `campaign_events` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `campaign_id` int(11) NOT NULL,
  `contact_id` int(11) NOT NULL,
  `event_type` enum('open','click','bounce') NOT NULL,
  `url_clicked` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
