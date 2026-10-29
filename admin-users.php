<?php
// Admin > Users: see everyone who signed up, make admins, confirm or delete accounts.
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/mailer.php';
$admin = require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $id     = (int) ($_POST['id'] ?? 0);
    $action = post('action');
    $back   = 'admin-users.php' . (post('q') !== '' ? '?q=' . urlencode(post('q')) : '');

    $st = db()->prepare('SELECT id, name, email, role, email_verified_at FROM users WHERE id = ?');
    $st->execute([$id]);
    $u = $st->fetch();

    if (!$u) {
        flash('error', 'That user no longer exists.');
        redirect($back);
    }
    $self = (int) $u['id'] === (int) $admin['id'];

    switch ($action) {
        case 'make_admin':
            if (!$u['email_verified_at']) {
                db()->prepare('UPDATE users SET email_verified_at = NOW(), verify_token_hash = NULL, verify_expires_at = NULL WHERE id = ?')->execute([$id]);
            }
            db()->prepare("UPDATE users SET role = 'admin' WHERE id = ?")->execute([$id]);
            flash('success', $u['name'] . ' is now an admin.');
            break;
        case 'remove_admin':
            if ($self) {
                flash('error', 'You cannot remove your own admin access. Ask another admin to do it.');
            } else {
                db()->prepare("UPDATE users SET role = 'user' WHERE id = ?")->execute([$id]);
                flash('success', $u['name'] . ' is now a normal user.');
            }
            break;
        case 'confirm':
            db()->prepare('UPDATE users SET email_verified_at = NOW(), verify_token_hash = NULL, verify_expires_at = NULL WHERE id = ?')->execute([$id]);
            flash('success', $u['name'] . "'s account is confirmed. They can log in now.");
            break;
        case 'resend':
            if ($u['email_verified_at']) {
                flash('info', 'This account is already confirmed.');
            } elseif (send_verification_email((int) $u['id'], $u['email'], $u['name'])) {
                flash('success', mail_settings()['effective'] === 'smtp'
                    ? 'A new confirmation email was sent to ' . $u['email'] . '.'
                    : 'Test mode: the confirmation email was saved in storage/mail-log.html.');
            } else {
                flash('error', 'Could not send the email. ' . $GLOBALS['mail_error'] . ' Check Email settings.');
            }
            break;
        case 'delete':
            if ($self) {
                flash('error', 'You cannot delete your own account here.');
            } else {
                db()->prepare('DELETE FROM users WHERE id = ?')->execute([$id]);
                flash('info', $u['name'] . "'s account and comments were deleted.");
            }
            break;
    }
    redirect($back);
}

$q = trim((string) ($_GET['q'] ?? ''));
$sql = 'SELECT u.id, u.name, u.email, u.role, u.email_verified_at, u.created_at,
               (SELECT COUNT(*) FROM blog_comments c WHERE c.user_id = u.id) AS comments
        FROM users u';
$args = [];
if ($q !== '') {
    $sql .= ' WHERE u.name LIKE ? OR u.email LIKE ?';
    $args = ['%' . $q . '%', '%' . $q . '%'];
}
$sql .= " ORDER BY (u.role = 'admin') DESC, u.created_at DESC";
$st = db()->prepare($sql);
$st->execute($args);
$users = $st->fetchAll();

$counts = db()->query("SELECT COUNT(*) total, SUM(role = 'admin') admins, SUM(email_verified_at IS NULL) waiting FROM users")->fetch();

$pageTitle = 'Admin: users | CareFirst';
$navActive = 'admin.php';
$adminTab  = 'users';
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/admin-tabs.php';

function user_action(int $id, string $action, string $label, string $q, string $class = '', string $confirm = ''): string
{
    return '<form method="post" action="admin-users.php"' . ($confirm ? ' onsubmit="return confirm(\'' . e($confirm) . '\')"' : '') . '>'
        . csrf_field()
        . '<input type="hidden" name="id" value="' . $id . '"><input type="hidden" name="action" value="' . e($action) . '">'
        . '<input type="hidden" name="q" value="' . e($q) . '">'
        . '<button type="submit" class="' . e($class) . '">' . e($label) . '</button></form>';
}
?>
<section class="band alt">
  <div class="wrap">
    <div class="users-head">
      <div class="user-stats">
        <span><b><?= (int) $counts['total'] ?></b> users</span>
        <span><b><?= (int) $counts['admins'] ?></b> admins</span>
        <span><b><?= (int) $counts['waiting'] ?></b> waiting for email confirmation</span>
      </div>
      <form class="user-search" method="get" action="admin-users.php">
        <label class="sr" for="u-q">Search users</label>
        <input id="u-q" name="q" type="search" value="<?= e($q) ?>" placeholder="Search name or email">
        <button class="btn solid" type="submit">Search</button>
      </form>
    </div>

<?php if (!$users): ?>
    <p class="empty"><?= $q !== '' ? 'No users match "' . e($q) . '".' : 'No one has signed up yet.' ?></p>
<?php else: ?>
    <div class="table-wrap">
      <table class="users-table">
        <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th>Joined</th><th>Comments</th><th>Actions</th></tr></thead>
        <tbody>
<?php foreach ($users as $u): $self = (int) $u['id'] === (int) $admin['id']; ?>
          <tr>
            <td data-label="Name"><b><?= e($u['name']) ?></b><?= $self ? ' <small>(you)</small>' : '' ?></td>
            <td data-label="Email"><?= e($u['email']) ?></td>
            <td data-label="Role"><span class="role role-<?= e($u['role']) ?>"><?= $u['role'] === 'admin' ? 'Admin' : 'User' ?></span></td>
            <td data-label="Status"><?= $u['email_verified_at'] ? '<span class="st ok">Confirmed</span>' : '<span class="st wait">Waiting</span>' ?></td>
            <td data-label="Joined"><?= e(date('j M Y', strtotime($u['created_at']))) ?></td>
            <td data-label="Comments"><?= (int) $u['comments'] ?></td>
            <td data-label="Actions"><div class="u-actions">
<?php if ($u['role'] === 'admin'): ?>
              <?= $self ? '' : user_action((int) $u['id'], 'remove_admin', 'Remove admin', $q) ?>
<?php else: ?>
              <?= user_action((int) $u['id'], 'make_admin', 'Make admin', $q, '', 'Give ' . $u['name'] . ' full admin access?') ?>
<?php endif; ?>
<?php if (!$u['email_verified_at']): ?>
              <?= user_action((int) $u['id'], 'confirm', 'Confirm now', $q) ?>
              <?= user_action((int) $u['id'], 'resend', 'Resend email', $q) ?>
<?php endif; ?>
              <?= $self ? '' : user_action((int) $u['id'], 'delete', 'Delete', $q, 'danger', 'Delete ' . $u['name'] . ' and all their comments?') ?>
            </div></td>
          </tr>
<?php endforeach; ?>
        </tbody>
      </table>
    </div>
<?php endif; ?>
  </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
