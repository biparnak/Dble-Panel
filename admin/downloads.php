<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create_card') {
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $slug = slugify($_POST['slug'] ?? '');
        $custom_css = $_POST['custom_css'] ?? '';
        if ($title === '') {
            $error = 'Title is required.';
        } else {
            if ($slug !== '') {
                $check = get_download_card_by_slug($slug);
                if ($check) $slug = '';
            }
            $stmt = $pdo->prepare("INSERT INTO download_cards (title, description, slug, custom_css) VALUES (?, ?, ?, ?)");
            $stmt->execute([$title, $description, $slug ?: null, $custom_css]);
            $message = 'Card created.';
        }
    } elseif ($action === 'update_card') {
        $id = (int)$_POST['id'];
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $slug = slugify($_POST['slug'] ?? '');
        $custom_css = $_POST['custom_css'] ?? '';
        if ($title === '') {
            $error = 'Title is required.';
        } else {
            if ($slug !== '') {
                $check = get_download_card_by_slug($slug);
                if ($check && (int)$check['id'] !== $id) $slug = '';
            }
            $stmt = $pdo->prepare("UPDATE download_cards SET title = ?, description = ?, slug = ?, custom_css = ? WHERE id = ?");
            $stmt->execute([$title, $description, $slug ?: null, $custom_css, $id]);
            $message = 'Card updated.';
        }
    } elseif ($action === 'delete_card') {
        $id = (int)$_POST['id'];
        $stmt = $pdo->prepare("DELETE FROM download_cards WHERE id = ?");
        $stmt->execute([$id]);
        $message = 'Card and its versions deleted.';
    }
}

$cards = get_download_cards();
$jsCards = array();
foreach ($cards as $c) {
    $jsCards[] = array(
        'id' => (int)$c['id'],
        'title' => (string)$c['title'],
        'description' => (string)$c['description'],
        'slug' => (string)($c['slug'] ?? ''),
        'css' => (string)($c['custom_css'] ?? '')
    );
}
?>
<!DOCTYPE html>
<html>
<head>
<title>Downloads Manager · Admin</title>
<link rel="stylesheet" href="../assets/style.css">
</head>
<body>
    <div class="admin-wrapper">
        <nav class="admin-nav">
            <h2>Panel Admin</h2>
            <a href="index.php">Dashboard</a>
            <a href="files.php">File Manager</a>
            <a href="downloads.php" class="active">Downloads Manager</a>
            <a href="editor.php">Appearance</a>
            <a href="settings.php">Site Settings</a>
            <a href="../index.php" target="_blank">View Site</a>
            <a href="logout.php">Logout</a>
        </nav>
        <div class="admin-content">
            <h1>Downloads Manager</h1>
            <?php if ($message): ?><div class="alert success"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
            <?php if ($error): ?><div class="alert error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

            <h2>Create New Download Card</h2>
            <form method="post" class="card-form">
                <input type="hidden" name="action" value="create_card">
                <label>Title</label>
                <input type="text" name="title" required>
                <label>Description</label>
                <textarea name="description" rows="3"></textarea>
                <label>Custom URL Slug (optional, e.g. my-app)</label>
                <input type="text" name="slug" placeholder="my-app → /card.php?s=my-app">
                <button type="submit">Create Card</button>
            </form>

            <h2>Download Cards</h2>
            <p class="hint">Each card is its own standalone page. Copy its URL below and use it anywhere on your site or in redirects.</p>
            <div class="cards-list">
                <?php foreach ($cards as $card): ?>
                <?php
                    $cardUrl = site_base_url('card.php?id=' . $card['id']);
                    $slugUrl = !empty($card['slug']) ? site_base_url('card.php?s=' . urlencode($card['slug'])) : '';
                ?>
                <div class="card-item">
                    <div class="card-header">
                        <h3>#<?php echo (int)$card['id']; ?> · <?php echo htmlspecialchars($card['title']); ?></h3>
                        <div class="card-actions">
                            <button onclick="openEditCard(<?php echo (int)$card['id']; ?>)">Edit / CSS</button>
                            <a href="download_versions.php?card_id=<?php echo $card['id']; ?>" class="btn">Manage Versions</a>
                            <form method="post" style="display:inline" onsubmit="return confirm('Delete this card and all versions?');">
                                <input type="hidden" name="action" value="delete_card">
                                <input type="hidden" name="id" value="<?php echo $card['id']; ?>">
                                <button type="submit" class="danger">Delete</button>
                            </form>
                        </div>
                    </div>
                    <p><?php echo nl2br(htmlspecialchars($card['description'])); ?></p>
                    <div class="url-row">
                        <input type="text" readonly value="<?php echo htmlspecialchars($cardUrl); ?>" onclick="this.select()">
                        <button type="button" class="copy-btn" onclick="copyUrl(this)">Copy</button>
                        <a href="../card.php?id=<?php echo $card['id']; ?>" target="_blank" class="btn">Open</a>
                    </div>
                    <?php if ($slugUrl): ?>
                    <div class="url-row">
                        <input type="text" readonly value="<?php echo htmlspecialchars($slugUrl); ?>" onclick="this.select()">
                        <button type="button" class="copy-btn" onclick="copyUrl(this)">Copy</button>
                        <a href="../card.php?s=<?php echo urlencode($card['slug']); ?>" target="_blank" class="btn">Open</a>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Edit Card Modal -->
    <div id="editCardModal" class="modal" style="display:none;">
        <div class="modal-content">
            <span class="close" onclick="closeEditCard()">&times;</span>
            <h2>Edit Card</h2>
            <form method="post">
                <input type="hidden" name="action" value="update_card">
                <input type="hidden" name="id" id="edit_card_id">
                <label>Title</label>
                <input type="text" name="title" id="edit_card_title" required>
                <label>Description</label>
                <textarea name="description" id="edit_card_description" rows="4"></textarea>
                <label>Custom URL Slug (optional)</label>
                <input type="text" name="slug" id="edit_card_slug" placeholder="my-app">
                <label>This Page's Custom CSS (loaded only on this card's page)</label>
                <textarea name="custom_css" id="edit_card_css" rows="8" class="code-editor" spellcheck="false" placeholder=".download-page { background: #111; color: #fff; }"></textarea>
                <button type="submit">Save</button>
                <button type="button" onclick="closeEditCard()">Cancel</button>
            </form>
        </div>
    </div>

    <script>
        var cardsData = <?php echo json_encode($jsCards, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;

        function openEditCard(id) {
            var card = null;
            for (var i = 0; i < cardsData.length; i++) {
                if (cardsData[i].id === id) { card = cardsData[i]; break; }
            }
            if (!card) return;
            document.getElementById('edit_card_id').value = card.id;
            document.getElementById('edit_card_title').value = card.title;
            document.getElementById('edit_card_description').value = card.description;
            document.getElementById('edit_card_slug').value = card.slug;
            document.getElementById('edit_card_css').value = card.css;
            document.getElementById('editCardModal').style.display = 'block';
        }
        function closeEditCard() {
            document.getElementById('editCardModal').style.display = 'none';
        }
        function copyUrl(btn) {
            var input = btn.parentElement.querySelector('input');
            input.select();
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(input.value).then(function () {
                    btn.textContent = 'Copied!';
                    setTimeout(function () { btn.textContent = 'Copy'; }, 1500);
                });
            } else {
                document.execCommand('copy');
                btn.textContent = 'Copied!';
                setTimeout(function () { btn.textContent = 'Copy'; }, 1500);
            }
        }
    </script>
</body>
</html>
