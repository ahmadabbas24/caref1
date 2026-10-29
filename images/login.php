<?php
require __DIR__ . '/includes/bootstrap.php';

$next = safe_next((string) ($_GET['next'] ?? $_POST['next'] ?? ''));
if (current_user()) {
    redirect($next);
}

$error = '';
$unverified = false;
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $email    = strtolower(post('email'));
    $password = (string) ($_POST['password'] ?? '');

    // Slow down password guessing: max 8 failed tries per 15 minutes in this browser session.
    $_SESSION['login_fails'] = array_filter($_SESSION['login_fails'] ?? [], fn($t) => $t > time() - 900);
    if (count($_SESSION['login_fails']) >= 8) {
        $error = 'Too many attempts. Please wait 15 minutes and try again.';
    } else {
        $st = db()->prepare('SELECT id, name, password_hash, email_verified_at FROM users WHERE email = ?');
        $st->execute([$email]);
        $user = $st->fetch();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            $_SESSION['login_fails'][] = time();
            $error = 'Email or password is incorrect.';
        } elseif (!$user['email_verified_at'] && !has_admin()) {
            // Website has no admin yet: the first person to log in correctly becomes the admin.
            make_first_admin((int) $user['id']);
            session_regenerate_id(true);
            $_SESSION['user_id'] = (int) $user['id'];
            unset($_SESSION['login_fails']);
            flash('success', 'Your account is now the website admin. Next, set up email so new users get their confirmation emails.');
            redirect('admin-settings.php');
        } elseif (!$user['email_verified_at']) {
            $unverified = true;
            $error = 'Please confirm your email first. We sent you a link when you signed up.';
        } else {
            if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
                db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
                    ->execute([password_hash($password, PASSWORD_DEFAULT), $user['id']]);
            }
            session_regenerate_id(true);
            $_SESSION['user_id'] = (int) $user['id'];
            unset($_SESSION['login_fails']);
            flash('success', 'Welcome back, ' . explode(' ', $user['name'])[0] . '!');
            redirect($next);
        }
    }
}

$pageTitle = 'Log in | CareFirst Home Nursing';
$navActive = 'login.php';
require __DIR__ . '/includes/header.php';
?>
<section class="phero compact">
  <div class="wrap">
    <div class="copy">
      <p class="crumbs in d1"><a href="index.html">Home</a> / Log in</p>
      <span class="eyebrow in d1"><i></i>Welcome back</span>
      <h1 class="in d2">Log in to <span class="green">CareFirst</span></h1>
      <p class="lead in d3">Log in to comment on our health articles.</p>
    </div>
  </div>
</section>

<section class="band alt">
  <div class="wrap auth-wrap">
    <form class="cform server" method="post" action="login.php" novalidate>
      <h3>Log in</h3>
      <?= csrf_field() ?>
      <input type="hidden" name="next" value="<?= e($next) ?>">
<?php if ($error): ?>
      <p class="err full" role="alert"><?= e($error) ?>
<?php if ($unverified): ?>
        <br><a href="resend.php?email=<?= e(urlencode($email)) ?>">Resend confirmation email</a>
<?php endif; ?>
      </p>
<?php endif; ?>
      <div class="field full"><label for="l-email">Email</label>
        <input id="l-email" name="email" type="email" value="<?= e($email) ?>" placeholder="you@example.com" autocomplete="email" required></div>
      <div class="field full"><label for="l-pass">Password</label>
        <input id="l-pass" name="password" type="password" placeholder="Your password" autocomplete="current-password" required></div>
      <div class="submit"><button class="btn solid" type="submit">Log in</button>
        <span class="hint">New here? <a href="signup.php">Create an account</a> · <a href="resend.php">Resend confirmation email</a></span></div>
    </form>
  </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
