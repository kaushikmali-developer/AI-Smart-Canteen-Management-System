<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role('ADMIN');

$pdo = db();

$stats = [];

$stats['orders'] = (int)$pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn();
$stats['order_items'] = (int)$pdo->query("SELECT COUNT(*) FROM order_items")->fetchColumn();
$stats['paid_orders'] = (int)$pdo->query(
    "SELECT COUNT(*) FROM orders WHERE payment_status = 'PAID' AND order_status <> 'CANCELLED'"
)->fetchColumn();

$stats['revenue'] = (float)$pdo->query(
    "SELECT COALESCE(SUM(total_amount), 0)
     FROM orders
     WHERE payment_status = 'PAID'
       AND order_status <> 'CANCELLED'"
)->fetchColumn();

$stats['first_order'] = $pdo->query(
    "SELECT MIN(order_date) FROM orders"
)->fetchColumn();

$stats['last_order'] = $pdo->query(
    "SELECT MAX(order_date) FROM orders"
)->fetchColumn();

$page_title = 'Dataset Statistics';
require __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="mb-4">
        <h2 class="mb-1">Dataset Statistics</h2>
        <p class="text-muted mb-0">Quick verification of the current sales dataset.</p>
    </div>

    <div class="row g-3">
        <?php
        $cards = [
            ['Orders', number_format($stats['orders'])],
            ['Order Items', number_format($stats['order_items'])],
            ['Paid Sales Orders', number_format($stats['paid_orders'])],
            ['Revenue', '₹' . number_format($stats['revenue'], 2)]
        ];
        foreach ($cards as [$label, $value]):
        ?>
            <div class="col-sm-6 col-xl-3">
                <div class="card shadow-sm h-100">
                    <div class="card-body">
                        <div class="text-muted small"><?= e($label) ?></div>
                        <div class="fs-3 fw-semibold"><?= e($value) ?></div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="card shadow-sm mt-4">
        <div class="card-body">
            <strong>Order date range:</strong>
            <?= e($stats['first_order'] ?: 'No orders') ?>
            &nbsp; → &nbsp;
            <?= e($stats['last_order'] ?: 'No orders') ?>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
