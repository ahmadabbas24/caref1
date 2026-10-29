<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/mailer.php';

$email = strtolower(trim((string) ($_POST['email'] ?? $_GET['email'] ?? '')));
$done = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Enter a valid email address.';
    } elseif (!empty($_SESSION['last_resend']) && $_SESSION['last_resend'] > time() - 60) {
        $error = 'Please wait a minute before asking for another email.';
    } else {
        $_SESSION['last_resend'] = time();
        $st = db()->prepare('SELECT id, name FROM users WHERE email = ? AND email_verified_at IS NULL');
        $st->execute([$email]);
        if ($user = $st->fetch()) {
            send_verification_email((int) $user['id'], $email, $user['name']);
        }
        // Same message either way, so nobody can use this page to check who has an account.
        $done = true;
    }
}

$pageTitle = 'Resend confirmation | CareFirst Home Nursing';
require __DIR__ . '/includes/header.php';
?>
<section class="band alt">
  <div class="wrap auth-wrap">
<?php if ($done): ?>
    <div class="cform server auth-done">
      <h3>Email sent</h3>
      <p>If <b><?= e($email) ?></b> has an account waiting for confirmation, a new link is on its way. Check your inbox and Spam folder.</p>
      <div class="submit"><a class="btn outline" href="login.php">Back to log in</a></div>
    </div>
<?php else: ?>
    <form class="cform server" method="post" action="resend.php" novalidate>
      <h3>Resend confirmation email</h3>
      <?= csrf_field() ?>
<?php if ($error): ?><p class="err full" role="alert"><?= e($error) ?></p><?php endif; ?>
      <div class="field full"><label for="r-email">Email you signed up with</label>
        <input id="r-email" name="email" type="email" value="<?= e($email) ?>" placeholder="you@example.com" autocomplete="email" required></div>
      <div class="submit"><button class="btn solid" type="submit">Send link</button><span class="hint"><a href="login.php">Back to log in</a></span></div>
    </form>
<?php endif; ?>
  </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
