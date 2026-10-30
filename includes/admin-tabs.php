<?php
// Tab bar shown at the top of every admin page. Set $adminTab before including.
$adminTab = $adminTab ?? 'posts';
$tabs = [
    'posts'    => ['admin.php', 'Blog posts'],
    'users'    => ['admin-users.php', 'Users'],
    'settings' => ['admin-settings.php', 'Email settings'],
];
$mailState = mail_settings();
?>
<nav class="admin-tabs" aria-label="Admin sections"><div class="wrap">
<?php foreach ($tabs as $key => [$href, $label]): ?>
  <a href="<?= $href ?>"<?= $adminTab === $key ? ' aria-current="page"' : '' ?>><?= e($label) ?><?php if ($key === 'settings' && $mailState['effective'] !== 'smtp'): ?> <span class="dot-warn" title="Real emails are not switched on yet">!</span><?php endif; ?></a>
<?php endforeach; ?>
</div></nav>
<?php if ($adminTab !== 'settings' && $mailState['effective'] !== 'smtp'): ?>
<div class="flash flash-info"><div class="wrap">Real emails are not switched on yet, so confirmation emails are saved in storage/mail-log.html. <a href="admin-settings.php">Set up email now →</a></div></div>
<?php endif; ?>
