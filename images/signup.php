<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/mailer.php';

if (current_user()) {
    redirect('blog.php');
}

$errors = [];
$name = $email = '';
$sentTo = null;
$setupMode = !has_admin();   // no admin yet: this signup creates the admin account

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $name     = preg_replace('/\s+/', ' ', post('name'));
    $email    = strtolower(post('email'));
    $password = (string) ($_POST['password'] ?? '');
    $confirm  = (string) ($_POST['confirm'] ?? '');

    if (mb_strlen($name) < 3 || mb_strlen($name) > 80 || !preg_match("/^[\p{L} .'-]+$/u", $name)) {
        $errors['name'] = 'Please enter your name (letters only, at least 3).';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) {
        $errors['email'] = 'Enter a valid email, e.g. name@gmail.com.';
    }
    if (strlen($password) < 8) {
        $errors['password'] = 'Use at least 8 characters.';
    } elseif (!preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password)) {
        $errors['password'] = 'Use at least one letter and one number.';
    }
    if ($password !== $confirm) {
        $errors['confirm'] = 'The passwords do not match.';
    }

    if (!$errors) {
        $st = db()->prepare('SELECT id, name, email_verified_at FROM users WHERE email = ?');
        $st->execute([$email]);
        $existing = $st->fetch();

        if ($existing && $existing['email_verified_at']) {
            $errors['email'] = 'An account with this email already exists. Please log in instead.';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $role = is_admin_email($email) ? 'admin' : 'user';
            if ($existing) {
                // Signed up before but never confirmed: update details and send a fresh link.
                db()->prepare('UPDATE users SET name = ?, password_hash = ?, role = ? WHERE id = ?')
                    ->execute([$name, $hash, $role, $existing['id']]);
                $userId = (int) $existing['id'];
            } else {
                db()->prepare('INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, ?)')
                    ->execute([$name, $email, $hash, $role]);
                $userId = (int) db()->lastInsertId();
            }

            if ($setupMode) {
                // First account on a new website: make it the admin right away.
                make_first_admin($userId);
                session_regenerate_id(true);
                $_SESSION['user_id'] = $userId;
                flash('success', 'Your admin account is ready. Next, set up email so new users get their confirmation emails.');
                redirect('admin-settings.php');
            }

            if (send_verification_email($userId, $email, $name)) {
                $sentTo = $email;
            } else {
                $errors['form'] = 'Your account was created, but we could not send the confirmation email right now. '
                    . 'Please try "Resend confirmation email" on the login page in a few minutes.';
            }
        }
    }
}

$pageTitle = 'Sign up | CareFirst Home Nursing';
$navActive = 'signup.php';
require __DIR__ . '/includes/header.php';
?>
<section class="phero compact">
  <div class="wrap">
    <div class="copy">
      <p class="crumbs in d1"><a href="index.html">Home</a> / Sign up</p>
<?php if ($setupMode): ?>
      <span class="eyebrow in d1"><i></i>First-time setup</span>
      <h1 class="in d2">Create the <span class="green">admin account</span></h1>
      <p class="lead in d3">This website has no admin yet, so the account you create now becomes the admin. You can then set up email and publish blog posts.</p>
<?php else: ?>
      <span class="eyebrow in d1"><i></i>Join the CareFirst community</span>
      <h1 class="in d2">Create your <span class="green">account</span></h1>
      <p class="lead in d3">Sign up with your email to comment on our health articles and ask questions.</p>
<?php endif; ?>
    </div>
  </div>
</section>

<section class="band alt">
  <div class="wrap auth-wrap">
<?php if ($sentTo): ?>
    <div class="cform server auth-done">
      <div class="done-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg></div>
      <h3>Check your email</h3>
      <p>We sent a confirmation link to <b><?= e($sentTo) ?></b>. Open the email and click <b>Confirm my email</b> to finish signing up. The link works for 24 hours.</p>
      <p class="hint">Can't find it? Check your Spam or Promotions folder, or <a href="resend.php?email=<?= e(urlencode($sentTo)) ?>">send the email again</a>.</p>
<?php if (mail_settings()['effective'] === 'log'): ?>
      <p class="alert"><strong>Test mode:</strong> real emails are not switched on yet, so the confirmation email was saved in <code>storage/mail-log.html</code>. An admin can turn on real emails in <b>Admin &gt; Email settings</b>.</p>
<?php endif; ?>
    </div>
<?php else: ?>
    <form class="cform server" method="post" action="signup.php" novalidate>
      <h3><?= $setupMode ? 'Admin account' : 'Sign up' ?></h3>
      <?= csrf_field() ?>
<?php if (!empty($errors['form'])): ?>
      <p class="err full" role="alert"><?= e($errors['form']) ?></p>
<?php endif; ?>
      <div class="field full"><label for="s-name">Full name</label>
        <input id="s-name" name="name" type="text" value="<?= e($name) ?>" placeholder="e.g. Ali Raza" autocomplete="name" required maxlength="80"<?= isset($errors['name']) ? ' class="invalid"' : '' ?>>
        <small class="ferr"><?= e($errors['name'] ?? '') ?></small></div>
      <div class="field full"><label for="s-email">Email</label>
        <input id="s-email" name="email" type="email" value="<?= e($email) ?>" placeholder="you@example.com" autocomplete="email" required maxlength="190"<?= isset($errors['email']) ? ' class="invalid"' : '' ?>>
        <small class="ferr"><?= e($errors['email'] ?? '') ?></small></div>
      <div class="field"><label for="s-pass">Password</label>
        <input id="s-pass" name="password" type="password" placeholder="At least 8 characters" autocomplete="new-password" required minlength="8"<?= isset($errors['password']) ? ' class="invalid"' : '' ?>>
        <small class="ferr"><?= e($errors['password'] ?? '') ?></small></div>
      <div class="field"><label for="s-confirm">Confirm password</label>
        <input id="s-confirm" name="confirm" type="password" placeholder="Type it again" autocomplete="new-password" required<?= isset($errors['confirm']) ? ' class="invalid"' : '' ?>>
        <small class="ferr"><?= e($errors['confirm'] ?? '') ?></small></div>
      <div class="submit"><button class="btn solid" type="submit">Create account</button>
        <span class="hint">Already have an account? <a href="login.php">Log in</a></span></div>
    </form>
<?php endif; ?>
  </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
