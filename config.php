<?php
// =====================================================================
//  CareFirst website settings. Edit these before going live.
// =====================================================================

// ---- Database (the same details you use in phpMyAdmin) ----
define('DB_HOST', 'localhost');
define('DB_NAME', 'carefirst');
define('DB_USER', 'root');      // XAMPP default user
define('DB_PASS', '');          // XAMPP default password is empty

// ---- Website address ----
// Leave empty to detect it automatically (works on XAMPP, e.g. http://localhost/carefirst-website).
// On a live server you can set it, e.g. 'https://carefirstpro.com'
define('SITE_URL', '');

// ---- Admin accounts ----
// You normally don't need to change this:
//  * the FIRST person who signs up on a new website becomes the admin automatically, and
//  * admins can make other users admins on the Admin > Users page.
// Emails listed here also become admins when they sign up (optional).
define('ADMIN_EMAILS', []);

// ---- Email sending (used for the "confirm your email" message) ----
// You normally don't need to change anything below: log in as admin and open
// Admin > Email settings to enter your Gmail and App Password on the website.
// The settings saved there are used instead of these.
// 'smtp' = send real emails through the SMTP account below.
// 'log'  = do not send; save each email into storage/mail-log.html instead (handy for testing on XAMPP).
define('MAIL_MODE', 'log');

// Gmail example: turn on 2-Step Verification for the Gmail account, then create an
// "App password" (Google Account > Security > App passwords) and paste it below.
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);              // 587 = STARTTLS, 465 = SSL
define('SMTP_SECURE', 'tls');          // 'tls' for 587, 'ssl' for 465
define('SMTP_USER', 'carefirstpro@gmail.com');
define('SMTP_PASS', 'your-16-letter-app-password');
define('MAIL_FROM', 'carefirstpro@gmail.com');
define('MAIL_FROM_NAME', 'CareFirst Home Nursing');

// ---- Blog image uploads ----
define('MAX_IMAGE_MB', 4);
