<?php
require __DIR__ . '/includes/bootstrap.php';

$perPage = 9;
$page    = max(1, (int) ($_GET['page'] ?? 1));
$total   = (int) db()->query('SELECT COUNT(*) FROM blog_posts')->fetchColumn();
$pages   = max(1, (int) ceil($total / $perPage));
$page    = min($page, $pages);

$st = db()->prepare(
    'SELECT p.id, p.title, p.category, p.description, p.image, p.created_at,
            (SELECT COUNT(*) FROM blog_comments c WHERE c.post_id = p.id) AS comments
     FROM blog_posts p
     ORDER BY p.created_at DESC, p.id DESC
     LIMIT ? OFFSET ?'
);
$st->bindValue(1, $perPage, PDO::PARAM_INT);
$st->bindValue(2, ($page - 1) * $perPage, PDO::PARAM_INT);
$st->execute();
$posts = $st->fetchAll();

$pageTitle = 'Blog | CareFirst Home Nursing';
$pageDesc  = 'Health tips and care guides for families caring for a loved one at home.';
$navActive = 'blog.php';
require __DIR__ . '/includes/header.php';
?>
<section class="phero compact">
  <div class="wrap">
    <div class="copy">
      <p class="crumbs in d1"><a href="index.html">Home</a> / Blog</p>
      <span class="eyebrow in d1"><i></i>Health tips & updates</span>
      <h1 class="in d2">CareFirst <span class="green">blog</span></h1>
      <div class="in d3"><svg class="ecg" viewBox="0 0 440 44" aria-hidden="true"><path class="base" d="M0 22 H440"/><path d="M0 22 H150 l10 -7 l8 14 l10 -27 l12 38 l10 -26 l8 8 H262 l8 -5 l6 10 l8 -5 H440"/></svg></div>
      <p class="lead in d3">Practical tips and guides for families caring for a loved one at home, from the CareFirst team.</p>
<?php if (is_admin()): ?>
      <div class="in d3"><a class="btn solid" href="admin.php">+ Write a new blog post</a></div>
<?php elseif (!current_user()): ?>
      <p class="in d3 auth-nudge"><a href="signup.php">Sign up</a> or <a href="login.php">log in</a> to join the conversation in the comments.</p>
<?php endif; ?>
    </div>
  </div>
</section>

<section class="band" id="posts">
  <div class="wrap">
    <div class="head reveal"><span class="pill">Latest blogs</span><h2>Care tips for <span class="green">families</span></h2></div>
<?php if (!$posts): ?>
    <p class="empty">No articles yet. Please check back soon.</p>
<?php else: ?>
    <div class="post-grid">
<?php foreach ($posts as $p): ?>
      <a class="post-card reveal<?= $p['image'] ? ' has-img' : '' ?>" href="post.php?id=<?= (int) $p['id'] ?>">
<?php if ($p['image']): ?>
        <span class="post-thumb"><img src="<?= e($p['image']) ?>" alt="" loading="lazy"></span>
<?php endif; ?>
        <span class="post-tag"><?= e($p['category']) ?></span>
        <h3><?= e($p['title']) ?></h3>
        <p><?= e(excerpt($p['description'])) ?></p>
        <span class="post-foot"><span class="post-more">Read article →</span><span class="post-date"><?= e(nice_date($p['created_at'])) ?> · <?= (int) $p['comments'] ?> comment<?= $p['comments'] == 1 ? '' : 's' ?></span></span>
      </a>
<?php endforeach; ?>
    </div>
<?php if ($pages > 1): ?>
    <nav class="pager" aria-label="Blog pages">
<?php for ($i = 1; $i <= $pages; $i++): ?>
      <a href="blog.php?page=<?= $i ?>#posts"<?= $i === $page ? ' aria-current="page"' : '' ?>><?= $i ?></a>
<?php endfor; ?>
    </nav>
<?php endif; ?>
<?php endif; ?>
  </div>
</section>

<section class="ctaband"><div class="wrap">
  <div class="reveal"><h2>Need help caring at home?</h2><p>Our nurses and caregivers can support your family. Book a visit today.</p></div>
  <a class="btn solid" href="appointment.html">Book Appointment</a>
</div></section>
<?php require __DIR__ . '/includes/footer.php'; ?>
