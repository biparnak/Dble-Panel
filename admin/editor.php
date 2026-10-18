<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

$message = '';
$error = '';
$projectRoot = dirname(__DIR__);
$stylePath = $projectRoot . '/assets/style.css';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save_index') {
        $content = $_POST['content'] ?? '';
        $fromVisual = ($_POST['from_visual'] ?? '0') === '1';
        if ($fromVisual) {
            $content = preg_replace('/<\?php[\s\S]*?\?>/i', '', $content);
            $content = str_replace('<?=', '', $content);
        }
        $content = wrap_homepage_fragment($content);
        $idx = get_index_file();
        if ($idx) {
            if (!preg_match('/\.php$/i', $idx['filepath'])) {
                $newFilepath = uniqid('index_') . '.php';
                if (@file_put_contents(UPLOAD_DIR . $newFilepath, $content) !== false) {
                    if (file_exists(UPLOAD_DIR . $idx['filepath'])) @unlink(UPLOAD_DIR . $idx['filepath']);
                    $stmt = $pdo->prepare("UPDATE website_files SET filepath = ?, size = ? WHERE id = ?");
                    $stmt->execute([$newFilepath, filesize(UPLOAD_DIR . $newFilepath), $idx['id']]);
                    $message = 'Homepage saved and live.';
                } else {
                    $error = 'Failed to write homepage file.';
                }
            } else {
                $path = UPLOAD_DIR . $idx['filepath'];
                if (file_put_contents($path, $content) !== false) {
                    $stmt = $pdo->prepare("UPDATE website_files SET size = ? WHERE id = ?");
                    $stmt->execute([filesize($path), $idx['id']]);
                    $message = 'Homepage saved and live.';
                } else {
                    $error = 'Failed to write homepage file.';
                }
            }
        } else {
            if (!is_dir(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0755, true);
            $stored = uniqid('index_') . '.php';
            if (file_put_contents(UPLOAD_DIR . $stored, $content) !== false) {
                try {
                    $pdo->beginTransaction();
                    $pdo->exec("UPDATE website_files SET is_index = 0");
                    $stmt = $pdo->prepare("INSERT INTO website_files (filename, filepath, size, is_index) VALUES ('index.php', ?, ?, 1)");
                    $stmt->execute([$stored, filesize(UPLOAD_DIR . $stored)]);
                    $pdo->commit();
                    $message = 'Homepage created and set live.';
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $error = 'Database error: ' . $e->getMessage();
                }
            } else {
                $error = 'Failed to create homepage file on disk.';
            }
        }
    } elseif ($action === 'save_style') {
        $content = $_POST['content'] ?? '';
        if (!file_exists($stylePath) && !is_writable(dirname($stylePath))) {
            $error = 'assets/style.css is not writable by the server.';
        } elseif (file_exists($stylePath) && !is_writable($stylePath)) {
            $error = 'assets/style.css is not writable by the server.';
        } elseif (file_put_contents($stylePath, $content) !== false) {
            $message = 'Main stylesheet saved.';
        } else {
            $error = 'Failed to write assets/style.css.';
        }
    } elseif ($action === 'save_custom') {
        set_setting('custom_css', $_POST['content'] ?? '');
        $message = 'Custom CSS saved.';
    }
}

$indexFile = get_index_file();
$indexContent = '';
if ($indexFile) {
    $indexContent = (string)@file_get_contents(UPLOAD_DIR . $indexFile['filepath']);
}

$indexBody = extract_homepage_body($indexContent);
if ($indexBody === null) {
    if (stripos($indexContent, '<?php') !== false) {
        $indexBody = '<p><em>This homepage contains PHP code. Switch to Code view to edit it.</em></p>';
    } else {
        $indexBody = $indexContent;
    }
}

$styleContent = file_exists($stylePath) ? (string)@file_get_contents($stylePath) : '';
$customContent = get_setting('custom_css');

$allFiles = get_all_website_files();
$imgExts = ['png','jpg','jpeg','gif','webp','svg','ico','bmp','avif'];
$siteImages = array();
$siteLinks = array();
foreach ($allFiles as $f) {
    $ext = strtolower(pathinfo($f['filename'], PATHINFO_EXTENSION));
    if ((int)$f['is_index'] === 1) continue;
    if (in_array($ext, $imgExts)) {
        $siteImages[] = array('name' => $f['filename'], 'url' => site_base_url('file.php?name=' . rawurlencode($f['filename'])));
    }
    $linkUrl = ($ext === 'php')
        ? site_base_url('page.php?name=' . rawurlencode($f['filename']))
        : site_base_url('file.php?name=' . rawurlencode($f['filename']));
    $siteLinks[] = array('name' => $f['filename'], 'url' => $linkUrl);
}
?>
<!DOCTYPE html>
<html>
<head>
<title>Appearance Editor · Admin</title>
<link rel="stylesheet" href="../assets/style.css">
</head>
<body>
    <div class="admin-wrapper">
        <nav class="admin-nav">
            <h2>Panel Admin</h2>
            <a href="index.php">Dashboard</a>
            <a href="files.php">File Manager</a>
            <a href="downloads.php">Downloads Manager</a>
            <a href="editor.php" class="active">Appearance</a>
            <a href="settings.php">Site Settings</a>
            <a href="../index.php" target="_blank">View Site</a>
            <a href="logout.php">Logout</a>
        </nav>
        <div class="admin-content">
            <h1>Appearance Editor</h1>
            <?php if ($message): ?><div class="alert success"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
            <?php if ($error): ?><div class="alert error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

            <div class="editor-box editor-box-wide">
                <h2>Homepage — Visual Editor</h2>
                <p class="hint">Design your site's main page visually. Saving applies it to the live website instantly. Images you uploaded in the File Manager appear in the Insert picker. Use Code view only if you know HTML.</p>
                <form method="post" id="homeForm">
                    <input type="hidden" name="action" value="save_index">
                    <input type="hidden" name="content" id="homeContentField">
                    <input type="hidden" name="from_visual" id="homeFromVisual" value="1">

                    <div class="ve-modes">
                        <button type="button" class="ve-mode-btn active" data-mode="visual" onclick="setHomeMode('visual')">Visual</button>
                        <button type="button" class="ve-mode-btn" data-mode="code" onclick="setHomeMode('code')">Code</button>
                    </div>

                    <div class="ve-toolbar" id="veToolbar">
                        <button type="button" onclick="vc('undo')" title="Undo">&#8630;</button>
                        <button type="button" onclick="vc('redo')" title="Redo">&#8631;</button>
                        <span class="ve-sep"></span>
                        <select onchange="vc('formatBlock', this.value)">
                            <option value="&lt;p&gt;">Paragraph</option>
                            <option value="&lt;h1&gt;">Heading 1</option>
                            <option value="&lt;h2&gt;">Heading 2</option>
                            <option value="&lt;h3&gt;">Heading 3</option>
                            <option value="&lt;h4&gt;">Heading 4</option>
                            <option value="&lt;blockquote&gt;">Quote</option>
                            <option value="&lt;pre&gt;">Preformatted</option>
                        </select>
                        <span class="ve-sep"></span>
                        <button type="button" onclick="vc('bold')" title="Bold"><b>B</b></button>
                        <button type="button" onclick="vc('italic')" title="Italic"><i>I</i></button>
                        <button type="button" onclick="vc('underline')" title="Underline"><u>U</u></button>
                        <button type="button" onclick="vc('strikeThrough')" title="Strikethrough"><s>S</s></button>
                        <span class="ve-sep"></span>
                        <button type="button" onclick="vc('insertUnorderedList')" title="Bullet list">&bull; List</button>
                        <button type="button" onclick="vc('insertOrderedList')" title="Numbered list">1. List</button>
                        <span class="ve-sep"></span>
                        <button type="button" onclick="vc('justifyLeft')" title="Align left">&larr;</button>
                        <button type="button" onclick="vc('justifyCenter')" title="Center">&harr;</button>
                        <button type="button" onclick="vc('justifyRight')" title="Align right">&rarr;</button>
                        <span class="ve-sep"></span>
                        <input type="color" class="ve-color" title="Text color" onchange="vc('foreColor', this.value)">
                        <input type="color" class="ve-color" title="Highlight" onchange="vc('hiliteColor', this.value)">
                        <span class="ve-sep"></span>
                        <button type="button" onclick="openLinkPicker()" title="Add link">Link</button>
                        <button type="button" onclick="vc('unlink')" title="Remove link">Unlink</button>
                        <button type="button" onclick="openImagePicker()" title="Insert image">Image</button>
                        <button type="button" onclick="vc('insertHorizontalRule')" title="Divider line">&mdash;</button>
                        <button type="button" onclick="vc('removeFormat')" title="Clear formatting">Clear</button>
                    </div>

                    <div id="veCanvas" class="ve-canvas" contenteditable="true"><?php echo htmlspecialchars($indexBody, ENT_QUOTES, 'UTF-8', false); ?></div>
                    <textarea name="code_view" id="homeCodeView" class="code-editor" rows="18" spellcheck="false" style="display:none"><?php echo htmlspecialchars($indexContent); ?></textarea>

                    <div style="margin-top:12px;">
                        <button type="submit">Save &amp; Publish</button>
                        <a href="../index.php" target="_blank" class="btn">Preview Live Site</a>
                    </div>
                </form>
            </div>

            <div class="editor-boxes">

                <div class="editor-box">
                    <h2>Main Stylesheet (style.css)</h2>
                    <p class="hint">The base theme used across the whole site.</p>
                    <form method="post">
                        <input type="hidden" name="action" value="save_style">
                        <textarea name="content" class="code-editor" rows="18" spellcheck="false"><?php echo htmlspecialchars($styleContent); ?></textarea>
                        <button type="submit">Save style.css</button>
                    </form>
                </div>

                <div class="editor-box">
                    <h2>Custom CSS (extra stylesheet)</h2>
                    <p class="hint">Loaded AFTER style.css on every public page — customize here instead of touching the main CSS file.</p>
                    <form method="post">
                        <input type="hidden" name="action" value="save_custom">
                        <textarea name="content" class="code-editor" rows="18" spellcheck="false"><?php echo htmlspecialchars($customContent); ?></textarea>
                        <button type="submit">Save Custom CSS</button>
                    </form>
                </div>

            </div>
        </div>
    </div>

    <div id="pickModal" class="modal" style="display:none;">
        <div class="modal-content">
            <span class="close" onclick="closePick()">&times;</span>
            <h3 id="pickTitle"></h3>
            <div id="pickGrid" class="picker-grid"></div>
            <label>Or paste a URL</label>
            <input type="text" id="pickUrl" placeholder="https://...">
            <button type="button" onclick="pickOk()">Insert</button>
            <button type="button" onclick="closePick()">Cancel</button>
        </div>
    </div>

    <script>
        var homeMode = 'visual';
        var siteImages = <?php echo json_encode($siteImages, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
        var siteLinks = <?php echo json_encode($siteLinks, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
        var pickCallback = null;

        try { document.execCommand('styleWithCSS', false, true); } catch (e) {}

        function vc(cmd, val) {
            var canvas = document.getElementById('veCanvas');
            canvas.focus();
            document.execCommand(cmd, false, val || null);
        }

        function setHomeMode(mode) {
            homeMode = mode;
            var canvas = document.getElementById('veCanvas');
            var code = document.getElementById('homeCodeView');
            var toolbar = document.getElementById('veToolbar');
            var btns = document.querySelectorAll('.ve-mode-btn');
            for (var i = 0; i < btns.length; i++) {
                btns[i].classList.toggle('active', btns[i].getAttribute('data-mode') === mode);
            }
            if (mode === 'code') {
                code.value = canvas.innerHTML;
                toolbar.style.display = 'none';
                canvas.style.display = 'none';
                code.style.display = 'block';
            } else {
                canvas.innerHTML = code.value;
                code.style.display = 'none';
                toolbar.style.display = 'flex';
                canvas.style.display = 'block';
            }
        }

        document.getElementById('homeForm').addEventListener('submit', function () {
            var field = document.getElementById('homeContentField');
            if (homeMode === 'visual') {
                document.getElementById('homeFromVisual').value = '1';
                field.value = document.getElementById('veCanvas').innerHTML.replace(/<\?php[\s\S]*?\?>/gi, '').replace(/<\?=/g, '');
            } else {
                document.getElementById('homeFromVisual').value = '0';
                field.value = document.getElementById('homeCodeView').value;
            }
        });

        function openPick(title, items, thumbs, cb) {
            pickCallback = cb;
            document.getElementById('pickTitle').textContent = title;
            var grid = document.getElementById('pickGrid');
            grid.innerHTML = '';
            document.getElementById('pickUrl').value = '';
            for (var i = 0; i < items.length; i++) {
                (function (item) {
                    var div = document.createElement('div');
                    div.className = 'picker-item';
                    if (thumbs) {
                        var img = document.createElement('img');
                        img.src = item.url;
                        img.alt = item.name;
                        div.appendChild(img);
                    }
                    var label = document.createElement('div');
                    label.textContent = item.name;
                    div.appendChild(label);
                    div.onclick = function () { pickCallback(item.url); closePick(); };
                    grid.appendChild(div);
                })(items[i]);
            }
            document.getElementById('pickModal').style.display = 'block';
        }
        function closePick() {
            document.getElementById('pickModal').style.display = 'none';
            pickCallback = null;
        }
        function pickOk() {
            var url = document.getElementById('pickUrl').value.trim();
            if (url && pickCallback) pickCallback(url);
            closePick();
        }
        function openImagePicker() {
            openPick('Insert Image (from your uploaded files)', siteImages, true, function (url) { vc('insertImage', url); });
        }
        function openLinkPicker() {
            openPick('Insert Link (to your uploaded files)', siteLinks, false, function (url) { vc('createLink', url); });
        }
    </script>
</body>
</html>
