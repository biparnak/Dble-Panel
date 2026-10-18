<?php
require_once 'includes/config.php';

$index_file = get_index_file();
if ($index_file) {
    if (render_stored_homepage($index_file['filepath'], $index_file['filename'])) {
        exit;
    }
}
?>
<!DOCTYPE html>
<html>
<head>
<?php render_head(); ?>
</head>
<body class="public-home">
    <div class="container">
        <h1><?php echo htmlspecialchars(get_setting('site_name', 'Welcome')); ?></h1>
        <p>No index page has been set by the admin yet.</p>
        <p><a href="downloads.php">Go to Downloads</a></p>
        <?php render_footer_text(); ?>
    </div>
</body>
</html>
