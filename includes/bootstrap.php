<?php
// Shared setup for every PHP page: settings, session, database and helpers.
require_once __DIR__ . '/../config.php';

ini_set('display_errors', '0');
error_reporting(E_ALL);
date_default_timezone_set('Asia/Karachi');

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    ]);
    session_name('carefirst_session');
    session_start();
}

// ---------- Database ----------
function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        try {
            $pdo = new PDO(
                'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET time_zone = '+05:00'",
                ]
            );
        } catch (PDOException $e) {
            http_response_code(500);
            error_log('CareFirst DB connection failed: ' . $e->getMessage());
            exit('<p style="font-family:sans-serif;padding:24px">Database connection failed. '
                . 'Check the database settings in <b>config.php</b> and make sure you imported <b>database.sql</b> in phpMyAdmin.</p>');
        }
    }
    return $pdo;
}

// ---------- Small helpers ----------
function e(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function site_url(string $path = ''): string
{
    if (SITE_URL !== '') {
        $base = rtrim(SITE_URL, '/');
    } else {
        $https  = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
        $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $dir    = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
        $base   = ($https ? 'https' : 'http') . '://' . $host . $dir;
    }
    return $base . '/' . ltrim($path, '/');
}

function redirect(string $to): void
{
    header('Location: ' . $to);
    exit;
}

function flash(string $type, string $msg): void
{
    $_SESSION['flash'][] = ['type' => $type, 'msg' => $msg];
}

function take_flashes(): array
{
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

function post(string $key): string
{
    return trim((string) ($_POST[$key] ?? ''));
}

function is_admin_email(string $email): bool
{
    return in_array(strtolower($email), array_map('strtolower', ADMIN_EMAILS), true);
}

// ---------- CSRF protection for every form ----------
function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function csrf_check(): void
{
    $sent = (string) ($_POST['csrf'] ?? '');
    if (!hash_equals(csrf_token(), $sent)) {
        http_response_code(400);
        exit('<p style="font-family:sans-serif;padding:24px">Your session expired. Please go back, refresh the page and try again.</p>');
    }
}

// ---------- Logged-in user ----------
function current_user(): ?array
{
    static $user = false;
    if ($user === false) {
        $user = null;
        if (!empty($_SESSION['user_id'])) {
            $st = db()->prepare('SELECT id, name, email, role FROM users WHERE id = ? AND email_verified_at IS NOT NULL');
            $st->execute([$_SESSION['user_id']]);
            $user = $st->fetch() ?: null;
            if (!$user) {
                unset($_SESSION['user_id']);
            }
        }
    }
    return $user;
}

function is_admin(): bool
{
    $u = current_user();
    return $u && $u['role'] === 'admin';
}

function require_login(): array
{
    $u = current_user();
    if (!$u) {
        flash('info', 'Please log in first.');
        redirect('login.php?next=' . urlencode($_SERVER['REQUEST_URI'] ?? 'blog.php'));
    }
    return $u;
}

function require_admin(): array
{
    $u = require_login();
    if ($u['role'] !== 'admin') {
        http_response_code(403);
        flash('error', 'Only admins can open that page.');
        redirect('blog.php');
    }
    return $u;
}

// Only allow redirects back to pages on this site.
function safe_next(string $next, string $fallback = 'blog.php'): string
{
    if ($next === '' || preg_match('~^(https?:)?//~i', $next) || str_contains($next, "\n") || str_contains($next, '\\')) {
        return $fallback;
    }
    $base = basename(parse_url($next, PHP_URL_PATH) ?: '');
    $query = parse_url($next, PHP_URL_QUERY);
    if (!preg_match('/^[a-z0-9_-]+\.php$/i', $base)) {
        return $fallback;
    }
    return $base . ($query ? '?' . $query : '');
}

// ---------- Blog text formatting ----------
// Admins write plain text. Supported extras:
//   a blank line starts a new paragraph
//   "## Heading" makes a sub-heading
//   "- item" makes a green tick list
//   **bold** makes bold text
function format_post(string $text): string
{
    $text  = str_replace(["\r\n", "\r"], "\n", trim($text));
    $lines = explode("\n", $text);
    $html  = '';
    $para  = [];
    $list  = [];

    $inline = function (string $s): string {
        $s = e($s);
        return preg_replace('/\*\*(.+?)\*\*/s', '<b>$1</b>', $s);
    };
    $flushPara = function () use (&$para, &$html, $inline) {
        if ($para) {
            $html .= '<p>' . implode('<br>', array_map($inline, $para)) . "</p>\n";
            $para = [];
        }
    };
    $flushList = function () use (&$list, &$html, $inline) {
        if ($list) {
            $html .= '<ul class="checks">' . implode('', array_map(fn($li) => '<li>' . $inline($li) . '</li>', $list)) . "</ul>\n";
            $list = [];
        }
    };

    foreach ($lines as $line) {
        $t = trim($line);
        if ($t === '') {
            $flushPara();
            $flushList();
        } elseif (str_starts_with($t, '## ')) {
            $flushPara();
            $flushList();
            $html .= '<h3>' . $inline(substr($t, 3)) . "</h3>\n";
        } elseif (preg_match('/^[-*•]\s+(.*)$/u', $t, $m)) {
            $flushPara();
            $list[] = $m[1];
        } else {
            $flushList();
            $para[] = $t;
        }
    }
    $flushPara();
    $flushList();
    return $html;
}

// Short plain-text summary for blog cards.
function excerpt(string $text, int $len = 150): string
{
    $plain = preg_replace('/^(##\s+|[-*•]\s+)/m', '', $text);
    $plain = str_replace('**', '', $plain);
    $plain = trim(preg_replace('/\s+/', ' ', $plain));
    if (mb_strlen($plain) <= $len) {
        return $plain;
    }
    $cut = mb_substr($plain, 0, $len);
    $cut = preg_replace('/\s+\S*$/u', '', $cut);
    return rtrim($cut, ' ,.;:') . '…';
}

function nice_date(string $dt): string
{
    return date('j F Y', strtotime($dt));
}

// ---------- Website settings saved in the database (Admin > Email settings) ----------
function ensure_settings_table(): void
{
    static $done = false;
    if (!$done) {
        db()->exec('CREATE TABLE IF NOT EXISTS `settings` (
            `name` VARCHAR(64) NOT NULL PRIMARY KEY,
            `value` TEXT NULL,
            `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $done = true;
    }
}

function all_settings(bool $reload = false): array
{
    static $cache = null;
    if ($cache === null || $reload) {
        ensure_settings_table();
        $cache = db()->query('SELECT name, value FROM settings')->fetchAll(PDO::FETCH_KEY_PAIR);
    }
    return $cache;
}

function setting(string $name, $default = null)
{
    $all = all_settings();
    return array_key_exists($name, $all) ? $all[$name] : $default;
}

function save_setting(string $name, ?string $value): void
{
    ensure_settings_table();
    db()->prepare('INSERT INTO settings (name, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value = VALUES(value)')
        ->execute([$name, $value]);
    all_settings(true);
}

// Secret values (the email password) are stored encrypted. The key lives in storage/app.key,
// which is created automatically and is blocked from the web.
function app_key(): string
{
    $file = __DIR__ . '/../storage/app.key';
    if (!is_file($file)) {
        if (!is_dir(dirname($file))) {
            mkdir(dirname($file), 0775, true);
        }
        file_put_contents($file, base64_encode(random_bytes(32)), LOCK_EX);
        @chmod($file, 0600);
    }
    return base64_decode(trim((string) file_get_contents($file)));
}

function encrypt_secret(string $plain): string
{
    if (!function_exists('openssl_encrypt')) {
        return 'plain:' . base64_encode($plain);
    }
    $iv  = random_bytes(12);
    $tag = '';
    $enc = openssl_encrypt($plain, 'aes-256-gcm', app_key(), OPENSSL_RAW_DATA, $iv, $tag);
    return 'gcm:' . base64_encode($iv . $tag . $enc);
}

function decrypt_secret(?string $stored): string
{
    if (!$stored) {
        return '';
    }
    if (str_starts_with($stored, 'plain:')) {
        return (string) base64_decode(substr($stored, 6));
    }
    if (str_starts_with($stored, 'gcm:') && function_exists('openssl_decrypt')) {
        $raw = base64_decode(substr($stored, 4));
        $out = openssl_decrypt(substr($raw, 28), 'aes-256-gcm', app_key(), OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16));
        return $out === false ? '' : $out;
    }
    return '';
}

/** Email settings: from the Settings page if saved there, otherwise from config.php. */
function mail_settings(): array
{
    $fromDb = setting('mail_mode') !== null;
    $s = [
        'mode'      => $fromDb ? setting('mail_mode') : MAIL_MODE,
        'host'      => $fromDb ? setting('smtp_host', '') : SMTP_HOST,
        'port'      => (int) ($fromDb ? setting('smtp_port', 587) : SMTP_PORT),
        'secure'    => $fromDb ? setting('smtp_secure', 'tls') : SMTP_SECURE,
        'user'      => $fromDb ? setting('smtp_user', '') : SMTP_USER,
        'pass'      => $fromDb ? decrypt_secret(setting('smtp_pass')) : SMTP_PASS,
        'from'      => $fromDb ? setting('mail_from', '') : MAIL_FROM,
        'from_name' => $fromDb ? setting('mail_from_name', 'CareFirst Home Nursing') : MAIL_FROM_NAME,
    ];
    $s['pass'] = str_replace(' ', '', $s['pass']);
    $s['from'] = $s['from'] ?: $s['user'];
    $s['ready'] = $s['mode'] === 'smtp' && $s['host'] && $s['user'] && $s['pass']
        && $s['pass'] !== 'your-16-letter-app-password' && $s['pass'] !== 'your16letterapppassword';
    // Real sending is chosen but not filled in yet: save emails to the log so nothing is lost.
    $s['effective'] = $s['ready'] ? 'smtp' : 'log';
    return $s;
}

// ---------- First-time setup ----------
// Until an admin exists, the first person to sign up becomes the admin straight away
// (no email needed, because email is set up from the admin Settings page).
function has_admin(): bool
{
    return (bool) db()->query("SELECT 1 FROM users WHERE role = 'admin' AND email_verified_at IS NOT NULL LIMIT 1")->fetchColumn();
}

function make_first_admin(int $userId): void
{
    db()->prepare("UPDATE users SET role = 'admin', email_verified_at = NOW(), verify_token_hash = NULL, verify_expires_at = NULL WHERE id = ?")
        ->execute([$userId]);
}
