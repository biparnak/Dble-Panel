<?php
$error = '';
$warnings = array();
$installed = false;
$verifiedTables = array();
$manualConfig = '';
$configPath = __DIR__ . '/includes/config.php';

$expectedTables = array('users', 'website_files', 'download_cards', 'download_versions', 'site_settings');

$schemaStatements = array(
    'users' => "CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) UNIQUE NOT NULL,
        password_hash VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    'website_files' => "CREATE TABLE IF NOT EXISTS website_files (
        id INT AUTO_INCREMENT PRIMARY KEY,
        filename VARCHAR(255) NOT NULL,
        filepath VARCHAR(255) NOT NULL,
        size BIGINT NOT NULL DEFAULT 0,
        is_index TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    'download_cards' => "CREATE TABLE IF NOT EXISTS download_cards (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        description TEXT,
        slug VARCHAR(120) NULL DEFAULT NULL,
        custom_css TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    'download_versions' => "CREATE TABLE IF NOT EXISTS download_versions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        card_id INT NOT NULL,
        version_name VARCHAR(100) NOT NULL,
        description TEXT NULL,
        original_filename VARCHAR(255) NOT NULL,
        file_data LONGBLOB NOT NULL,
        file_size INT NOT NULL DEFAULT 0,
        compressed_size INT NOT NULL DEFAULT 0,
        mime_type VARCHAR(100) DEFAULT 'application/octet-stream',
        download_count INT NOT NULL DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (card_id) REFERENCES download_cards(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    'site_settings' => "CREATE TABLE IF NOT EXISTS site_settings (
        setting_key VARCHAR(64) PRIMARY KEY,
        setting_value LONGTEXT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
);

function install_build_config($host, $name, $user, $pass) {
    $h = addslashes($host); $n = addslashes($name); $u = addslashes($user); $p = addslashes($pass);
    $php = "<?php\n";
    $php .= "session_start();\n\n";
    $php .= "define('DB_HOST', '$h');\n";
    $php .= "define('DB_NAME', '$n');\n";
    $php .= "define('DB_USER', '$u');\n";
    $php .= "define('DB_PASS', '$p');\n\n";
    $php .= "define('UPLOAD_DIR', __DIR__ . '/../uploads/');\n";
    $php .= "define('MAX_STORAGE_BYTES', 500 * 1024 * 1024);\n\n";
    $php .= "try {\n";
    $php .= "    \$pdo = new PDO(\"mysql:host=\".DB_HOST.\";dbname=\".DB_NAME.\";charset=utf8mb4\", DB_USER, DB_PASS);\n";
    $php .= "    \$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);\n";
    $php .= "} catch (PDOException \$e) {\n";
    $php .= "    die(\"Database connection failed: \" . \$e->getMessage());\n";
    $php .= "}\n\n";
    $php .= "require_once __DIR__ . '/functions.php';\n";
    $php .= "?>\n";
    return $php;
}

function install_verify_tables($pdo, $expected) {
    try {
        $rows = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    } catch (Exception $e) {
        return array();
    } catch (Throwable $e) {
        return array();
    }
    $found = array();
    foreach ($expected as $t) {
        if (in_array($t, $rows)) $found[] = $t;
    }
    return $found;
}

$showForm = true;
if (file_exists($configPath) && !isset($_GET['force'])) {
    $showForm = false;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $showForm) {
    $db_host = trim($_POST['db_host'] ?? '');
    $db_name = trim($_POST['db_name'] ?? '');
    $db_user = trim($_POST['db_user'] ?? '');
    $db_pass = $_POST['db_pass'] ?? '';
    $admin_user = trim($_POST['admin_user'] ?? '');
    $admin_pass = $_POST['admin_pass'] ?? '';
    $site_name = trim($_POST['site_name'] ?? '');

    if ($db_host === '' || $db_name === '' || $db_user === '') {
        $error = 'Database host, name and user are required.';
    } elseif ($admin_user === '' || strlen($admin_pass) < 6) {
        $error = 'Admin username is required and password must be at least 6 characters.';
    }

    if ($error === '') {
        try {
            $serverPdo = new PDO("mysql:host=" . $db_host . ";charset=utf8mb4", $db_user, $db_pass, array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION));
        } catch (Exception $e) {
            $error = 'Could not connect to the MySQL server. Check host, user and password.<br><small>' . htmlspecialchars($e->getMessage()) . '</small>';
        } catch (Throwable $e) {
            $error = 'Could not connect to the MySQL server. Check host, user and password.<br><small>' . htmlspecialchars($e->getMessage()) . '</small>';
        }
    }

    if ($error === '') {
        try {
            $safeName = str_replace('`', '', $db_name);
            $serverPdo->exec("CREATE DATABASE IF NOT EXISTS `" . $safeName . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        } catch (Exception $e) {
            $warnings[] = 'The database could not be created automatically (your host may not allow it). If installation fails below, create the database "' . htmlspecialchars($db_name) . '" in your hosting control panel first.';
        } catch (Throwable $e) {
            $warnings[] = 'The database could not be created automatically. Create it in your hosting control panel if installation fails.';
        }
    }

    if ($error === '') {
        try {
            $pdo = new PDO("mysql:host=" . $db_host . ";dbname=" . $db_name . ";charset=utf8mb4", $db_user, $db_pass, array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION));
        } catch (Exception $e) {
            $error = 'Connected to the server, but could not select database <strong>' . htmlspecialchars($db_name) . '</strong>. Create it in your ALC hosting control panel (SQL Databases), then install again.<br><small>' . htmlspecialchars($e->getMessage()) . '</small>';
        } catch (Throwable $e) {
            $error = 'Connected to the server, but could not select database <strong>' . htmlspecialchars($db_name) . '</strong>.<br><small>' . htmlspecialchars($e->getMessage()) . '</small>';
        }
    }

    if ($error === '') {
        foreach ($schemaStatements as $table => $sql) {
            try {
                $pdo->exec($sql);
            } catch (Exception $e) {
                $warnings[] = 'Table <strong>' . htmlspecialchars($table) . '</strong>: ' . htmlspecialchars($e->getMessage());
            } catch (Throwable $e) {
                $warnings[] = 'Table <strong>' . htmlspecialchars($table) . '</strong>: ' . htmlspecialchars($e->getMessage());
            }
        }

        $verifiedTables = install_verify_tables($pdo, $expectedTables);
        $missing = array_diff($expectedTables, $verifiedTables);
        if (!empty($missing)) {
            $error = 'These tables could NOT be created: <strong>' . htmlspecialchars(implode(', ', $missing)) . '</strong>. Check the warnings above — usually the SQL user lacks permission or the DB was not selected.';
        }
    }

    if ($error === '') {
        try {
            $cols = $pdo->query("SHOW COLUMNS FROM download_cards")->fetchAll(PDO::FETCH_COLUMN);
            if (!in_array('slug', $cols)) $pdo->exec("ALTER TABLE download_cards ADD COLUMN slug VARCHAR(120) NULL DEFAULT NULL");
            if (!in_array('custom_css', $cols)) $pdo->exec("ALTER TABLE download_cards ADD COLUMN custom_css TEXT NULL");
            $vcols = $pdo->query("SHOW COLUMNS FROM download_versions")->fetchAll(PDO::FETCH_COLUMN);
            if (!in_array('download_count', $vcols)) $pdo->exec("ALTER TABLE download_versions ADD COLUMN download_count INT NOT NULL DEFAULT 0");
            if (!in_array('description', $vcols)) $pdo->exec("ALTER TABLE download_versions ADD COLUMN description TEXT NULL");
        } catch (Exception $e) {
        } catch (Throwable $e) {
        }
    }

    if ($error === '') {
        try {
            $hash = password_hash($admin_pass, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
            $stmt->execute([$admin_user]);
            $existing = $stmt->fetch();
            if ($existing) {
                $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
                $stmt->execute([$hash, $existing['id']]);
                $warnings[] = 'Admin user <strong>' . htmlspecialchars($admin_user) . '</strong> already existed — its password was updated.';
            } else {
                $stmt = $pdo->prepare("INSERT INTO users (username, password_hash) VALUES (?, ?)");
                $stmt->execute([$admin_user, $hash]);
            }
        } catch (Exception $e) {
            $error = 'Could not create the admin account: ' . htmlspecialchars($e->getMessage());
        } catch (Throwable $e) {
            $error = 'Could not create the admin account: ' . htmlspecialchars($e->getMessage());
        }
    }

    if ($error === '' && $site_name !== '') {
        try {
            $stmt = $pdo->prepare("INSERT INTO site_settings (setting_key, setting_value) VALUES ('site_name', ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
            $stmt->execute([$site_name]);
        } catch (Exception $e) {
        } catch (Throwable $e) {
        }
    }

    if ($error === '') {
        $configPhp = install_build_config($db_host, $db_name, $db_user, $db_pass);
        if (@file_put_contents($configPath, $configPhp) === false) {
            $manualConfig = $configPhp;
            $error = 'Everything succeeded, but <code>includes/config.php</code> could not be written (folder permissions). Copy the code from the box below and save it manually as <code>includes/config.php</code>, then delete install.php.';
        }
    }

    if ($error === '' && !$manualConfig) {
        $installed = true;
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Install · Kinevix Panel</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body class="login-body">
    <div class="login-container install-container">
        <?php if (!$showForm): ?>
            <h2>Already Installed</h2>
            <div class="alert success">A configuration file (<code>includes/config.php</code>) already exists.</div>
            <p><a href="admin/login.php">Go to Admin Login</a></p>
            <p><a href="?force=1">Run the installer anyway (overwrites config.php)</a></p>
        <?php elseif ($installed): ?>
            <h2>Installation Complete</h2>
            <div class="alert success">
                Database connected, tables created and admin account ready.
                <br><strong>Verified tables:</strong> <?php echo htmlspecialchars(implode(', ', $verifiedTables)); ?>
            </div>
            <?php foreach ($warnings as $w): ?><div class="alert error"><?php echo $w; ?></div><?php endforeach; ?>
            <p><a href="admin/login.php">Go to Admin Login</a></p>
            <div class="alert error"><strong>Security:</strong> delete <code>install.php</code> from the server now.</div>
        <?php else: ?>
            <h2>Kinevix Panel Installer</h2>
            <?php if ($error): ?><div class="alert error"><?php echo $error; ?></div><?php endif; ?>
            <?php if ($manualConfig): ?>
                <textarea class="code-editor" rows="14" readonly onclick="this.select()"><?php echo htmlspecialchars($manualConfig); ?></textarea>
            <?php endif; ?>
            <?php foreach ($warnings as $w): ?><div class="alert error"><?php echo $w; ?></div><?php endforeach; ?>
            <form method="post">
                <h3>Database (ALC Hosting SQL)</h3>
                <label>SQL Host</label>
                <input type="text" name="db_host" value="<?php echo htmlspecialchars($_POST['db_host'] ?? 'sql113.alchosting.xyz'); ?>" required>
                <label>SQL Database Name</label>
                <input type="text" name="db_name" value="<?php echo htmlspecialchars($_POST['db_name'] ?? ''); ?>" placeholder="alcy_00000000_db" required>
                <label>SQL Username</label>
                <input type="text" name="db_user" value="<?php echo htmlspecialchars($_POST['db_user'] ?? ''); ?>" placeholder="alcy_00000000" required>
                <label>SQL Password</label>
                <input type="password" name="db_pass" value="">

                <h3>Admin Account</h3>
                <label>Admin Username</label>
                <input type="text" name="admin_user" value="<?php echo htmlspecialchars($_POST['admin_user'] ?? 'admin'); ?>" required>
                <label>Admin Password (min 6 chars)</label>
                <input type="password" name="admin_pass" minlength="6" required>

                <h3>Site Identity</h3>
                <label>Site Name</label>
                <input type="text" name="site_name" value="<?php echo htmlspecialchars($_POST['site_name'] ?? 'My Website'); ?>">

                <button type="submit">Install Now</button>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>
