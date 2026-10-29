<?php
require __DIR__ . '/includes/bootstrap.php';

$id = (int) ($_GET['id'] ?? 0);
$st = db()->prepare('SELECT p.*, u.name AS author FROM blog_posts p LEFT JOIN users u ON u.id = p.author_id WHERE p.id = ?');
$st->execute([$id]);
$post = $st->fetch();

if (!$post) {
    http_response_code(404);
    flash('error', 'Sorry, that article was not found.');
    redirect('blog.php');
}

$me = current_user();
$commentError = '';
$commentText = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = post('action');

    if ($action === 'comment') {
        if (!$me) {
            flash('info', 'Please log in to comment.');
            redirect('login.php?next=' . urlencode('post.php?id=' . $id));
        }
        $commentText = post('body');
        $len = mb_strlen($commentText);
        if ($len < 2) {
            $commentError = 'Please write a comment first.';
        } elseif ($len > 2000) {
            $commentError = 'Comments can be up to 2000 characters (yours is ' . $len . ').';
        } elseif (!empty($_SESSION['last_comment']) && $_SESSION['last_comment'] > time() - 15) {
            $commentError = 'Please wait a few seconds before posting another comment.';
        } else {
            db()->prepare('INSERT INTO blog_comments (post_id, user_id, body) VALUES (?, ?, ?)')
                ->execute([$id, $me['id'], $commentText]);
            $_SESSION['last_comment'] = time();
            flash('success', 'Thanks! Your comment has been posted.');
            redirect('post.php?id=' . $id . '#comments');
        }
    }

    if ($action === 'delete_comment' && $me) {
        $cid = (int) ($_POST['comment_id'] ?? 0);
        // Admins can delete any comment; users can delete their own.
        if (is_admin()) {
            db()->prepare('DELETE FROM blog_comments WHERE id = ? AND post_id = ?')->execute([$cid, $id]);
        } else {
            db()->prepare('DELETE FROM blog_comments WHERE id = ? AND post_id = ? AND user_id = ?')->execute([$cid, $id, $me['id']]);
        }
        flash('info', 'Comment deleted.');
        redirect('post.php?id=' . $id . '#comments');
    }
}

$cs = db()->prepare('SELECT c.id, c.body, c.created_at, c.user_id, u.name, u.role FROM blog_comments c JOIN users u ON u.id = c.user_id WHERE c.post_id = ? ORDER BY c.created_at ASC, c.id ASC');
$cs->execute([$id]);
$comments = $cs->fetchAll();

$more = db()->prepare('SELECT id, title, category FROM blog_posts WHERE id <> ? ORDER BY created_at DESC, id DESC LIMIT 3');
$more->execute([$id]);
$others = $more->fetchAll();

$pageTitle = $post['title'] . ' | CareFirst Blog';
$pageDesc  = excerpt($post['description'], 155);
$navActive = 'blog.php';
require __DIR__ . '/includes/header.php';
?>
<section class="band alt post-page">
  <div class="wrap posts">
    <p class="crumbs"><a href="index.html">Home</a> / <a href="blog.php">Blog</a> / <?= e($post['category']) ?></p>
    <article class="post">
      <header><span class="post-tag"><?= e($post['category']) ?></span><h1><?= e($post['title']) ?></h1>
        <p class="post-meta"><?= e($post['author'] ?: 'CareFirst Team') ?> · <?= e(nice_date($post['created_at'])) ?> · <a href="#comments"><?= count($comments) ?> comment<?= count($comments) === 1 ? '' : 's' ?></a></p>
<?php if (is_admin()): ?>
        <p class="admin-tools"><a href="admin.php?edit=<?= (int) $post['id'] ?>">Edit this post</a></p>
<?php endif; ?>
      </header>
<?php if ($post['image']): ?>
      <figure class="post-hero"><img src="<?= e($post['image']) ?>" alt=""></figure>
<?php endif; ?>
      <div class="post-body">
<?= format_post($post['description']) ?>
      </div>
      <p class="post-note">This article is general information, not medical advice. Always follow the patient's doctor's instructions.</p>
    </article>

    <section class="comments" id="comments" aria-labelledby="c-title">
      <h2 id="c-title">Comments <span>(<?= count($comments) ?>)</span></h2>
<?php if (!$comments): ?>
      <p class="empty">No comments yet. Be the first to share your thoughts or ask a question.</p>
<?php endif; ?>
<?php foreach ($comments as $c): ?>
      <div class="comment" id="c<?= (int) $c['id'] ?>">
        <span class="avatar" aria-hidden="true"><?= e(mb_strtoupper(mb_substr($c['name'], 0, 1))) ?></span>
        <div>
          <p class="c-head"><b><?= e($c['name']) ?></b><?php if ($c['role'] === 'admin'): ?> <span class="c-badge">CareFirst</span><?php endif; ?> <time datetime="<?= e(date('c', strtotime($c['created_at']))) ?>"><?= e(date('j M Y, g:i a', strtotime($c['created_at']))) ?></time></p>
          <p class="c-body"><?= nl2br(e($c['body'])) ?></p>
<?php if ($me && (is_admin() || (int) $c['user_id'] === (int) $me['id'])): ?>
          <form method="post" class="c-del" onsubmit="return confirm('Delete this comment?')">
            <?= csrf_field() ?><input type="hidden" name="action" value="delete_comment"><input type="hidden" name="comment_id" value="<?= (int) $c['id'] ?>">
            <button type="submit">Delete</button>
          </form>
<?php endif; ?>
        </div>
      </div>
<?php endforeach; ?>

<?php if ($me): ?>
      <form class="cform server comment-form" method="post" action="post.php?id=<?= (int) $id ?>#comments">
        <?= csrf_field() ?><input type="hidden" name="action" value="comment">
        <div class="field full"><label for="c-body">Leave a comment as <?= e($me['name']) ?></label>
          <textarea id="c-body" name="body" maxlength="2000" required placeholder="Share your experience or ask a question…"<?= $commentError ? ' class="invalid"' : '' ?>><?= e($commentText) ?></textarea>
          <small class="ferr"><?= e($commentError) ?></small></div>
        <div class="submit"><button class="btn solid" type="submit">Post comment</button><span class="hint">Please don't share private medical details.</span></div>
      </form>
<?php else: ?>
      <div class="comment-login">
        <p><b>Want to comment?</b> Log in or create a free account. We'll ask you to confirm your email first.</p>
        <div><a class="btn solid" href="login.php?next=<?= e(urlencode('post.php?id=' . $id)) ?>">Log in</a> <a class="btn outline" href="signup.php">Sign up</a></div>
      </div>
<?php endif; ?>
    </section>

<?php if ($others): ?>
    <section class="more-posts">
      <h2>More articles</h2>
      <ul>
<?php foreach ($others as $o): ?>
        <li><a href="post.php?id=<?= (int) $o['id'] ?>"><span class="post-tag"><?= e($o['category']) ?></span><?= e($o['title']) ?></a></li>
<?php endforeach; ?>
      </ul>
      <a class="post-top" href="blog.php">← Back to all articles</a>
    </section>
<?php endif; ?>
  </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
