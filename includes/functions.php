<?php
define('KINEVIX_PAYLOAD_MAGIC', 'KVX1');

function ensure_table_ids($pdo, $tables) {
    try {
        $db = $pdo->query("SELECT DATABASE()")->fetchColumn();
        if (!$db) return;
        $safeList = implode(',', array_map(function ($t) use ($pdo) { return $pdo->quote($t); }, $tables));
        $aiRows = $pdo->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = " . $pdo->quote($db) . " AND TABLE_NAME IN ($safeList) AND AUTO_INCREMENT IS NOT NULL")->fetchAll(PDO::FETCH_COLUMN);
        $pkRows = $pdo->query("SELECT TABLE_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = " . $pdo->quote($db) . " AND CONSTRAINT_NAME = 'PRIMARY' AND COLUMN_NAME = 'id' AND TABLE_NAME IN ($safeList)")->fetchAll(PDO::FETCH_COLUMN);

        foreach ($tables as $t) {
            $needsPk = !in_array($t, $pkRows);
            $needsAi = !in_array($t, $aiRows);
            if (!$needsPk && !$needsAi) continue;
            if ($needsPk) {
                $pdo->exec("SET @kvx_i = 0");
                $pdo->exec("UPDATE `" . str_replace('`', '', $t) . "` SET id = (@kvx_i := @kvx_i + 1)");
                $pdo->exec("ALTER TABLE `" . str_replace('`', '', $t) . "` ADD PRIMARY KEY (id)");
            }
            $pdo->exec("ALTER TABLE `" . str_replace('`', '', $t) . "` MODIFY id INT NOT NULL AUTO_INCREMENT");
        }

        if (in_array('download_versions', $tables) && in_array('download_cards', $tables)) {
            $pdo->exec("DELETE FROM download_versions WHERE card_id NOT IN (SELECT id FROM download_cards)");
        }
    } catch (Exception $e) {
    } catch (Throwable $e) {
    }
}

function ensure_db_upgrades() {
    global $pdo;
    static $done = false;
    if ($done) return;
    $done = true;
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS site_settings (
            setting_key VARCHAR(64) PRIMARY KEY,
            setting_value LONGTEXT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $cols = $pdo->query("SHOW COLUMNS FROM download_cards")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('slug', $cols)) {
            $pdo->exec("ALTER TABLE download_cards ADD COLUMN slug VARCHAR(120) NULL DEFAULT NULL");
        }
        if (!in_array('custom_css', $cols)) {
            $pdo->exec("ALTER TABLE download_cards ADD COLUMN custom_css TEXT NULL");
        }
        $vcols = $pdo->query("SHOW COLUMNS FROM download_versions")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('description', $vcols)) {
            $pdo->exec("ALTER TABLE download_versions ADD COLUMN description TEXT NULL");
        }
        ensure_table_ids($pdo, array('users', 'website_files', 'download_cards', 'download_versions'));
    } catch (Exception $e) {
    } catch (Throwable $e) {
    }
}

function is_logged_in() {
    return isset($_SESSION['admin_id']);
}

function require_login() {
    if (!is_logged_in()) {
        header('Location: login.php');
        exit;
    }
}

function human_filesize($bytes, $decimals = 2) {
    $bytes = (int)$bytes;
    $size = array('B','kB','MB','GB','TB','PB','EB','ZB','YB');
    $factor = floor((strlen($bytes) - 1) / 3);
    return sprintf("%.{$decimals}f", $bytes / pow(1024, $factor)) . ' ' . @$size[$factor];
}

function get_total_website_files_size() {
    global $pdo;
    $stmt = $pdo->query("SELECT COALESCE(SUM(size),0) FROM website_files");
    return (int)$stmt->fetchColumn();
}

function get_index_file() {
    global $pdo;
    $stmt = $pdo->query("SELECT * FROM website_files WHERE is_index = 1 LIMIT 1");
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function get_website_file_by_name($filename) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM website_files WHERE filename = ? LIMIT 1");
    $stmt->execute([$filename]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function get_website_file_by_id($id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM website_files WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function get_all_website_files() {
    global $pdo;
    $stmt = $pdo->query("SELECT * FROM website_files ORDER BY is_index DESC, created_at DESC");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function delete_website_file($id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM website_files WHERE id = ?");
    $stmt->execute([$id]);
    $file = $stmt->fetch();
    if ($file) {
        $path = UPLOAD_DIR . $file['filepath'];
        if (file_exists($path)) unlink($path);
        $stmt = $pdo->prepare("DELETE FROM website_files WHERE id = ?");
        $stmt->execute([$id]);
        return true;
    }
    return false;
}

function set_index_file($id) {
    global $pdo;
    $pdo->beginTransaction();
    $pdo->exec("UPDATE website_files SET is_index = 0");
    $stmt = $pdo->prepare("UPDATE website_files SET is_index = 1 WHERE id = ?");
    $stmt->execute([$id]);
    $pdo->commit();
}

function compress_data($data) {
    return gzcompress($data, 9);
}

function decompress_data($data) {
    return gzuncompress($data);
}

function payload_codec_available() {
    return function_exists('imagecreatetruecolor') && function_exists('imagepng') && function_exists('imagecreatefromstring');
}

function png_encode_data($data) {
    $payload = KINEVIX_PAYLOAD_MAGIC . pack('N', strlen($data)) . $data;
    $total = strlen($payload);
    $width = 1024;
    $height = (int)ceil(($total / 3) / $width);
    if ($height < 1) $height = 1;
    $img = imagecreatetruecolor($width, $height);
    $pos = 0;
    for ($y = 0; $y < $height; $y++) {
        for ($x = 0; $x < $width; $x++) {
            $r = $pos < $total ? ord($payload[$pos]) : 0; $pos++;
            $g = $pos < $total ? ord($payload[$pos]) : 0; $pos++;
            $b = $pos < $total ? ord($payload[$pos]) : 0; $pos++;
            imagesetpixel($img, $x, $y, imagecolorallocate($img, $r, $g, $b));
        }
    }
    ob_start();
    imagepng($img, null, 6);
    $png = ob_get_clean();
    return $png;
}

function png_decode_data($blob) {
    $img = @imagecreatefromstring($blob);
    if ($img === false) return false;
    $w = imagesx($img);
    $h = imagesy($img);
    $payload = '';
    for ($y = 0; $y < $h; $y++) {
        for ($x = 0; $x < $w; $x++) {
            $rgb = imagecolorat($img, $x, $y);
            $payload .= chr(($rgb >> 16) & 0xFF) . chr(($rgb >> 8) & 0xFF) . chr($rgb & 0xFF);
        }
    }
    if (strlen($payload) < 8 || strncmp($payload, KINEVIX_PAYLOAD_MAGIC, 4) !== 0) return false;
    $len = unpack('N', substr($payload, 4, 4));
    $len = (int)$len[1];
    if ($len < 0 || $len > strlen($payload) - 8) return false;
    return substr($payload, 8, $len);
}

function encode_stored_payload($rawData) {
    $compressed = compress_data($rawData);
    if ($compressed === false) return false;
    if (!payload_codec_available()) return $compressed;
    $png = png_encode_data($compressed);
    if ($png === false) return $compressed;
    return $png;
}

function decode_stored_payload($blob) {
    if ($blob === null || $blob === '') return false;
    if (strncmp($blob, "\x89PNG", 4) === 0 && payload_codec_available()) {
        $compressed = png_decode_data($blob);
        if ($compressed === false) return false;
        $data = @gzuncompress($compressed);
        return $data === false ? false : $data;
    }
    $data = @gzuncompress($blob);
    if ($data !== false) return $data;
    if (payload_codec_available()) {
        $inner = png_decode_data($blob);
        if ($inner !== false) return @gzuncompress($inner);
    }
    return false;
}

function get_site_settings() {
    global $pdo;
    $settings = array(
        'site_name' => 'My Website',
        'tab_title' => '',
        'favicon' => '',
        'footer_text' => '',
        'custom_css' => ''
    );
    try {
        $rows = $pdo->query("SELECT setting_key, setting_value FROM site_settings")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $row) {
            if (array_key_exists($row['setting_key'], $settings)) {
                $settings[$row['setting_key']] = (string)$row['setting_value'];
            }
        }
    } catch (Exception $e) {
    } catch (Throwable $e) {
    }
    return $settings;
}

function get_setting($key, $default = '') {
    $s = get_site_settings();
    return (isset($s[$key]) && $s[$key] !== '') ? $s[$key] : $default;
}

function set_setting($key, $value) {
    global $pdo;
    $stmt = $pdo->prepare("INSERT INTO site_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
    $stmt->execute([$key, $value]);
}

function render_head($title = '', $base = '') {
    echo build_public_head($title, $base);
}

function build_public_head($title = '', $base = '') {
    $s = get_site_settings();
    if (trim($s['tab_title']) !== '') {
        $tab = $s['tab_title'];
    } elseif (trim((string)$title) !== '') {
        $tab = $title . ' · ' . $s['site_name'];
    } else {
        $tab = $s['site_name'];
    }
    $html = '<meta charset="utf-8">';
    $html .= '<meta name="viewport" content="width=device-width, initial-scale=1">';
    $html .= '<title>' . htmlspecialchars($tab) . '</title>';
    if ($s['favicon'] !== '') {
        $html .= '<link rel="icon" href="' . htmlspecialchars($s['favicon']) . '">';
    }
    $html .= '<link rel="stylesheet" href="' . $base . 'assets/style.css">';
    if (trim($s['custom_css']) !== '') {
        $html .= '<style>' . $s['custom_css'] . '</style>';
    }
    return $html;
}

function wrap_homepage_fragment($content) {
    if (stripos($content, '<html') !== false || stripos($content, '<!doctype') !== false) {
        return $content;
    }
    $head = build_public_head();
    $footer = '';
    $footerText = get_setting('footer_text');
    if (trim($footerText) !== '') {
        $footer = '<footer class="site-footer">' . nl2br(htmlspecialchars($footerText)) . '</footer>';
    }
    $doc = "<!DOCTYPE html>\n<html>\n<head>\n" . $head . "\n</head>\n<body class=\"public-home\">\n" . $content . "\n" . $footer . "\n</body>\n</html>";
    return $doc;
}

function extract_homepage_body($html) {
    if (preg_match('/<body[^>]*>(.*)<\/body>/is', $html, $m)) {
        return trim($m[1]);
    }
    return null;
}

function file_looks_like_php($path) {
    $fh = @fopen($path, 'rb');
    if (!$fh) return false;
    $chunk = fread($fh, 8192);
    fclose($fh);
    if ($chunk === false || $chunk === '') return false;
    return stripos($chunk, '<?php') !== false || stripos($chunk, '<?=', 0) !== false;
}

function render_footer_text() {
    $footer = get_setting('footer_text');
    if (trim($footer) !== '') {
        echo '<footer class="site-footer">' . nl2br(htmlspecialchars($footer)) . '</footer>';
    }
}

function slugify($text) {
    $text = strtolower(trim(preg_replace('/[^A-Za-z0-9]+/', '-', (string)$text), '-'));
    return substr($text, 0, 120);
}

function content_type_from_filename($filename) {
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    $map = array(
        'html' => 'text/html; charset=utf-8',
        'htm' => 'text/html; charset=utf-8',
        'css' => 'text/css; charset=utf-8',
        'js' => 'application/javascript; charset=utf-8',
        'mjs' => 'application/javascript; charset=utf-8',
        'json' => 'application/json; charset=utf-8',
        'xml' => 'application/xml',
        'txt' => 'text/plain; charset=utf-8',
        'md' => 'text/plain; charset=utf-8',
        'log' => 'text/plain; charset=utf-8',
        'ini' => 'text/plain; charset=utf-8',
        'csv' => 'text/csv; charset=utf-8',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
        'svg' => 'image/svg+xml',
        'ico' => 'image/x-icon',
        'bmp' => 'image/bmp',
        'avif' => 'image/avif',
        'pdf' => 'application/pdf',
        'mp3' => 'audio/mpeg',
        'wav' => 'audio/wav',
        'ogg' => 'audio/ogg',
        'mp4' => 'video/mp4',
        'webm' => 'video/webm',
        'woff' => 'font/woff',
        'woff2' => 'font/woff2',
        'ttf' => 'font/ttf',
        'otf' => 'font/otf',
        'zip' => 'application/zip'
    );
    return isset($map[$ext]) ? $map[$ext] : null;
}

function serve_website_file($filepath, $displayFilename) {
    $path = UPLOAD_DIR . $filepath;
    if (!file_exists($path)) {
        http_response_code(404);
        exit('File missing on disk');
    }
    if (preg_match('/\.php$/i', $filepath)) {
        header('Location: page.php?name=' . urlencode($displayFilename));
        exit;
    }
    if (file_looks_like_php($path)) {
        header('Location: page.php?name=' . urlencode($displayFilename));
        exit;
    }
    if (preg_match('/\.(phtml|phar|cgi|pl)$/i', $filepath)) {
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . str_replace(array('"', "\\", "/", "\r", "\n"), '', $displayFilename) . '"');
        header('Content-Length: ' . filesize($path));
        header('X-Content-Type-Options: nosniff');
        readfile($path);
        exit;
    }
    $ctype = content_type_from_filename($displayFilename);
    if ($ctype === null) {
        $detected = function_exists('mime_content_type') ? @mime_content_type($path) : false;
        $ctype = $detected ? $detected : 'application/octet-stream';
    }
    header('Content-Type: ' . $ctype);
    header('Content-Length: ' . filesize($path));
    header('X-Content-Type-Options: nosniff');
    readfile($path);
    exit;
}

function render_stored_homepage($filepath, $displayFilename) {
    $path = UPLOAD_DIR . $filepath;
    if (!file_exists($path)) {
        return false;
    }
    if (preg_match('/\.php$/i', $filepath) || file_looks_like_php($path)) {
        include $path;
        return true;
    }
    serve_website_file($filepath, $displayFilename);
    return true;
}

function site_base_url($path = '') {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';
    $dir = rtrim(str_replace('\\', '/', dirname(isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '/')), '/');
    if (substr($dir, -6) === '/admin') $dir = substr($dir, 0, -6);
    return $scheme . '://' . $host . $dir . '/' . ltrim($path, '/');
}

function get_download_cards() {
    global $pdo;
    $stmt = $pdo->query("SELECT * FROM download_cards ORDER BY created_at DESC");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function get_download_card($id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM download_cards WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function get_download_card_by_slug($slug) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM download_cards WHERE slug = ? LIMIT 1");
    $stmt->execute([$slug]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function get_versions_for_card($card_id) {
    global $pdo;
    $card_id = (int)$card_id;
    $stmt = $pdo->prepare("SELECT * FROM download_versions WHERE card_id = ? ORDER BY created_at DESC");
    $stmt->execute([$card_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function get_download_version($id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM download_versions WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

ensure_db_upgrades();
?>
