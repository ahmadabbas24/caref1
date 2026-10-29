<?php
require __DIR__ . '/includes/bootstrap.php';

if (hash_equals(csrf_token(), (string) ($_GET['t'] ?? $_POST['csrf'] ?? ''))) {
    $_SESSION = [];
    session_regenerate_id(true);
    flash('info', 'You have been logged out.');
}
redirect('blog.php');
