<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role('ADMIN');

$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        set_flash('error', 'Invalid security token. Please try again.');
        header('Location: recipes.php');
        exit;
    }

    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'add') {
            $itemId = (int)($_POST['item_id'] ?? 0);
            $inventoryId = (int)($_POST['inventory_id'] ?? 0);
            $quantity = (float)($_POST['quantity_required'] ?? 0);

            if ($itemId <= 0 || $inventoryId <= 0 || $quantity <= 0) {
                throw new RuntimeException('Select a menu item, ingredient, and enter a quantity greater than zero.');
            }

            $stmt = $pdo->prepare(
                "INSERT INTO menu_item_ingredients
                    (item_id, inventory_id, quantity_required)
                 VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE quantity_required = VALUES(quantity_required)"
            );
            $stmt->execute([$itemId, $inventoryId, $quantity]);

            set_flash('success', 'Recipe ingredient saved successfully.');
        } elseif ($action === 'delete') {
            $recipeId = (int)($_POST['recipe_id'] ?? 0);

            if ($recipeId <= 0) {
                throw new RuntimeException('Invalid recipe entry.');
            }

            $stmt = $pdo->prepare("DELETE FROM menu_item_ingredients WHERE recipe_id = ?");
            $stmt->execute([$recipeId]);

            set_flash('success', 'Recipe ingredient removed.');
        }
    } catch (Throwable $e) {
        set_flash('error', $e->getMessage());
    }

    header('Location: recipes.php');
    exit;
}

$menuItems = $pdo->query(
    "SELECT item_id, item_name, price, is_available
     FROM menu_items
     ORDER BY item_name"
)->fetchAll();

$ingredients = $pdo->query(
    "SELECT inventory_id, ingredient_name, unit, current_stock
     FROM inventory
     WHERE is_active = 1
     ORDER BY ingredient_name"
)->fetchAll();

$recipes = $pdo->query(
    "SELECT
        mii.recipe_id,
        m.item_name,
        i.ingredient_name,
        i.unit,
        mii.quantity_required,
        i.current_stock
     FROM menu_item_ingredients mii
     INNER JOIN menu_items m ON m.item_id = mii.item_id
     INNER JOIN inventory i ON i.inventory_id = mii.inventory_id
     ORDER BY m.item_name, i.ingredient_name"
)->fetchAll();

$page_title = 'Recipe & Ingredient Mapping';
require __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
        <div>
            <h2 class="mb-1">Recipe & Ingredient Mapping</h2>
            <p class="text-muted mb-0">Define how much inventory each menu item consumes.</p>
        </div>
        <span class="badge text-bg-info">Automatic consumption on COMPLETED</span>
    </div>

    <div class="alert alert-info">
        Example: if <strong>Veg Sandwich</strong> uses 2 bread slices and 1 cheese portion,
        enter those quantities here. When an order containing the sandwich is completed,
        the mapped inventory is deducted automatically.
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-header">
            <strong>Add / Update Recipe Ingredient</strong>
        </div>
        <div class="card-body">
            <form method="post" class="row g-3">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="add">

                <div class="col-md-5">
                    <label class="form-label">Menu Item</label>
                    <select name="item_id" class="form-select" required>
                        <option value="">Select menu item</option>
                        <?php foreach ($menuItems as $item): ?>
                            <option value="<?= (int)$item['item_id'] ?>">
                                <?= e($item['item_name']) ?>
                                <?= $item['is_available'] ? '' : ' (Unavailable)' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="form-label">Inventory Ingredient</label>
                    <select name="inventory_id" class="form-select" required>
                        <option value="">Select ingredient</option>
                        <?php foreach ($ingredients as $ingredient): ?>
                            <option value="<?= (int)$ingredient['inventory_id'] ?>">
                                <?= e($ingredient['ingredient_name']) ?>
                                — <?= e($ingredient['unit']) ?>
                                (stock: <?= e($ingredient['current_stock']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-2">
                    <label class="form-label">Qty / Menu Item</label>
                    <input type="number" name="quantity_required" class="form-control"
                           step="0.001" min="0.001" required>
                </div>

                <div class="col-md-1 d-flex align-items-end">
                    <button class="btn btn-primary w-100" type="submit">Save</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header d-flex justify-content-between align-items-center">
            <strong>Current Recipe Mapping</strong>
            <span class="badge text-bg-secondary"><?= count($recipes) ?> mappings</span>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Menu Item</th>
                        <th>Ingredient</th>
                        <th>Qty Required</th>
                        <th>Current Stock</th>
                        <th class="text-end">Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$recipes): ?>
                    <tr>
                        <td colspan="5" class="text-center text-muted py-4">
                            No recipe mappings yet.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($recipes as $recipe): ?>
                        <tr>
                            <td><?= e($recipe['item_name']) ?></td>
                            <td><?= e($recipe['ingredient_name']) ?> <small class="text-muted">(<?= e($recipe['unit']) ?>)</small></td>
                            <td><?= e($recipe['quantity_required']) ?></td>
                            <td><?= e($recipe['current_stock']) ?> <?= e($recipe['unit']) ?></td>
                            <td class="text-end">
                                <form method="post" class="d-inline"
                                      onsubmit="return confirm('Remove this recipe ingredient mapping?');">
                                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="recipe_id" value="<?= (int)$recipe['recipe_id'] ?>">
                                    <button class="btn btn-sm btn-outline-danger" type="submit">
                                        Delete
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
