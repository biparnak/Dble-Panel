<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save_settings') {
        $siteName = trim($_POST['site_name'] ?? '');
        if ($siteName === '') {
            $error = 'Site name cannot be empty.';
        } else {
            set_setting('site_name', $siteName);
            set_setting('tab_title', trim($_POST['tab_title'] ?? ''));
            set_setting('footer_text', trim($_POST['footer_text'] ?? ''));

            if (!empty($_POST['remove_favicon'])) {
                set_setting('favicon', '');
            }

            if (isset($_FILES['favicon']) && $_FILES['favicon']['error'] === UPLOAD_ERR_OK) {
                $tmp = $_FILES['favicon']['tmp_name'];
                if (filesize($tmp) > 300 * 1024) {
                    $error = 'Icon must be under 300 KB.';
                } else {
                    $info = @getimagesize($tmp);
                    $ext = strtolower(pathinfo($_FILES['favicon']['name'], PATHINFO_EXTENSION));
                    if ($info) {
                        $mime = $info['mime'];
                    } elseif ($ext === 'ico') {
                        $mime = 'image/x-icon';
                    } elseif ($ext === 'svg') {
                        $mime = 'image/svg+xml';
                    } else {
                        $mime = '';
                    }
                    if ($mime === '') {
                        $error = 'Icon must be an image (PNG, ICO, SVG, JPG...).';
                    } else {
                        $dataUri = 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($tmp));
                        set_setting('favicon', $dataUri);
                    }
                }
            }

            if ($error === '') $message = 'Settings saved.';
        }
    }
}

$s = get_site_settings();
?>
<!DOCTYPE html>
<html>
<head>
<title>Site Settings · Admin</title>
<link rel="stylesheet" href="../assets/style.css">
</head>
<body>
    <div class="admin-wrapper">
        <nav class="admin-nav">
            <h2>Panel Admin</h2>
            <a href="index.php">Dashboard</a>
            <a href="files.php">File Manager</a>
            <a href="downloads.php">Downloads Manager</a>
            <a href="editor.php">Appearance</a>
            <a href="settings.php" class="active">Site Settings</a>
            <a href="../index.php" target="_blank">View Site</a>
            <a href="logout.php">Logout</a>
        </nav>
        <div class="admin-content">
            <h1>Site Settings</h1>
            <?php if ($message): ?><div class="alert success"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
            <?php if ($error): ?><div class="alert error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

            <form method="post" enctype="multipart/form-data" class="settings-form">
                <input type="hidden" name="action" value="save_settings">

                <label>Site Name (used across the site)</label>
                <input type="text" name="site_name" value="<?php echo htmlspecialchars($s['site_name']); ?>" required>

                <label>Browser Tab Title (leave empty to use site name)</label>
                <input type="text" name="tab_title" value="<?php echo htmlspecialchars($s['tab_title']); ?>" placeholder="e.g. Kinevix — Official Site">

                <label>Footer Text (public pages)</label>
                <input type="text" name="footer_text" value="<?php echo htmlspecialchars($s['footer_text']); ?>" placeholder="© <?php echo date('Y'); ?> Your Brand">

                <label>Favicon / Tab Icon (PNG, ICO, SVG, JPG — max 300 KB)</label>
                <?php if ($s['favicon'] !== ''): ?>
                    <div class="favicon-preview">
                        <img src="<?php echo htmlspecialchars($s['favicon']); ?>" alt="Current icon" width="32" height="32">
                        <span>Current icon</span>
                        <label class="checkbox-label"><input type="checkbox" name="remove_favicon" value="1"> Remove current icon</label>
                    </div>
                <?php endif; ?>
                <input type="file" name="favicon" accept=".png,.ico,.svg,.jpg,.jpeg,.gif,.webp">

                <button type="submit">Save Settings</button>
            </form>
        </div>
    </div>
</body>
</html>
