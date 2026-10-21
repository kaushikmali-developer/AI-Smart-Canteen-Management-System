<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role('ADMIN', 'MANAGER');

$pdo = db();

$days = max(7, min(365, (int)($_GET['days'] ?? 30)));
$start = date('Y-m-d', strtotime("-" . ($days - 1) . " days"));
$end = date('Y-m-d');

$topStmt = $pdo->prepare(
    "SELECT oi.item_id, oi.item_name,
            SUM(oi.quantity) AS units_sold,
            SUM(oi.subtotal) AS revenue
     FROM order_items oi
     INNER JOIN orders o ON o.order_id = oi.order_id
     WHERE o.order_date >= ?
       AND o.order_date < DATE_ADD(?, INTERVAL 1 DAY)
       AND o.payment_status = 'PAID'
       AND o.order_status <> 'CANCELLED'
     GROUP BY oi.item_id, oi.item_name
     ORDER BY units_sold DESC
     LIMIT 10"
);
$topStmt->execute([$start, $end]);
$topItems = $topStmt->fetchAll();

$lowStock = $pdo->query(
    "SELECT inventory_id, ingredient_name, unit, current_stock, minimum_stock, maximum_stock
     FROM inventory
     WHERE is_active = 1
       AND current_stock <= minimum_stock
     ORDER BY (minimum_stock - current_stock) DESC, ingredient_name"
)->fetchAll();

$recipeStmt = $pdo->prepare(
    "SELECT m.item_name, i.ingredient_name, mii.quantity_required, i.unit
     FROM menu_item_ingredients mii
     INNER JOIN menu_items m ON m.item_id = mii.item_id
     INNER JOIN inventory i ON i.inventory_id = mii.inventory_id
     WHERE m.is_available = 1
     ORDER BY m.item_name, i.ingredient_name"
);
$recipeStmt->execute();
$recipes = $recipeStmt->fetchAll();

$recipeMap = [];
foreach ($recipes as $recipe) {
    $recipeMap[$recipe['item_name']][] = $recipe;
}

$page_title = 'AI Recommendations';
require __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="mb-4">
        <h2 class="mb-1">AI Recommendations</h2>
        <p class="text-muted mb-0">Rule-based recommendations grounded in sales, recipes and stock.</p>
    </div>

    <div class="alert alert-info">
        Recommendations below are generated from database facts. They are operational
        suggestions, not invented forecasts.
    </div>

    <div class="row g-4">
        <div class="col-xl-7">
            <div class="card shadow-sm">
                <div class="card-header"><strong>Menu Demand Signals</strong></div>
                <div class="card-body">
                    <?php if (!$topItems): ?>
                        <p class="text-muted mb-0">Not enough sales data yet.</p>
                    <?php else: ?>
                        <?php foreach ($topItems as $index => $item): ?>
                            <div class="border rounded p-3 mb-3">
                                <div class="d-flex justify-content-between">
                                    <strong><?= e($item['item_name']) ?></strong>
                                    <span class="badge text-bg-success">
                                        <?= number_format((float)$item['units_sold'], 0) ?> units
                                    </span>
                                </div>
                                <div class="small text-muted mt-1">
                                    Sales: ₹<?= number_format((float)$item['revenue'], 2) ?>
                                </div>

                                <?php if (!empty($recipeMap[$item['item_name']])): ?>
                                    <div class="small mt-2">
                                        <strong>Recipe demand:</strong>
                                        <?php
                                        $parts = [];
                                        foreach ($recipeMap[$item['item_name']] as $recipe) {
                                            $parts[] = e($recipe['ingredient_name']) . ' × ' .
                                                e($recipe['quantity_required']) . ' ' . e($recipe['unit']);
                                        }
                                        echo implode(', ', $parts);
                                        ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-xl-5">
            <div class="card shadow-sm h-100">
                <div class="card-header"><strong>Inventory Actions</strong></div>
                <div class="card-body">
                    <?php if (!$lowStock): ?>
                        <div class="alert alert-success mb-0">
                            No ingredients are currently at or below minimum stock.
                        </div>
                    <?php else: ?>
                        <?php foreach ($lowStock as $item): ?>
                            <div class="border rounded p-3 mb-3">
                                <strong><?= e($item['ingredient_name']) ?></strong>
                                <div class="small mt-1">
                                    Current:
                                    <strong><?= e($item['current_stock']) ?> <?= e($item['unit']) ?></strong>
                                    · Minimum:
                                    <?= e($item['minimum_stock']) ?> <?= e($item['unit']) ?>
                                </div>
                                <div class="mt-2">
                                    <span class="badge text-bg-warning">
                                        Suggested action: review/reorder stock
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
