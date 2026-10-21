<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_role('ADMIN');
$pageTitle = 'Categories';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf((string)($_POST['csrf_token'] ?? ''))) {
        set_flash('error', 'Invalid security token.');
        redirect('/SmartCanteenAI/admin/categories.php');
    }
    $action = (string)($_POST['action'] ?? '');
    try {
        if ($action === 'save') {
            $id = (int)($_POST['category_id'] ?? 0);
            $name = trim((string)($_POST['category_name'] ?? ''));
            $description = trim((string)($_POST['description'] ?? ''));
            $sort = max(0, (int)($_POST['sort_order'] ?? 0));
            $active = isset($_POST['is_active']) ? 1 : 0;
            if ($name === '') throw new RuntimeException('Category name is required.');
            if ($id > 0) {
                $stmt = db()->prepare('UPDATE categories SET category_name=?, description=?, sort_order=?, is_active=? WHERE category_id=?');
                $stmt->execute([$name, $description ?: null, $sort, $active, $id]);
                set_flash('success', 'Category updated.');
            } else {
                $stmt = db()->prepare('INSERT INTO categories (category_name, description, sort_order, is_active) VALUES (?,?,?,?)');
                $stmt->execute([$name, $description ?: null, $sort, $active]);
                set_flash('success', 'Category created.');
            }
        } elseif ($action === 'toggle') {
            $id = (int)($_POST['category_id'] ?? 0);
            $stmt = db()->prepare('UPDATE categories SET is_active = 1 - is_active WHERE category_id=?');
            $stmt->execute([$id]);
            set_flash('success', 'Category status updated.');
        }
    } catch (Throwable $e) {
        set_flash('error', str_contains($e->getMessage(), 'Duplicate') ? 'Category name already exists.' : $e->getMessage());
    }
    redirect('/SmartCanteenAI/admin/categories.php');
}

$editId = (int)($_GET['edit'] ?? 0);
$edit = null;
if ($editId) {
    $stmt = db()->prepare('SELECT * FROM categories WHERE category_id=?');
    $stmt->execute([$editId]);
    $edit = $stmt->fetch();
}
$categories = db()->query('SELECT * FROM categories ORDER BY sort_order, category_name')->fetchAll();
include __DIR__ . '/../includes/header.php';
?>
<div class="container-fluid py-4">
<div class="d-flex justify-content-between align-items-center mb-4"><div><h1 class="h3 mb-1">Categories</h1><p class="text-secondary mb-0">Manage menu categories.</p></div></div>
<div class="row g-4">
<div class="col-lg-4"><div class="card border-0 shadow-sm"><div class="card-body">
<h2 class="h5"><?= $edit ? 'Edit Category' : 'Add Category' ?></h2>
<form method="post"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="save"><input type="hidden" name="category_id" value="<?= (int)($edit['category_id'] ?? 0) ?>">
<div class="mb-3"><label class="form-label">Name</label><input class="form-control" name="category_name" required value="<?= e($edit['category_name'] ?? '') ?>"></div>
<div class="mb-3"><label class="form-label">Description</label><textarea class="form-control" name="description" rows="3"><?= e($edit['description'] ?? '') ?></textarea></div>
<div class="mb-3"><label class="form-label">Sort order</label><input class="form-control" type="number" name="sort_order" min="0" value="<?= (int)($edit['sort_order'] ?? 0) ?>"></div>
<div class="form-check mb-3"><input class="form-check-input" type="checkbox" name="is_active" id="catActive" <?= !isset($edit) || !$edit || (int)$edit['is_active'] ? 'checked' : '' ?>><label class="form-check-label" for="catActive">Active</label></div>
<button class="btn btn-primary">Save</button><?php if($edit): ?><a class="btn btn-outline-secondary ms-2" href="/SmartCanteenAI/admin/categories.php">Cancel</a><?php endif; ?>
</form></div></div></div>
<div class="col-lg-8"><div class="card border-0 shadow-sm"><div class="card-body"><div class="table-responsive"><table class="table align-middle"><thead><tr><th>Name</th><th>Order</th><th>Status</th><th></th></tr></thead><tbody>
<?php foreach($categories as $c): ?><tr><td><strong><?= e($c['category_name']) ?></strong><br><small class="text-secondary"><?= e((string)$c['description']) ?></small></td><td><?= (int)$c['sort_order'] ?></td><td><?= (int)$c['is_active'] ? '<span class="badge text-bg-success">Active</span>' : '<span class="badge text-bg-secondary">Inactive</span>' ?></td><td class="text-end"><a class="btn btn-sm btn-outline-primary" href="?edit=<?= (int)$c['category_id'] ?>">Edit</a><form method="post" class="d-inline"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="toggle"><input type="hidden" name="category_id" value="<?= (int)$c['category_id'] ?>"><button class="btn btn-sm btn-outline-secondary">Toggle</button></form></td></tr><?php endforeach; ?>
</tbody></table></div></div></div></div></div></div>
<?php include __DIR__ . '/../includes/footer.php'; ?>