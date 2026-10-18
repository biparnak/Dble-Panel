<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

$card_id = isset($_GET['card_id']) ? (int)$_GET['card_id'] : 0;
$card = get_download_card($card_id);
if (!$card) {
    die("Card not found.");
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_version') {
        $version_name = trim($_POST['version_name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        if ($version_name === '') {
            $error = 'Version name is required.';
        } elseif (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            $error = 'File upload error.';
        } elseif (!payload_codec_available()) {
            $error = 'PHP GD (image) extension is not available on this server.';
        } else {
            $original_filename = basename($_FILES['file']['name']);
            $tmp_path = $_FILES['file']['tmp_name'];
            $file_data = file_get_contents($tmp_path);
            $original_size = strlen($file_data);
            $stored = encode_stored_payload($file_data);
            if ($stored === false) {
                $error = 'Failed to encode file payload.';
            } else {
                $stored_size = strlen($stored);
                $mime_type = mime_content_type($tmp_path) ?: 'application/octet-stream';
                try {
                    $stmt = $pdo->prepare("INSERT INTO download_versions (card_id, version_name, description, original_filename, file_data, file_size, compressed_size, mime_type) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->bindParam(1, $card_id, PDO::PARAM_INT);
                    $stmt->bindParam(2, $version_name, PDO::PARAM_STR);
                    $stmt->bindParam(3, $description, PDO::PARAM_STR);
                    $stmt->bindParam(4, $original_filename, PDO::PARAM_STR);
                    $stmt->bindParam(5, $stored, PDO::PARAM_LOB);
                    $stmt->bindParam(6, $original_size, PDO::PARAM_INT);
                    $stmt->bindParam(7, $stored_size, PDO::PARAM_INT);
                    $stmt->bindParam(8, $mime_type, PDO::PARAM_STR);
                    $stmt->execute();
                    $message = 'Version added successfully (compressed and stored as image payload).';
                } catch (Exception $e) {
                    $error = 'Database error (file too large for MySQL packet?): ' . $e->getMessage();
                }
            }
        }
    } elseif ($action === 'edit_version') {
        $version_id = (int)$_POST['version_id'];
        $version_name = trim($_POST['version_name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        if ($version_name === '') {
            $error = 'Version name is required.';
        } else {
            $stmt = $pdo->prepare("UPDATE download_versions SET version_name = ?, description = ? WHERE id = ? AND card_id = ?");
            $stmt->execute([$version_name, $description, $version_id, $card_id]);
            $message = 'Version updated.';
        }
    } elseif ($action === 'delete_version') {
        $version_id = (int)$_POST['version_id'];
        $stmt = $pdo->prepare("DELETE FROM download_versions WHERE id = ? AND card_id = ?");
        $stmt->execute([$version_id, $card_id]);
        $message = 'Version deleted.';
    }
}

$versions = get_versions_for_card($card_id);

$jsVersions = array();
foreach ($versions as $v) {
    $jsVersions[] = array(
        'id' => (int)$v['id'],
        'name' => (string)$v['version_name'],
        'desc' => (string)($v['description'] ?? '')
    );
}
?>
<!DOCTYPE html>
<html>
<head>
<title>Manage Versions · <?php echo htmlspecialchars($card['title']); ?></title>
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
            <a href="settings.php">Site Settings</a>
            <a href="../index.php" target="_blank">View Site</a>
            <a href="logout.php">Logout</a>
        </nav>
        <div class="admin-content">
            <h1>Manage Versions: <?php echo htmlspecialchars($card['title']); ?></h1>
            <div class="card-target">Uploading to card <strong>#<?php echo $card_id; ?></strong> — <?php echo htmlspecialchars($card['title']); ?></div>
            <p><a href="downloads.php">&larr; Back to Downloads Manager</a></p>
            <?php if ($message): ?><div class="alert success"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
            <?php if ($error): ?><div class="alert error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

            <h2>Add New Version</h2>
            <form method="post" enctype="multipart/form-data" class="version-form" id="versionUploadForm">
                <input type="hidden" name="action" value="add_version">
                <label>Version Name</label>
                <input type="text" name="version_name" required>
                <label>Description (shown under this version on its page)</label>
                <textarea name="description" rows="3" placeholder="What's new in this release..."></textarea>
                <label>File (APK, EXE, ZIP, etc.) — compressed &amp; stored as an image in the DB</label>
                <input type="file" name="file" id="versionFile" required>
                <div class="upload-progress" id="uploadProgressWrap" style="display:none;">
                    <div class="upload-progress-track"><div class="upload-progress-bar" id="uploadProgressBar"></div></div>
                    <span id="uploadProgressPct">0%</span>
                </div>
                <button type="submit" id="versionUploadBtn">Upload Version</button>
            </form>

            <h2>Existing Versions</h2>
            <table class="table">
                <thead>
                    <tr>
                        <th>Version</th>
                        <th>Description</th>
                        <th>Original Filename</th>
                        <th>Size</th>
                        <th>Stored Size</th>
                        <th>Downloads</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($versions as $version): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($version['version_name']); ?></td>
                        <td><?php $d = (string)($version['description'] ?? ''); echo $d !== '' ? nl2br(htmlspecialchars($d)) : '<em style="color:#999;">—</em>'; ?></td>
                        <td><?php echo htmlspecialchars($version['original_filename']); ?></td>
                        <td><?php echo human_filesize($version['file_size']); ?></td>
                        <td><?php echo human_filesize($version['compressed_size']); ?></td>
                        <td><?php echo (int)($version['download_count'] ?? 0); ?></td>
                        <td>
                            <a href="../download.php?id=<?php echo $version['id']; ?>" target="_blank">Download</a>
                            <button onclick="openEditVersion(<?php echo (int)$version['id']; ?>)">Edit</button>
                            <form method="post" style="display:inline" onsubmit="return confirm('Delete this version?');">
                                <input type="hidden" name="action" value="delete_version">
                                <input type="hidden" name="version_id" value="<?php echo $version['id']; ?>">
                                <button type="submit" class="danger">Delete</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Edit Version Modal -->
    <div id="editVerModal" class="modal" style="display:none;">
        <div class="modal-content">
            <span class="close" onclick="closeEditVersion()">&times;</span>
            <h2>Edit Version</h2>
            <form method="post">
                <input type="hidden" name="action" value="edit_version">
                <input type="hidden" name="version_id" id="ev_id">
                <label>Version Name</label>
                <input type="text" name="version_name" id="ev_name" required>
                <label>Description</label>
                <textarea name="description" id="ev_desc" rows="4"></textarea>
                <button type="submit">Save</button>
                <button type="button" onclick="closeEditVersion()">Cancel</button>
            </form>
        </div>
    </div>

    <script>
        var versionData = <?php echo json_encode($jsVersions, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;

        function openEditVersion(id) {
            var v = null;
            for (var i = 0; i < versionData.length; i++) {
                if (versionData[i].id === id) { v = versionData[i]; break; }
            }
            if (!v) return;
            document.getElementById('ev_id').value = v.id;
            document.getElementById('ev_name').value = v.name;
            document.getElementById('ev_desc').value = v.desc;
            document.getElementById('editVerModal').style.display = 'block';
        }
        function closeEditVersion() {
            document.getElementById('editVerModal').style.display = 'none';
        }

        document.getElementById('versionUploadForm').addEventListener('submit', function (e) {
            e.preventDefault();
            var form = this;
            var btn = document.getElementById('versionUploadBtn');
            var wrap = document.getElementById('uploadProgressWrap');
            var bar = document.getElementById('uploadProgressBar');
            var pct = document.getElementById('uploadProgressPct');
            var fd = new FormData(form);
            var xhr = new XMLHttpRequest();
            wrap.style.display = 'flex';
            bar.style.width = '0%';
            pct.textContent = '0%';
            btn.disabled = true;
            xhr.upload.addEventListener('progress', function (ev) {
                if (ev.lengthComputable) {
                    var p = Math.round((ev.loaded / ev.total) * 100);
                    bar.style.width = p + '%';
                    pct.textContent = p + '%';
                }
            });
            xhr.addEventListener('load', function () {
                if (xhr.status >= 200 && xhr.status < 400) {
                    bar.style.width = '100%';
                    pct.textContent = '100%';
                    location.replace(location.href);
                } else {
                    alert('Upload failed (server error). The file may exceed the server limit.');
                    wrap.style.display = 'none';
                    btn.disabled = false;
                }
            });
            xhr.addEventListener('error', function () {
                alert('Upload failed (network error).');
                wrap.style.display = 'none';
                btn.disabled = false;
            });
            xhr.open('POST', location.href);
            xhr.send(fd);
        });
    </script>
</body>
</html>
