<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

$total_size = get_total_website_files_size();
$index_file = get_index_file();
$card_count = count(get_download_cards());
$version_count = $pdo->query("SELECT COUNT(*) FROM download_versions")->fetchColumn();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="../assets/style.css">
</head>
<body>
    <div class="admin-wrapper">
        <nav class="admin-nav">
            <h2>Panel Admin</h2>
            <a href="index.php" class="active">Dashboard</a>
            <a href="files.php">File Manager</a>
            <a href="downloads.php">Downloads Manager</a>
            <a href="editor.php">Appearance</a>
            <a href="settings.php">Site Settings</a>
            <a href="../index.php" target="_blank">View Site</a>
            <a href="logout.php">Logout</a>
        </nav>
        <div class="admin-content">
            <h1>Dashboard</h1>
            <div class="stats-grid">
                <div class="stat-card">
                    <h3>Storage Used (Files)</h3>
                    <p><?php echo human_filesize($total_size); ?> / 500 MB</p>
                    <div class="progress-bar"><div class="progress" style="width: <?php echo min(100, ($total_size / MAX_STORAGE_BYTES) * 100); ?>%"></div></div>
                </div>
                <div class="stat-card">
                    <h3>Index File</h3>
                    <p><?php echo $index_file ? htmlspecialchars($index_file['filename']) : 'Not set'; ?></p>
                </div>
                <div class="stat-card">
                    <h3>Download Cards</h3>
                    <p><?php echo $card_count; ?></p>
                </div>
                <div class="stat-card">
                    <h3>Total Versions</h3>
                    <p><?php echo $version_count; ?></p>
                </div>
            </div>

            <h2>Quick Actions</h2>
            <div class="stats-grid">
                <div class="stat-card">
                    <h3>Appearance</h3>
                    <p>Edit homepage, style.css &amp; custom CSS.</p>
                    <p><a class="btn" href="editor.php">Open Editor</a></p>
                </div>
                <div class="stat-card">
                    <h3>Site Settings</h3>
                    <p>Name, tab title, favicon icon.</p>
                    <p><a class="btn" href="settings.php">Open Settings</a></p>
                </div>
                <div class="stat-card">
                    <h3>New Download Card</h3>
                    <p>Create a standalone download page.</p>
                    <p><a class="btn" href="downloads.php">Create Card</a></p>
                </div>
            </div>
        </div>
    </div>
</body>
</html>