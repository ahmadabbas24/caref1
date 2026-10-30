<?php
// Shared page top. Set $pageTitle, $pageDesc and $navActive before including.
$pageTitle = $pageTitle ?? 'CareFirst Home Nursing';
$pageDesc  = $pageDesc ?? 'CareFirst Home Nursing: quality care at your doorstep.';
$navActive = $navActive ?? '';
$me        = current_user();
$navItems  = [
    'index.html'     => 'Home',
    'services.html'  => 'Services',
    'equipment.html' => 'Medical Equipment',
    'why-us.html'    => 'Why Us',
    'blog.php'       => 'Blog',
    'hiring.html'    => "We're Hiring",
    'contact.html'   => 'Contact Us',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <title><?= e($pageTitle) ?></title>
  <meta name="description" content="<?= e($pageDesc) ?>">
  <link rel="icon" href="images/logo.jpg">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@600;700;800&family=Figtree:wght@400;500;600;700&family=Caveat:wght@600&display=swap">
  <link rel="stylesheet" href="css/style.css?v=4">
</head>
<body>
<header>
  <div class="wrap nav">
    <a class="brand" href="index.html" aria-label="CareFirst home">
      <img src="images/logo.jpg" alt="">
      <span><b>Care<span>First</span></b><small>Quality Care at Your Doorstep</small></span>
    </a>
    <button class="menu-btn" id="menuBtn" aria-label="Open menu" aria-expanded="false" aria-controls="links">
      <svg viewBox="0 0 24 24"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
    </button>
    <ul class="links" id="links">
<?php foreach ($navItems as $href => $label): ?>
      <li><a href="<?= $href ?>"<?= $navActive === $href ? ' aria-current="page"' : '' ?>><?= e($label) ?></a></li>
<?php endforeach; ?>
<?php if ($me && $me['role'] === 'admin'): ?>
      <li><a href="admin.php"<?= $navActive === 'admin.php' ? ' aria-current="page"' : '' ?>>Admin</a></li>
<?php endif; ?>
<?php if ($me): ?>
      <li><a class="acct" href="logout.php?t=<?= e(csrf_token()) ?>" title="Logged in as <?= e($me['email']) ?>">Log out</a></li>
<?php else: ?>
      <li><a class="acct" href="login.php"<?= in_array($navActive, ['login.php', 'signup.php'], true) ? ' aria-current="page"' : '' ?>>Log in</a></li>
<?php endif; ?>
      <li><a href="appointment.html" class="cta">Book Appointment</a></li>
    </ul>
  </div>
</header>

<main>
<?php foreach (take_flashes() as $f): ?>
<div class="flash flash-<?= e($f['type']) ?>" role="status"><div class="wrap"><?= e($f['msg']) ?></div></div>
<?php endforeach; ?>
