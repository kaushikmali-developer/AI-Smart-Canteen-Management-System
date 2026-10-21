<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role('STAFF');

$pdo = db();

$countsStmt = $pdo->query(
    "SELECT
        COUNT(*) AS today_orders,
        COALESCE(SUM(CASE WHEN order_status IN ('PLACED','CONFIRMED','PREPARING','READY') THEN 1 ELSE 0 END),0) AS active_orders,
        COALESCE(SUM(CASE WHEN order_status = 'COMPLETED' THEN 1 ELSE 0 END),0) AS completed_orders,
        COALESCE(SUM(CASE WHEN order_status = 'COMPLETED' THEN total_amount ELSE 0 END),0) AS today_revenue
     FROM orders
     WHERE order_date >= CURDATE()
       AND order_date < DATE_ADD(CURDATE(), INTERVAL 1 DAY)"
);
$counts = $countsStmt->fetch() ?: [];

$pendingStmt = $pdo->query(
    "SELECT o.order_id, o.order_number, o.order_date, o.total_amount, o.payment_status,
            o.order_status, u.full_name
     FROM orders o
     INNER JOIN users u ON u.user_id = o.user_id
     WHERE o.order_status IN ('PLACED','CONFIRMED','PREPARING','READY')
     ORDER BY FIELD(o.order_status,'PLACED','CONFIRMED','PREPARING','READY'), o.order_date ASC
     LIMIT 10"
);
$pendingOrders = $pendingStmt->fetchAll();

$page_title = 'Staff Dashboard';
require __DIR__ . '/../includes/header.php';
?>

<section class="premium-hero"><div class="section-kicker" style="color:#8ce8c8">Kitchen operations</div><h1 class="h2 mb-2">Keep every order <span class="accent">moving.</span></h1><p class="mb-0" style="max-width:620px;color:#d5e8e2">A clean operational view for incoming orders, kitchen progress and completed service.</p></section>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <div>
        <h1 class="h3 fw-bold mb-1">Staff Dashboard</h1>
        <p class="text-secondary mb-0">Monitor incoming orders and kitchen progress.</p>
    </div>
    <div class="d-flex gap-2">
        <a class="btn btn-primary" href="/SmartCanteenAI/staff/kitchen.php"><i class="bi bi-fire me-1"></i>Kitchen Board</a>
        <a class="btn btn-outline-primary" href="/SmartCanteenAI/staff/orders.php"><i class="bi bi-receipt me-1"></i>All Orders</a>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3"><div class="card kpi-card border-0 shadow-sm h-100"><div class="card-body"><div class="text-secondary small">Today's Orders</div><div class="fs-3 fw-bold mt-1"><?= (int)($counts['today_orders'] ?? 0) ?></div></div></div></div>
    <div class="col-sm-6 col-xl-3"><div class="card kpi-card border-0 shadow-sm h-100"><div class="card-body"><div class="text-secondary small">Active Orders</div><div class="fs-3 fw-bold mt-1"><?= (int)($counts['active_orders'] ?? 0) ?></div></div></div></div>
    <div class="col-sm-6 col-xl-3"><div class="card kpi-card border-0 shadow-sm h-100"><div class="card-body"><div class="text-secondary small">Completed Today</div><div class="fs-3 fw-bold mt-1"><?= (int)($counts['completed_orders'] ?? 0) ?></div></div></div></div>
    <div class="col-sm-6 col-xl-3"><div class="card kpi-card border-0 shadow-sm h-100"><div class="card-body"><div class="text-secondary small">Completed Revenue</div><div class="fs-3 fw-bold mt-1">₹<?= number_format((float)($counts['today_revenue'] ?? 0), 2) ?></div></div></div></div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
        <h2 class="h5 mb-0">Orders Requiring Attention</h2>
        <a href="/SmartCanteenAI/staff/orders.php" class="small text-decoration-none">View all</a>
    </div>
    <?php if ($pendingOrders === []): ?>
        <div class="card-body text-center py-5 text-secondary">No active orders right now.</div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead><tr><th>Order</th><th>Customer</th><th>Time</th><th>Payment</th><th>Status</th><th class="text-end">Total</th></tr></thead>
                <tbody>
                <?php foreach ($pendingOrders as $order): ?>
                    <tr>
                        <td><a class="fw-semibold text-decoration-none" href="/SmartCanteenAI/staff/orders.php?order_id=<?= (int)$order['order_id'] ?>"><?= e($order['order_number']) ?></a></td>
                        <td><?= e($order['full_name']) ?></td>
                        <td><?= e(date('h:i A', strtotime((string)$order['order_date']))) ?></td>
                        <td><span class="badge <?= $order['payment_status'] === 'PAID' ? 'text-bg-success' : 'text-bg-warning' ?>"><?= e($order['payment_status']) ?></span></td>
                        <td><span class="badge text-bg-primary"><?= e($order['order_status']) ?></span></td>
                        <td class="text-end">₹<?= number_format((float)$order['total_amount'], 2) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
