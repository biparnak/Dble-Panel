<?php
require_once 'includes/config.php';

$name = $_GET['name'] ?? '';
if ($name === '') {
    http_response_code(400);
    exit('Filename required');
}
$file = get_website_file_by_name($name);
if (!$file) {
    http_response_code(404);
    exit('Page not found');
}
$path = UPLOAD_DIR . $file['filepath'];
if (!file_exists($path)) {
    http_response_code(404);
    exit('File missing on disk');
}

if (!preg_match('/\.php$/i', $file['filepath']) && !file_looks_like_php($path)) {
    header('Location: file.php?name=' . urlencode($file['filename']));
    exit;
}

function kinevix_run_stored_page($pagePath) {
    include $pagePath;
}

kinevix_run_stored_page($path);
exit;
?>
