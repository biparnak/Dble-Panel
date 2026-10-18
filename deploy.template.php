<?php
define('DEPLOY_TOKEN', 'kinevix-2026');

if (!isset($_GET['token']) || $_GET['token'] !== DEPLOY_TOKEN) {
    http_response_code(403);
    exit('Forbidden');
}

$b64 = '__PAYLOAD_B64__';

$zipData = base64_decode($b64);
if ($zipData === false) exit('Corrupt embedded archive.');

$tmp = tempnam(sys_get_temp_dir(), 'kvx');
file_put_contents($tmp, $zipData);

if (!class_exists('ZipArchive')) {
    unlink($tmp);
    exit('This server does not have the PHP Zip extension enabled. Please upload the files manually via FTP or the hosting file manager.');
}

$zip = new ZipArchive;
if ($zip->open($tmp) !== TRUE) { unlink($tmp); exit('Failed to open embedded archive.'); }

$root = __DIR__;
$written = 0;
for ($i = 0; $i < $zip->numFiles; $i++) {
    $name = $zip->getNameIndex($i);
    if ($name === false) continue;
    if (substr($name, -1) === '/') continue;
    if (strpos($name, '..') !== false) continue;
    $target = $root . '/' . str_replace('\\', '/', $name);
    $dir = dirname($target);
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    if (file_put_contents($target, $zip->getFromIndex($i)) !== false) $written++;
}
$zip->close();
unlink($tmp);

echo '<h2>Deployed ' . $written . ' files.</h2>';
echo '<p style="color:#c00"><strong>Now DELETE deploy.php from the server for security.</strong></p>';
echo '<p><a href="admin/login.php">Go to Admin Login</a> | <a href="./">Open Site</a></p>';
echo '<p>Note: includes/config.php was not touched. If it does not exist yet, open install.php first.</p>';
