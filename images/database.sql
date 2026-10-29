-- =====================================================================
--  CareFirst website database
--  Import this file in phpMyAdmin: Import tab > Choose file > Go.
--  It creates the `carefirst` database with users, blog posts, comments and settings,
--  and adds the three existing blog articles.
--  The users table starts empty: the first person who signs up becomes the admin.
-- =====================================================================

CREATE DATABASE IF NOT EXISTS `carefirst` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `carefirst`;

-- Website users (people who sign up). role = 'admin' can publish blog posts.
CREATE TABLE IF NOT EXISTS `users` (
  `id`                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`              VARCHAR(80)  NOT NULL,
  `email`             VARCHAR(190) NOT NULL,
  `password_hash`     VARCHAR(255) NOT NULL,
  `role`              ENUM('user','admin') NOT NULL DEFAULT 'user',
  `email_verified_at` DATETIME NULL DEFAULT NULL,
  `verify_token_hash` CHAR(64) NULL DEFAULT NULL,
  `verify_expires_at` DATETIME NULL DEFAULT NULL,
  `created_at`        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_email` (`email`),
  KEY `idx_verify_token` (`verify_token_hash`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Blog posts written by admins.
CREATE TABLE IF NOT EXISTS `blog_posts` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `title`       VARCHAR(200) NOT NULL,
  `category`    VARCHAR(60)  NOT NULL DEFAULT 'Health tips',
  `description` MEDIUMTEXT   NOT NULL,
  `image`       VARCHAR(255) NULL DEFAULT NULL,
  `author_id`   INT UNSIGNED NULL DEFAULT NULL,
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_created` (`created_at`),
  CONSTRAINT `fk_post_author` FOREIGN KEY (`author_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Comments by logged-in (email-confirmed) users.
CREATE TABLE IF NOT EXISTS `blog_comments` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `post_id`    INT UNSIGNED NOT NULL,
  `user_id`    INT UNSIGNED NOT NULL,
  `body`       TEXT NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_post` (`post_id`, `created_at`),
  CONSTRAINT `fk_comment_post` FOREIGN KEY (`post_id`) REFERENCES `blog_posts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_comment_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Website settings saved from the admin pages (for example the email account for sending emails).
-- The email password is stored encrypted.
CREATE TABLE IF NOT EXISTS `settings` (
  `name`       VARCHAR(64) NOT NULL,
  `value`      TEXT NULL,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- The three articles that were already on the website.
INSERT INTO `blog_posts` (`title`, `category`, `description`, `image`, `created_at`) VALUES
('12-hour or 24-hour care: which does your family need?', 'Planning care',
'Home care can be a few visits a week, a 12-hour shift, or round-the-clock support. The right choice depends on the patient''s condition and how much help the family can give.

## 12-hour care may be enough if:
- The patient sleeps through the night without needing help.
- A family member is home and able to help for the other 12 hours.
- Most care, like medicines, dressings and meals, happens during the day.

## 24-hour care may be better if:
- The patient can''t be left alone safely, or is at high risk of falls.
- They need help at night, for example with turning, toileting, oxygen or suction.
- Family members work or can''t stay awake through the night.

Our team can talk through the patient''s needs with you and suggest a care plan. You can always start with one option and change it later.',
'images/hiring.jpg', '2026-10-01 10:00:00'),

('Bringing a patient home from hospital: a checklist', 'Recovery',
'A smooth move home helps recovery and lowers the chance of going back to hospital. Before you leave the hospital, make sure you have the following.

## Before you leave the hospital
- **Discharge summary and prescriptions.** Keep them in one folder with the patient''s reports.
- **A clear medicine schedule.** Ask which medicines are new, which have stopped, and when each dose is due.
- **Follow-up appointments.** Note the date, doctor and any tests needed before the visit.
- **Warning signs.** Ask the doctor which symptoms mean you should call them or return to hospital.

## At home
- **Prepare the room.** Keep a clear path to the bed and bathroom, with good lighting at night.
- **Arrange equipment early.** A hospital bed, oxygen or a suction machine should be ready before the patient arrives.
- **Plan care support.** Decide who will help with medicines, dressings, meals and hygiene, and whether a nurse or attendant is needed.',
'images/equipment.jpg', '2026-10-02 10:00:00'),

('How to prevent bed sores in bedridden patients', 'Caregiving',
'Bed sores (pressure ulcers) form when constant pressure cuts off blood flow to the skin, usually over bony areas. Patients who stay in bed or a wheelchair for long periods are most at risk.

## Daily habits that help
- **Change position regularly.** Turn the patient at least every 2 hours in bed, or as their nurse or doctor advises.
- **Check the skin every day.** Look at the heels, hips, lower back, tailbone, elbows and back of the head for redness that doesn''t fade.
- **Keep skin clean and dry.** Change wet sheets and diapers quickly, and pat the skin dry gently.
- **Use pillows and soft support.** Pillows between the knees and under the calves keep pressure off bony points. An air mattress can also help.
- **Support good nutrition and fluids.** Healthy skin needs enough protein and water, as advised by the patient''s doctor.

**When to get help:** if you see an open wound, blistering, or red skin that stays red after pressure is removed, contact a nurse or doctor quickly. Early care makes healing much easier.',
'images/nurse.jpg', '2026-10-03 10:00:00');
