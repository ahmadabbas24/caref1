<?php
// The link in the confirmation email opens this page.
require __DIR__ . '/includes/bootstrap.php';

$token = (string) ($_GET['token'] ?? '');
$ok = false;
$expired = false;

if (preg_match('/^[a-f0-9]{64}$/', $token)) {
    $st = db()->prepare('SELECT id, name, email, role, verify_expires_at FROM users WHERE verify_token_hash = ? AND email_verified_at IS NULL');
    $st->execute([hash('sha256', $token)]);
    $user = $st->fetch();

    if ($user && strtotime($user['verify_expires_at']) < time()) {
        $expired = true;
    } elseif ($user) {
        $role = (is_admin_email($user['email']) || !has_admin()) ? 'admin' : $user['role'];
        db()->prepare('UPDATE users SET email_verified_at = NOW(), verify_token_hash = NULL, verify_expires_at = NULL, role = ? WHERE id = ?')
            ->execute([$role, $user['id']]);

        // Log them straight in.
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $user['id'];
        flash('success', 'Your email is confirmed and your account is ready. Welcome to CareFirst, ' . explode(' ', $user['name'])[0] . '!');
        redirect($role === 'admin' ? 'admin.php' : 'blog.php');
    }
}

$pageTitle = 'Confirm email | CareFirst Home Nursing';
require __DIR__ . '/includes/header.php';
?>
<section class="band alt">
  <div class="wrap auth-wrap">
    <div class="cform server auth-done">
      <h3><?= $expired ? 'This link has expired' : 'This link is not valid' ?></h3>
      <p><?= $expired
          ? 'Confirmation links work for 24 hours. Request a new one and click it from your inbox.'
          : 'The link may have been used already or copied incompletely. If you already confirmed your email, just log in.' ?></p>
      <div class="submit"><a class="btn solid" href="resend.php">Send a new link</a><a class="btn outline" href="login.php">Log in</a></div>
    </div>
  </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
