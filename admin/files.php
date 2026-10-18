<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'upload') {
        if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
            $original_name = basename($_FILES['file']['name']);
            $tmp_path = $_FILES['file']['tmp_name'];
            $size = filesize($tmp_path);
            $total_size = get_total_website_files_size();
            if ($total_size + $size > MAX_STORAGE_BYTES) {
                $error = 'Upload would exceed 500MB storage limit.';
            } else {
                $ext = pathinfo($original_name, PATHINFO_EXTENSION);
                $stored_name = uniqid() . ($ext ? '.' . $ext : '');
                $dest = UPLOAD_DIR . $stored_name;
                if (!is_dir(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0755, true);
                if (move_uploaded_file($tmp_path, $dest)) {
                    $stmt = $pdo->prepare("INSERT INTO website_files (filename, filepath, size, is_index) VALUES (?, ?, ?, 0)");
                    $stmt->execute([$original_name, $stored_name, $size]);
                    $message = 'File uploaded successfully.';
                } else {
                    $error = 'Failed to move uploaded file.';
                }
            }
        } else {
            $error = 'No file uploaded or upload error.';
        }
    } elseif ($action === 'set_index') {
        $id = (int)$_POST['id'];
        set_index_file($id);
        $message = 'Index file updated.';
    } elseif ($action === 'delete') {
        $id = (int)$_POST['id'];
        if (delete_website_file($id)) {
            $message = 'File deleted.';
        } else {
            $error = 'File not found or could not be deleted.';
        }
    } elseif ($action === 'edit') {
        $id = (int)$_POST['id'];
        $content = $_POST['content'] ?? '';
        $file = get_website_file_by_id($id);
        if ($file) {
            $ext = strtolower(pathinfo($file['filename'], PATHINFO_EXTENSION));
            $text_exts = ['txt','html','htm','css','js','php','json','xml','md','ini','log','yml','yaml'];
            if (in_array($ext, $text_exts)) {
                $path = UPLOAD_DIR . $file['filepath'];
                if (file_put_contents($path, $content) !== false) {
                    $new_size = filesize($path);
                    $stmt = $pdo->prepare("UPDATE website_files SET size = ? WHERE id = ?");
                    $stmt->execute([$new_size, $id]);
                    $message = 'File updated.';
                } else {
                    $error = 'Failed to write file.';
                }
            } else {
                $error = 'This file type is not editable as text.';
            }
        }
    }
}

$files = get_all_website_files();
$total_size = get_total_website_files_size();

$text_exts_list = ['txt','html','htm','css','js','php','json','xml','md','ini','log','yml','yaml'];
$editableFiles = array();
foreach ($files as $f) {
    $ext = strtolower(pathinfo($f['filename'], PATHINFO_EXTENSION));
    if (in_array($ext, $text_exts_list) && $f['size'] < 1048576) {
        $content = @file_get_contents(UPLOAD_DIR . $f['filepath']);
        if ($content !== false) {
            $editableFiles[] = array('id' => (int)$f['id'], 'name' => $f['filename'], 'content' => $content);
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
<title>File Manager · Admin</title>
<link rel="stylesheet" href="../assets/style.css">
</head>
<body>
    <div class="admin-wrapper">
        <nav class="admin-nav">
            <h2>Panel Admin</h2>
            <a href="index.php">Dashboard</a>
            <a href="files.php" class="active">File Manager</a>
            <a href="downloads.php">Downloads Manager</a>
            <a href="editor.php">Appearance</a>
            <a href="settings.php">Site Settings</a>
            <a href="../index.php" target="_blank">View Site</a>
            <a href="logout.php">Logout</a>
        </nav>
        <div class="admin-content">
            <h1>Website File Manager</h1>
            <?php if ($message): ?><div class="alert success"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
            <?php if ($error): ?><div class="alert error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

            <div class="storage-info">
                Storage used: <?php echo human_filesize($total_size); ?> / 500 MB
            </div>

            <h2>Upload New File</h2>
            <form method="post" enctype="multipart/form-data" class="upload-form" id="fileUploadForm">
                <input type="hidden" name="action" value="upload">
                <input type="file" name="file" required>
                <div class="upload-progress" id="uploadProgressWrap" style="display:none;">
                    <div class="upload-progress-track"><div class="upload-progress-bar" id="uploadProgressBar"></div></div>
                    <span id="uploadProgressPct">0%</span>
                </div>
                <button type="submit" id="fileUploadBtn">Upload</button>
            </form>

            <h2>Files</h2>
            <table class="table">
                <thead>
                    <tr>
                        <th>Filename</th>
                        <th>Size</th>
                        <th>Index</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($files as $file): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($file['filename']); ?></td>
                        <td><?php echo human_filesize($file['size']); ?></td>
                        <td><?php echo $file['is_index'] ? '⭐' : ''; ?></td>
                        <td class="actions">
                            <?php $is_php = preg_match('/\.php$/i', $file['filepath']); ?>
                            <a href="../<?php echo $is_php ? 'page.php' : 'file.php'; ?>?name=<?php echo urlencode($file['filename']); ?>" target="_blank"><?php echo $is_php ? 'Run' : 'View'; ?></a>
                            <form method="post" style="display:inline">
                                <input type="hidden" name="action" value="set_index">
                                <input type="hidden" name="id" value="<?php echo $file['id']; ?>">
                                <button type="submit" <?php echo $file['is_index'] ? 'disabled' : ''; ?>>Set Index</button>
                            </form>
                            <?php
                                $can_edit = false;
                                foreach ($editableFiles as $ef) { if ($ef['id'] === (int)$file['id']) { $can_edit = true; break; } }
                                if ($can_edit): ?>
                            <button onclick="openEditModal(<?php echo (int)$file['id']; ?>)">Edit</button>
                            <?php endif; ?>
                            <form method="post" style="display:inline" onsubmit="return confirm('Delete this file?');">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?php echo $file['id']; ?>">
                                <button type="submit" class="danger">Delete</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Edit Modal -->
    <div id="editModal" class="modal" style="display:none;">
        <div class="modal-content">
            <span class="close" onclick="closeEditModal()">&times;</span>
            <h2>Edit File</h2>
            <form method="post" id="editForm">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="edit_id">
                <p id="edit_filename"></p>
                <textarea name="content" id="edit_content" rows="15" style="width:100%" class="code-editor"></textarea>
                <button type="submit">Save</button>
                <button type="button" onclick="closeEditModal()">Cancel</button>
            </form>
        </div>
    </div>

    <script>
        var editableFiles = <?php echo json_encode($editableFiles, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;

        function openEditModal(id) {
            var f = null;
            for (var i = 0; i < editableFiles.length; i++) {
                if (editableFiles[i].id === id) { f = editableFiles[i]; break; }
            }
            if (!f) return;
            document.getElementById('edit_id').value = id;
            document.getElementById('edit_filename').textContent = f.name;
            document.getElementById('edit_content').value = f.content;
            document.getElementById('editModal').style.display = 'block';
        }
        function closeEditModal() {
            document.getElementById('editModal').style.display = 'none';
        }

        document.getElementById('fileUploadForm').addEventListener('submit', function (e) {
            e.preventDefault();
            var form = this;
            var btn = document.getElementById('fileUploadBtn');
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
                    location.reload();
                } else {
                    alert('Upload failed (server error).');
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
