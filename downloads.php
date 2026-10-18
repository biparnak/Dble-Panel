<?php
require_once 'includes/config.php';
$cards = get_download_cards();
?>
<!DOCTYPE html>
<html>
<head>
<?php render_head('Downloads'); ?>
<style>
.download-card .open-btn { display: inline-block; margin-top: 10px; }
</style>
</head>
<body>
    <div class="container">
        <h1>Downloads</h1>
        <?php if (empty($cards)): ?>
            <p>No downloads available yet.</p>
        <?php else: ?>
            <div class="downloads-grid">
                <?php foreach ($cards as $card): ?>
                <div class="download-card">
                    <h2><?php echo htmlspecialchars($card['title']); ?></h2>
                    <?php if ($card['description']): ?>
                        <p><?php echo nl2br(htmlspecialchars($card['description'])); ?></p>
                    <?php endif; ?>
                    <?php $version_count = count(get_versions_for_card($card['id'])); ?>
                    <p><small><?php echo $version_count; ?> version<?php echo $version_count === 1 ? '' : 's'; ?> available</small></p>
                    <a href="card.php?id=<?php echo $card['id']; ?>" class="download-btn open-btn">View &amp; Download</a>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <p style="margin-top:25px;"><a href="index.php">Home</a></p>
        <?php render_footer_text(); ?>
    </div>
</body>
</html>
