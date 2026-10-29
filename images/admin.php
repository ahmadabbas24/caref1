<?php
// Admin page: write, edit and delete blog posts.
require __DIR__ . '/includes/bootstrap.php';
$admin = require_admin();

$uploadDir = __DIR__ . '/uploads/blog';
$errors = [];
$editId = (int) ($_GET['edit'] ?? $_POST['id'] ?? 0);
$form = ['title' => '', 'category' => 'Health tips', 'description' => '', 'image' => null];

if ($editId) {
    $st = db()->prepare('SELECT * FROM blog_posts WHERE id = ?');
    $st->execute([$editId]);
    $existing = $st->fetch();
    if (!$existing) {
        flash('error', 'That post no longer exists.');
        redirect('admin.php');
    }
    $form = array_merge($form, $existing);
}

/** Saves the uploaded image and returns its web path, or null if no file was chosen. */
function save_upload(string $dir, array &$errors): ?string
{
    $f = $_FILES['image'] ?? null;
    if (!$f || $f['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($f['error'] === UPLOAD_ERR_INI_SIZE || $f['error'] === UPLOAD_ERR_FORM_SIZE || $f['size'] > MAX_IMAGE_MB * 1024 * 1024) {
        $errors['image'] = 'The image is too big. Please use one under ' . MAX_IMAGE_MB . ' MB.';
        return null;
    }
    if ($f['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name'])) {
        $errors['image'] = 'The image could not be uploaded. Please try again.';
        return null;
    }
    $types = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
    $mime  = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
    if (!isset($types[$mime]) || @getimagesize($f['tmp_name']) === false) {
        $errors['image'] = 'Please choose a JPG, PNG, WEBP or GIF image.';
        return null;
    }
    if (!is_dir($dir) && !mkdir($dir, 0775, true)) {
        $errors['image'] = 'The uploads/blog folder is missing and could not be created.';
        return null;
    }
    $name = date('Ymd') . '-' . bin2hex(random_bytes(8)) . '.' . $types[$mime];
    if (!move_uploaded_file($f['tmp_name'], $dir . '/' . $name)) {
        $errors['image'] = 'Could not save the image. Make sure the uploads/blog folder is writable.';
        return null;
    }
    return 'uploads/blog/' . $name;
}

function delete_upload(?string $path): void
{
    if ($path && str_starts_with($path, 'uploads/blog/')) {
        $full = __DIR__ . '/' . $path;
        if (is_file($full)) {
            @unlink($full);
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = post('action');

    if ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        $st = db()->prepare('SELECT image FROM blog_posts WHERE id = ?');
        $st->execute([$id]);
        if ($row = $st->fetch()) {
            db()->prepare('DELETE FROM blog_posts WHERE id = ?')->execute([$id]);
            delete_upload($row['image']);
            flash('info', 'The post was deleted.');
        }
        redirect('admin.php');
    }

    if ($action === 'save') {
        $form['title']       = preg_replace('/\s+/', ' ', post('title'));
        $form['category']    = preg_replace('/\s+/', ' ', post('category')) ?: 'Health tips';
        $form['description'] = post('description');

        if (mb_strlen($form['title']) < 5 || mb_strlen($form['title']) > 200) {
            $errors['title'] = 'Title should be 5 to 200 characters.';
        }
        if (mb_strlen($form['category']) > 60) {
            $errors['category'] = 'Category can be up to 60 characters.';
        }
        if (mb_strlen($form['description']) < 30) {
            $errors['description'] = 'Please write at least 30 characters.';
        }
        if (!$editId && (empty($_FILES['image']) || $_FILES['image']['error'] === UPLOAD_ERR_NO_FILE)) {
            $errors['image'] = 'Please choose an image for the post.';
        }

        $newImage = $errors ? null : save_upload($uploadDir, $errors);

        if (!$errors) {
            if ($editId) {
                $image = $newImage ?: $form['image'];
                db()->prepare('UPDATE blog_posts SET title = ?, category = ?, description = ?, image = ? WHERE id = ?')
                    ->execute([$form['title'], $form['category'], $form['description'], $image, $editId]);
                if ($newImage) {
                    delete_upload($form['image']);
                }
                flash('success', 'Post updated.');
                redirect('post.php?id=' . $editId);
            }
            db()->prepare('INSERT INTO blog_posts (title, category, description, image, author_id) VALUES (?, ?, ?, ?, ?)')
                ->execute([$form['title'], $form['category'], $form['description'], $newImage, $admin['id']]);
            $newId = (int) db()->lastInsertId();
            flash('success', 'Your post is published and now shows first on the blog.');
            redirect('post.php?id=' . $newId);
        }
    }
}

$list = db()->query(
    'SELECT p.id, p.title, p.category, p.image, p.created_at, (SELECT COUNT(*) FROM blog_comments c WHERE c.post_id = p.id) AS comments
     FROM blog_posts p ORDER BY p.created_at DESC, p.id DESC'
)->fetchAll();
$cats = db()->query('SELECT DISTINCT category FROM blog_posts ORDER BY category')->fetchAll(PDO::FETCH_COLUMN);

$pageTitle = 'Admin: blog posts | CareFirst';
$navActive = 'admin.php';
$adminTab  = 'posts';
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/admin-tabs.php';
?>
<section class="phero compact">
  <div class="wrap">
    <div class="copy">
      <p class="crumbs in d1"><a href="blog.php">Blog</a> / Admin</p>
      <span class="eyebrow in d1"><i></i>Logged in as <?= e($admin['name']) ?></span>
      <h1 class="in d2"><?= $editId ? 'Edit' : 'Write a new' ?> <span class="green">blog post</span></h1>
      <p class="lead in d3">Fill in the title, description and image, then press <b>Publish</b>. The post is saved in the database and shows at the top of Latest blogs.</p>
    </div>
  </div>
</section>

<section class="band alt">
  <div class="wrap form-wrap admin-wrap">
    <form class="cform server" method="post" action="admin.php<?= $editId ? '?edit=' . $editId : '' ?>" enctype="multipart/form-data" novalidate>
      <h3><?= $editId ? 'Edit post' : 'New post' ?></h3>
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="MAX_FILE_SIZE" value="<?= MAX_IMAGE_MB * 1024 * 1024 ?>">
<?php if ($editId): ?><input type="hidden" name="id" value="<?= $editId ?>"><?php endif; ?>
<?php if ($errors): ?><p class="err full" role="alert">Please fix the fields marked in red.</p><?php endif; ?>

      <div class="field full"><label for="a-title">Title</label>
        <input id="a-title" name="title" type="text" maxlength="200" required value="<?= e($form['title']) ?>" placeholder="e.g. 5 ways to keep elderly parents safe from falls"<?= isset($errors['title']) ? ' class="invalid"' : '' ?>>
        <small class="ferr"><?= e($errors['title'] ?? '') ?></small></div>

      <div class="field full"><label for="a-cat">Category <span class="opt">(shown as a tag)</span></label>
        <input id="a-cat" name="category" type="text" maxlength="60" list="cat-list" value="<?= e($form['category']) ?>"<?= isset($errors['category']) ? ' class="invalid"' : '' ?>>
        <datalist id="cat-list"><?php foreach ($cats as $c): ?><option value="<?= e($c) ?>"><?php endforeach; ?></datalist>
        <small class="ferr"><?= e($errors['category'] ?? '') ?></small></div>

      <div class="field full"><label for="a-desc">Description</label>
        <textarea id="a-desc" name="description" rows="14" required placeholder="Write the article here…"<?= isset($errors['description']) ? ' class="invalid"' : '' ?>><?= e($form['description']) ?></textarea>
        <small class="ferr"><?= e($errors['description'] ?? '') ?></small>
        <small class="fhelp">Tips: leave an empty line between paragraphs · start a line with <code>## </code> for a heading · start a line with <code>- </code> for a tick list · wrap words in <code>**double stars**</code> for bold.</small></div>

      <div class="field full"><label for="a-img">Image <?= $editId ? '<span class="opt">(choose a file only to replace it)</span>' : '' ?></label>
<?php if ($editId && $form['image']): ?>
        <img class="img-current" src="<?= e($form['image']) ?>" alt="Current image">
<?php endif; ?>
        <label class="drop" for="a-img">
          <img id="a-preview" alt="" hidden>
          <span id="a-drop-text"><b>Click to choose an image</b><br>JPG, PNG, WEBP or GIF, up to <?= MAX_IMAGE_MB ?> MB</span>
        </label>
        <input id="a-img" class="file" name="image" type="file" accept="image/jpeg,image/png,image/webp,image/gif"<?= $editId ? '' : ' required' ?>>
        <small class="ferr"><?= e($errors['image'] ?? '') ?></small></div>

      <div class="submit">
        <button class="btn solid" type="submit"><?= $editId ? 'Save changes' : 'Publish post' ?></button>
<?php if ($editId): ?><a class="btn outline" href="admin.php">Cancel</a><?php endif; ?>
        <span class="hint">Press Enter in the title box or click the button to publish.</span>
      </div>
    </form>

    <div class="side">
      <div class="admin-list">
        <h3>All posts (<?= count($list) ?>)</h3>
<?php foreach ($list as $p): ?>
        <div class="admin-row<?= $p['id'] == $editId ? ' current' : '' ?>">
<?php if ($p['image']): ?><img src="<?= e($p['image']) ?>" alt=""><?php else: ?><span class="noimg"></span><?php endif; ?>
          <div>
            <a href="post.php?id=<?= (int) $p['id'] ?>"><b><?= e($p['title']) ?></b></a>
            <small><?= e($p['category']) ?> · <?= e(nice_date($p['created_at'])) ?> · <?= (int) $p['comments'] ?> comments</small>
            <span class="row-actions">
              <a href="admin.php?edit=<?= (int) $p['id'] ?>">Edit</a>
              <form method="post" action="admin.php" onsubmit="return confirm('Delete this post and all its comments?')">
                <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                <button type="submit">Delete</button>
              </form>
            </span>
          </div>
        </div>
<?php endforeach; ?>
      </div>
    </div>
  </div>
</section>
<script>
// Show a preview of the chosen image before publishing.
(function () {
  var input = document.getElementById('a-img'), img = document.getElementById('a-preview'), txt = document.getElementById('a-drop-text');
  input.addEventListener('change', function () {
    var f = input.files && input.files[0];
    if (!f) { img.hidden = true; return; }
    img.src = URL.createObjectURL(f); img.hidden = false;
    txt.innerHTML = '<b>' + f.name.replace(/[<>&]/g, '') + '</b><br>Click to choose a different image';
  });
})();
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>
