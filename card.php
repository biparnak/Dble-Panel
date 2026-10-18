<?php
require_once 'includes/config.php';

$card = null;
if (isset($_GET['id'])) {
    $card = get_download_card((int)$_GET['id']);
} elseif (isset($_GET['s'])) {
    $card = get_download_card_by_slug(slugify($_GET['s']));
}

if (!$card) {
    http_response_code(404);
}
?>
<!DOCTYPE html>
<html>
<head>
<?php render_head($card ? $card['title'] : 'Not Found'); ?>
<style>
.download-page { max-width: 800px; margin: 40px auto; background: #fff; padding: 30px; border-radius: 10px; box-shadow: 0 4px 16px rgba(0,0,0,0.08); }
.download-page .back { display: inline-block; margin-bottom: 15px; }
</style>
<?php if ($card && trim((string)$card['custom_css']) !== ''): ?>
<style><?php echo $card['custom_css']; ?></style>
<?php endif; ?>
</head>
<body>
    <div class="container">
        <?php if (!$card): ?>
            <div class="download-page">
                <h1>Download not found</h1>
                <p>This download page does not exist or was removed.</p>
                <a class="back" href="downloads.php">&larr; All Downloads</a>
            </div>
        <?php else: ?>
            <div class="download-page">
                <a class="back" href="downloads.php">&larr; All Downloads</a>
                <h1><?php echo htmlspecialchars($card['title']); ?></h1>
                <?php if ($card['description']): ?>
                    <p class="card-desc"><?php echo nl2br(htmlspecialchars($card['description'])); ?></p>
                <?php endif; ?>

                <?php $versions = get_versions_for_card($card['id']); ?>
                <?php if (!empty($versions)): ?>
                    <ul class="version-list">
                        <?php foreach ($versions as $version): ?>
                        <li>
                            <span class="version-info">
                                <strong><?php echo htmlspecialchars($version['version_name']); ?></strong><br>
                                <small><?php echo htmlspecialchars($version['original_filename']); ?> · <?php echo human_filesize($version['file_size']); ?></small>
                                <?php $vdesc = (string)($version['description'] ?? ''); if ($vdesc !== ''): ?>
                                <small class="ver-desc"><?php echo nl2br(htmlspecialchars($vdesc)); ?></small>
                                <?php endif; ?>
                            </span>
                            <a href="download.php?id=<?php echo $version['id']; ?>" class="download-btn">Download</a>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p>No versions available yet.</p>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        <?php render_footer_text(); ?>
    </div>
</body>
</html>
