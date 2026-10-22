<?php
$pageTitle = 'Category Management';
require_once __DIR__ . '/header.php';

$pdo = getDbConnection();
$action = $_GET['action'] ?? $_POST['action'] ?? 'list';

if ($action === 'delete') {
    $id = (int)($_GET['id'] ?? 0);
    if ($id > 0) {
        $pdo->prepare("DELETE FROM categories WHERE id = ?")->execute([$id]);
    }
    header('Location: categories.php');
    exit;
}

if ($action === 'save' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['category_id'] ?? 0);
    $name = sanitizeInput($_POST['name'] ?? '');
    $slug = sanitizeInput($_POST['slug'] ?? strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name))));
    $icon = sanitizeInput($_POST['icon'] ?? 'folder');
    $sortOrder = (int)($_POST['sort_order'] ?? 0);

    if ($id > 0) {
        $stmt = $pdo->prepare("UPDATE categories SET name = ?, slug = ?, icon = ?, sort_order = ? WHERE id = ?");
        $stmt->execute([$name, $slug, $icon, $sortOrder, $id]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO categories (name, slug, icon, sort_order, created_at) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$name, $slug, $icon, $sortOrder, date('Y-m-d H:i:s')]);
    }
    header('Location: categories.php');
    exit;
}

$categories = $pdo->query("
    SELECT c.*, COUNT(a.id) as app_count
    FROM categories c
    LEFT JOIN apps a ON c.id = a.category_id
    GROUP BY c.id
    ORDER BY c.sort_order ASC
")->fetchAll();
?>

<div style="display: grid; grid-template-columns: 1fr 2fr; gap: 24px;">
    <!-- Add / Edit Category Form -->
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">+ Add New Category</h2>
        </div>
        <div class="card-body">
            <form method="POST" action="categories.php">
                <input type="hidden" name="action" value="save">
                <div class="form-group">
                    <label class="form-label">Category Name *</label>
                    <input type="text" name="name" class="form-control" placeholder="e.g. Best of week" required>
                </div>
                <div class="form-group">
                    <label class="form-label">URL Slug</label>
                    <input type="text" name="slug" class="form-control" placeholder="e.g. best-of-week">
                </div>
                <div class="form-group">
                    <label class="form-label">Icon Tag</label>
                    <input type="text" name="icon" class="form-control" value="sparkles" placeholder="e.g. sparkles, tv, bookmark">
                </div>
                <div class="form-group">
                    <label class="form-label">Display Order</label>
                    <input type="number" name="sort_order" class="form-control" value="1">
                </div>
                <button type="submit" class="btn btn-primary" style="width: 100%; justify-content: center;">Save Category</button>
            </form>
        </div>
    </div>

    <!-- Categories List -->
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">🗂️ Categories (<?= count($categories) ?>)</h2>
        </div>
        <div class="card-body" style="padding: 0;">
            <div class="table-responsive">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Order</th>
                            <th>Name</th>
                            <th>Slug</th>
                            <th>Apps</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($categories as $cat): ?>
                            <tr>
                                <td><?= $cat['sort_order'] ?></td>
                                <td><strong><?= htmlspecialchars($cat['name']) ?></strong></td>
                                <td style="color: var(--text-secondary);"><?= htmlspecialchars($cat['slug']) ?></td>
                                <td><span class="badge badge-primary"><?= $cat['app_count'] ?> apps</span></td>
                                <td>
                                    <a href="categories.php?action=delete&id=<?= $cat['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Delete category?')">Delete</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
