<?php
// Admin > Email settings: enter the Gmail (or other) account the website sends emails from.
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/mailer.php';
$admin = require_admin();

$presets = [
    'gmail'   => ['label' => 'Gmail',              'host' => 'smtp.gmail.com',        'port' => 587, 'secure' => 'tls'],
    'outlook' => ['label' => 'Outlook / Hotmail',  'host' => 'smtp-mail.outlook.com', 'port' => 587, 'secure' => 'tls'],
    'yahoo'   => ['label' => 'Yahoo Mail',         'host' => 'smtp.mail.yahoo.com',   'port' => 465, 'secure' => 'ssl'],
    'custom'  => ['label' => 'Other / my hosting', 'host' => '',                      'port' => 587, 'secure' => 'tls'],
];

$errors = [];
$testResult = null;   // ['ok' => bool, 'msg' => string]
$cur = mail_settings();
$hasSavedPass = $cur['pass'] !== '' && !in_array($cur['pass'], ['your-16-letter-app-password', 'your16letterapppassword'], true);

$form = [
    'mode'      => $cur['mode'] === 'smtp' ? 'smtp' : 'log',
    'provider'  => setting('mail_provider', 'gmail'),
    'host'      => $cur['host'] ?: 'smtp.gmail.com',
    'port'      => $cur['port'] ?: 587,
    'secure'    => $cur['secure'] ?: 'tls',
    'user'      => $cur['user'] === 'carefirstpro@gmail.com' && !$hasSavedPass ? '' : $cur['user'],
    'from_name' => $cur['from_name'] ?: 'CareFirst Home Nursing',
    'from'      => $cur['from'] === $cur['user'] ? '' : $cur['from'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $form['mode']      = post('mode') === 'smtp' ? 'smtp' : 'log';
    $form['provider']  = array_key_exists(post('provider'), $presets) ? post('provider') : 'custom';
    $form['host']      = post('host');
    $form['port']      = (int) post('port');
    $form['secure']    = post('secure') === 'ssl' ? 'ssl' : 'tls';
    $form['user']      = strtolower(post('user'));
    $form['from_name'] = post('from_name') ?: 'CareFirst Home Nursing';
    $form['from']      = strtolower(post('from'));
    $newPass           = str_replace(' ', '', (string) ($_POST['pass'] ?? ''));

    if ($form['mode'] === 'smtp') {
        if (!preg_match('/^[a-z0-9.-]+\.[a-z]{2,}$/i', $form['host'])) {
            $errors['host'] = 'Enter the email server, e.g. smtp.gmail.com.';
        }
        if ($form['port'] < 1 || $form['port'] > 65535) {
            $errors['port'] = 'Enter a port, usually 587 or 465.';
        }
        if (!filter_var($form['user'], FILTER_VALIDATE_EMAIL)) {
            $errors['user'] = 'Enter the email address that will send the emails.';
        }
        if ($newPass === '' && !$hasSavedPass) {
            $errors['pass'] = 'Enter the App Password.';
        }
    }
    if ($form['from'] !== '' && !filter_var($form['from'], FILTER_VALIDATE_EMAIL)) {
        $errors['from'] = 'Enter a valid email, or leave it empty.';
    }
    if (mb_strlen($form['from_name']) > 80) {
        $errors['from_name'] = 'Use up to 80 characters.';
    }

    if (!$errors) {
        save_setting('mail_mode', $form['mode']);
        save_setting('mail_provider', $form['provider']);
        save_setting('smtp_host', $form['host']);
        save_setting('smtp_port', (string) ($form['port'] ?: 587));
        save_setting('smtp_secure', $form['secure']);
        save_setting('smtp_user', $form['user']);
        save_setting('mail_from', $form['from']);
        save_setting('mail_from_name', $form['from_name']);
        save_setting('mail_last_test', null);
        if ($newPass !== '') {
            save_setting('smtp_pass', encrypt_secret($newPass));
        } elseif (!$hasSavedPass) {
            save_setting('smtp_pass', null);
        }
        $cur = mail_settings();
        $hasSavedPass = $cur['pass'] !== '';

        if (post('action') === 'test') {
            $ok = send_mail(
                $admin['email'],
                $admin['name'],
                'Test email from your CareFirst website',
                '<div style="font-family:Arial,sans-serif;color:#14305e"><h2 style="color:#3b9441">It works!</h2><p>Your CareFirst website can send emails. New users will now receive their confirmation emails.</p></div>',
                "It works! Your CareFirst website can send emails."
            );
            if ($cur['effective'] === 'smtp') {
                save_setting('mail_last_test', ($ok ? 'ok' : 'fail') . '|' . date('j M Y, g:i a'));
            }
            if ($ok && $cur['effective'] === 'smtp') {
                $testResult = ['ok' => true, 'msg' => 'Test email sent to ' . $admin['email'] . '. Check your inbox (and Spam). Real emails are now switched on.'];
            } elseif ($ok) {
                $testResult = ['ok' => true, 'msg' => 'Test mode is on, so the test email was saved in storage/mail-log.html instead of being sent. Choose "Send real emails" to send to inboxes.'];
            } else {
                $testResult = ['ok' => false, 'msg' => 'The test email could not be sent. ' . $GLOBALS['mail_error']];
            }
        } else {
            flash('success', 'Email settings saved.');
            redirect('admin-settings.php');
        }
    }
}

$checks = [
    ['ok' => extension_loaded('openssl'), 'label' => 'PHP OpenSSL is on',
     'fix' => 'Open C:\xampp\php\php.ini, find ;extension=openssl, remove the ; at the start, save, then Stop and Start Apache in XAMPP.'],
    ['ok' => is_writable(__DIR__ . '/storage') || (!is_dir(__DIR__ . '/storage') && is_writable(__DIR__)), 'label' => 'storage folder is writable',
     'fix' => 'Make the storage folder writable by the web server.'],
    ['ok' => $cur['effective'] === 'smtp', 'label' => 'Real email sending is chosen and filled in',
     'fix' => 'Choose "Send real emails", fill in your email and App Password, then press "Save and send test email".'],
];
if ($cur['effective'] === 'smtp') {
    [$lastState, $lastWhen] = array_pad(explode('|', (string) setting('mail_last_test', ''), 2), 2, '');
    $checks[] = $lastState === 'ok'
        ? ['ok' => true, 'label' => 'Test email worked (' . $lastWhen . ')', 'fix' => '']
        : ['ok' => false, 'label' => $lastState === 'fail' ? 'Last test email failed (' . $lastWhen . ')' : 'No test email sent yet',
           'fix' => 'Press "Save and send test email" and check your inbox. If it fails, read the red message on the left.'];
}

$pageTitle = 'Admin: email settings | CareFirst';
$navActive = 'admin.php';
$adminTab  = 'settings';
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/admin-tabs.php';
?>
<section class="band alt">
  <div class="wrap form-wrap admin-wrap">
    <form class="cform server" method="post" action="admin-settings.php" novalidate autocomplete="off">
      <h3>Email settings</h3>
      <?= csrf_field() ?>
<?php if ($testResult): ?>
      <p class="full test-<?= $testResult['ok'] ? 'ok' : 'bad' ?>" role="status"><?= e($testResult['msg']) ?></p>
<?php endif; ?>
<?php if ($errors): ?><p class="err full" role="alert">Please fix the fields marked in red.</p><?php endif; ?>

      <fieldset class="full mode-pick">
        <legend>How should the website send emails?</legend>
        <label class="choice"><input type="radio" name="mode" value="smtp"<?= $form['mode'] === 'smtp' ? ' checked' : '' ?>>
          <span><b>Send real emails</b><small>Confirmation emails go to each user's inbox.</small></span></label>
        <label class="choice"><input type="radio" name="mode" value="log"<?= $form['mode'] === 'log' ? ' checked' : '' ?>>
          <span><b>Test mode</b><small>Nothing is sent. Emails are saved in storage/mail-log.html.</small></span></label>
      </fieldset>

      <div class="smtp-fields full" id="smtpFields">
        <div class="field full"><label for="m-provider">Email provider</label>
          <select id="m-provider" name="provider">
<?php foreach ($presets as $k => $p): ?>
            <option value="<?= $k ?>" data-host="<?= e($p['host']) ?>" data-port="<?= $p['port'] ?>" data-secure="<?= $p['secure'] ?>"<?= $form['provider'] === $k ? ' selected' : '' ?>><?= e($p['label']) ?></option>
<?php endforeach; ?>
          </select></div>

        <div class="field full"><label for="m-user">Email address that sends the emails</label>
          <input id="m-user" name="user" type="email" value="<?= e($form['user']) ?>" placeholder="e.g. carefirstpro@gmail.com" autocomplete="off"<?= isset($errors['user']) ? ' class="invalid"' : '' ?>>
          <small class="ferr"><?= e($errors['user'] ?? '') ?></small></div>

        <div class="field full"><label for="m-pass">App Password <?= $hasSavedPass ? '<span class="opt">(saved. Leave empty to keep it)</span>' : '' ?></label>
          <input id="m-pass" name="pass" type="password" value="" placeholder="<?= $hasSavedPass ? '•••••••••••••••• saved' : '16 letters, e.g. abcd efgh ijkl mnop' ?>" autocomplete="new-password"<?= isset($errors['pass']) ? ' class="invalid"' : '' ?>>
          <small class="ferr"><?= e($errors['pass'] ?? '') ?></small>
          <small class="fhelp">For Gmail this is <b>not</b> your normal password. See "How to get a Gmail App Password". Spaces are removed automatically. It is stored encrypted.</small></div>

        <div class="field"><label for="m-from-name">Sender name</label>
          <input id="m-from-name" name="from_name" type="text" maxlength="80" value="<?= e($form['from_name']) ?>"<?= isset($errors['from_name']) ? ' class="invalid"' : '' ?>>
          <small class="ferr"><?= e($errors['from_name'] ?? '') ?></small></div>
        <div class="field"><label for="m-from">Reply-from email <span class="opt">(optional)</span></label>
          <input id="m-from" name="from" type="email" value="<?= e($form['from']) ?>" placeholder="Same as above"<?= isset($errors['from']) ? ' class="invalid"' : '' ?>>
          <small class="ferr"><?= e($errors['from'] ?? '') ?></small></div>

        <details class="field full adv"<?= isset($errors['host']) || isset($errors['port']) || $form['provider'] === 'custom' ? ' open' : '' ?>>
          <summary>Advanced: server and port</summary>
          <div class="adv-grid">
            <div class="field"><label for="m-host">SMTP server</label>
              <input id="m-host" name="host" type="text" value="<?= e($form['host']) ?>"<?= isset($errors['host']) ? ' class="invalid"' : '' ?>>
              <small class="ferr"><?= e($errors['host'] ?? '') ?></small></div>
            <div class="field"><label for="m-port">Port</label>
              <input id="m-port" name="port" type="number" min="1" max="65535" value="<?= (int) $form['port'] ?>"<?= isset($errors['port']) ? ' class="invalid"' : '' ?>>
              <small class="ferr"><?= e($errors['port'] ?? '') ?></small></div>
            <div class="field"><label for="m-secure">Security</label>
              <select id="m-secure" name="secure"><option value="tls"<?= $form['secure'] === 'tls' ? ' selected' : '' ?>>TLS (port 587)</option><option value="ssl"<?= $form['secure'] === 'ssl' ? ' selected' : '' ?>>SSL (port 465)</option></select></div>
          </div>
        </details>
      </div>

      <div class="submit">
        <button class="btn solid" type="submit" name="action" value="test">Save and send test email</button>
        <button class="btn outline" type="submit" name="action" value="save">Save only</button>
        <span class="hint">The test email goes to <?= e($admin['email']) ?>.</span>
      </div>
    </form>

    <div class="side">
      <div class="admin-list">
        <h3>Status</h3>
        <ul class="status-list">
<?php foreach ($checks as $c): ?>
          <li class="<?= $c['ok'] ? 'ok' : 'bad' ?>"><b><?= $c['ok'] ? '✓' : '✗' ?> <?= e($c['label']) ?></b><?php if (!$c['ok']): ?><small><?= e($c['fix']) ?></small><?php endif; ?></li>
<?php endforeach; ?>
        </ul>
      </div>
      <div class="admin-list">
        <h3>How to get a Gmail App Password</h3>
        <ol class="howto">
          <li>Open <a href="https://myaccount.google.com/security" target="_blank" rel="noopener">myaccount.google.com/security</a> and sign in to the Gmail account that will send the emails.</li>
          <li>Turn on <b>2-Step Verification</b>.</li>
          <li>Open <a href="https://myaccount.google.com/apppasswords" target="_blank" rel="noopener">App passwords</a>, type the name <b>CareFirst</b> and press <b>Create</b>.</li>
          <li>Copy the 16-letter code, paste it in <b>App Password</b> here, and press <b>Save and send test email</b>.</li>
        </ol>
        <p class="fhelp">On localhost, the link inside confirmation emails starts with http://localhost, so open it on the same computer. Once the site is on real hosting, links work on any device.</p>
      </div>
    </div>
  </div>
</section>
<script>
(function () {
  var modes = document.querySelectorAll('input[name=mode]'), box = document.getElementById('smtpFields');
  function sync() { box.classList.toggle('off', document.querySelector('input[name=mode]:checked').value !== 'smtp'); }
  modes.forEach(function (m) { m.addEventListener('change', sync); }); sync();
  var prov = document.getElementById('m-provider');
  prov.addEventListener('change', function () {
    var o = prov.options[prov.selectedIndex];
    if (o.value === 'custom') { document.querySelector('details.adv').open = true; return; }
    document.getElementById('m-host').value = o.dataset.host;
    document.getElementById('m-port').value = o.dataset.port;
    document.getElementById('m-secure').value = o.dataset.secure;
  });
  document.getElementById('m-secure').addEventListener('change', function (e) {
    document.getElementById('m-port').value = e.target.value === 'ssl' ? 465 : 587;
  });
})();
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>
