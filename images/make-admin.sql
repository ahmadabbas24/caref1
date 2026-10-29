-- Creates (or resets) the CareFirst admin account.
-- Email:    ahmadabbastuts@gmail.com
-- Password: pakistan123
USE `carefirst`;
INSERT INTO `users` (`name`, `email`, `password_hash`, `role`, `email_verified_at`)
VALUES ('Ahmad Abbas', 'ahmadabbastuts@gmail.com',
        '$2y$10$VLkk4ENn54XHmxuqrnhLRO8O9bvtP9iny1kEEIlQmRjLloczWJA3S',
        'admin', NOW())
ON DUPLICATE KEY UPDATE
  `password_hash`     = VALUES(`password_hash`),
  `role`              = 'admin',
  `email_verified_at` = NOW(),
  `verify_token_hash` = NULL,
  `verify_expires_at` = NULL;
