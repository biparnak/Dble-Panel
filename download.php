<?php
require_once 'includes/config.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$version = get_download_version($id);
if (!$version) {
    http_response_code(404);
    exit('Version not found');
}

$data = decode_stored_payload($version['file_data']);
if ($data === false || strlen($data) === 0) {
    http_response_code(500);
    exit('Failed to decode file payload.');
}

try {
    $stmt = $pdo->prepare("UPDATE download_versions SET download_count = COALESCE(download_count,0) + 1 WHERE id = ?");
    $stmt->execute([$id]);
} catch (Exception $e) {
}

$safeName = str_replace(array('"', "\\", "/", "\r", "\n"), '', $version['original_filename']);
if ($safeName === '') $safeName = 'download-' . $id;

header('Content-Type: application/octet-stream');
header('Content-Transfer-Encoding: binary');
header('Content-Disposition: attachment; filename="' . $safeName . '"; filename*=UTF-8\'\'' . rawurlencode($safeName));
header('Content-Length: ' . strlen($data));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');
header('Pragma: private');
echo $data;
exit;
?>
