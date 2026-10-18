<?php
require_once 'includes/config.php';

$filename = $_GET['name'] ?? '';
if (!$filename) {
    http_response_code(400);
    exit('Filename required');
}
$file = get_website_file_by_name($filename);
if (!$file) {
    http_response_code(404);
    exit('File not found');
}
serve_website_file($file['filepath'], $file['filename']);
?>
