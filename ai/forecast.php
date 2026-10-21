<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role('ADMIN', 'MANAGER');

$pdo = db();

$days = max(7, min(365, (int)($_GET['days'] ?? 30)));
$forecastDays = max(1, min(14, (int)($_GET['forecast_days'] ?? 7)));

$start = date('Y-m-d', strtotime("-" . ($days - 1) . " days"));
$end = date('Y-m-d');

$stmt = $pdo->prepare(
    "SELECT DATE(order_date) AS sales_date,
            COUNT(*) AS order_count,
            COALESCE(SUM(total_amount), 0) AS revenue
     FROM orders
     WHERE order_date >= ?
       AND order_date < DATE_ADD(?, INTERVAL 1 DAY)
       AND payment_status = 'PAID'
       AND order_status <> 'CANCELLED'
     GROUP BY DATE(order_date)
     ORDER BY sales_date"
);
$stmt->execute([$start, $end]);
$rows = $stmt->fetchAll();

$byDate = [];
foreach ($rows as $row) {
    $byDate[$row['sales_date']] = [
        'orders' => (int)$row['order_count'],
        'revenue' => (float)$row['revenue']
    ];
}

$dailyRevenue = [];
$dailyOrders = [];
for ($i = 0; $i < $days; $i++) {
    $date = date('Y-m-d', strtotime($start . " +{$i} days"));
    $dailyRevenue[] = $byDate[$date]['revenue'] ?? 0.0;
    $dailyOrders[] = $byDate[$date]['orders'] ?? 0;
}

// Simple transparent baseline model:
// forecast = weighted average of recent daily values.
// This is intentionally explainable and does not claim ML accuracy.
$window = min(7, count($dailyRevenue));
$recentRevenue = array_slice($dailyRevenue, -$window);
$recentOrders = array_slice($dailyOrders, -$window);

$weightedAverage = function(array $values): float {
    if (!$values) return 0.0;
    $weighted = 0.0;
    $weights = 0.0;
    foreach ($values as $index => $value) {
        $weight = $index + 1;
        $weighted += $value * $weight;
        $weights += $weight;
    }
    return $weights > 0 ? $weighted / $weights : 0.0;
};

$forecastRevenue = $weightedAverage($recentRevenue);
$forecastOrders = $weightedAverage($recentOrders);

$forecastDates = [];
$forecastRevenueSeries = [];
$forecastOrderSeries = [];

for ($i = 1; $i <= $forecastDays; $i++) {
    $date = date('Y-m-d', strtotime($end . " +{$i} days"));
    $forecastDates[] = $date;
    $forecastRevenueSeries[] = round($forecastRevenue, 2);
    $forecastOrderSeries[] = round($forecastOrders, 1);
}

$topItemsStmt = $pdo->prepare(
    "SELECT oi.item_name,
            SUM(oi.quantity) AS units_sold,
            SUM(oi.subtotal) AS sales
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
$topItemsStmt->execute([$start, $end]);
$topItems = $topItemsStmt->fetchAll();

$lowStock = $pdo->query(
    "SELECT inventory_id, ingredient_name, unit, current_stock, minimum_stock
     FROM inventory
     WHERE is_active = 1
       AND current_stock <= minimum_stock
     ORDER BY current_stock ASC
     LIMIT 20"
)->fetchAll();

$page_title = 'AI Demand Forecast';
require __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
        <div>
            <h2 class="mb-1">AI Demand Forecast</h2>
            <p class="text-muted mb-0">Explainable baseline forecasting from historical sales.</p>
        </div>
        <span class="badge text-bg-primary">AI Analytics Prototype</span>
    </div>

    <div class="alert alert-info">
        <strong>How it works:</strong> the current prototype uses a weighted 7-day moving
        average of paid, non-cancelled sales. It is intentionally transparent and
        database-grounded. It does not invent values or claim predictive accuracy.
    </div>

    <form class="card card-body shadow-sm mb-4" method="get">
        <div class="row g-3 align-items-end">
            <div class="col-md-4">
                <label class="form-label">Historical Days</label>
                <select name="days" class="form-select">
                    <?php foreach ([14, 30, 60, 90, 180, 365] as $option): ?>
                        <option value="<?= $option ?>" <?= $days === $option ? 'selected' : '' ?>>
                            <?= $option ?> days
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Forecast Days</label>
                <select name="forecast_days" class="form-select">
                    <?php foreach ([3, 7, 14] as $option): ?>
                        <option value="<?= $option ?>" <?= $forecastDays === $option ? 'selected' : '' ?>>
                            <?= $option ?> days
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <button class="btn btn-primary w-100">Generate Forecast</button>
            </div>
        </div>
    </form>

    <div class="row g-3 mb-4">
        <div class="col-md-6">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small">Expected Daily Revenue</div>
                    <div class="fs-2 fw-bold">₹<?= number_format($forecastRevenue, 2) ?></div>
                    <small class="text-muted">Weighted recent-sales baseline</small>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small">Expected Daily Orders</div>
                    <div class="fs-2 fw-bold"><?= number_format($forecastOrders, 1) ?></div>
                    <small class="text-muted">Weighted recent-orders baseline</small>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-header"><strong>Forecast</strong></div>
        <div class="card-body">
            <canvas id="forecastChart" height="100"></canvas>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-xl-7">
            <div class="card shadow-sm h-100">
                <div class="card-header"><strong>Top Selling Items</strong></div>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead><tr><th>Item</th><th>Units</th><th>Sales</th></tr></thead>
                        <tbody>
                        <?php foreach ($topItems as $item): ?>
                            <tr>
                                <td><?= e($item['item_name']) ?></td>
                                <td><?= number_format((float)$item['units_sold'], 0) ?></td>
                                <td>₹<?= number_format((float)$item['sales'], 2) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$topItems): ?>
                            <tr><td colspan="3" class="text-center text-muted">No sales data.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-xl-5">
            <div class="card shadow-sm h-100">
                <div class="card-header"><strong>Low Stock Watch</strong></div>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead><tr><th>Ingredient</th><th>Stock</th><th>Minimum</th></tr></thead>
                        <tbody>
                        <?php foreach ($lowStock as $item): ?>
                            <tr>
                                <td><?= e($item['ingredient_name']) ?></td>
                                <td><?= e($item['current_stock']) ?> <?= e($item['unit']) ?></td>
                                <td><?= e($item['minimum_stock']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$lowStock): ?>
                            <tr><td colspan="3" class="text-center text-muted">No low-stock items.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
new Chart(document.getElementById('forecastChart'), {
    type: 'line',
    data: {
        labels: <?= json_encode($forecastDates) ?>,
        datasets: [{
            label: 'Forecast Revenue (₹)',
            data: <?= json_encode($forecastRevenueSeries) ?>,
            tension: 0.25
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { display: true } },
        scales: { y: { beginAtZero: true } }
    }
});
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>
