<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role('STUDENT');

$pdo = db();
$userId = current_user_id();

$cartStmt = $pdo->prepare(
    'SELECT COALESCE(SUM(quantity), 0)
     FROM cart_items ci
     INNER JOIN carts c ON c.cart_id = ci.cart_id
     WHERE c.user_id = ?'
);
$cartStmt->execute([$userId]);
$cartCount = (int) $cartStmt->fetchColumn();

$orderStmt = $pdo->prepare(
    "SELECT COUNT(*)
     FROM orders
     WHERE user_id = ? AND order_status NOT IN ('COMPLETED', 'CANCELLED')"
);
$orderStmt->execute([$userId]);
$activeOrders = (int) $orderStmt->fetchColumn();

$recentStmt = $pdo->prepare(
    'SELECT order_id, order_number, order_date, total_amount, order_status
     FROM orders
     WHERE user_id = ?
     ORDER BY order_date DESC
     LIMIT 5'
);
$recentStmt->execute([$userId]);
$recentOrders = $recentStmt->fetchAll();

$page_title = 'Student Dashboard';
require __DIR__ . '/../includes/header.php';
?>

<section class="premium-hero"><div class="section-kicker" style="color:#8ce8c8">Good food. Better days.</div><h1 class="h2 mb-2">Your canteen, made <span class="accent">smarter.</span></h1><p class="mb-0" style="max-width:620px;color:#d5e8e2">Discover meals, manage your cart and keep track of every order in one place.</p></section>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <div>
        <h1 class="h3 fw-bold mb-1">Welcome, <?= e(current_user()['full_name']) ?> 👋</h1>
        <p class="text-secondary mb-0">Order food, manage your cart and track your canteen orders.</p>
    </div>
    <a class="btn btn-primary" href="/SmartCanteenAI/student/menu.php">
        <i class="bi bi-grid me-1"></i>Browse Menu
    </a>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-4">
        <a class="text-decoration-none" href="/SmartCanteenAI/student/menu.php">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-secondary small">Food Menu</div>
                    <div class="fs-4 fw-bold mt-1">Browse & Order</div>
                    <div class="text-primary mt-2">View available items →</div>
                </div>
            </div>
        </a>
    </div>
    <div class="col-md-4">
        <a class="text-decoration-none" href="/SmartCanteenAI/student/cart.php">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-secondary small">Cart Items</div>
                    <div class="fs-4 fw-bold mt-1"><?= $cartCount ?></div>
                    <div class="text-primary mt-2">Open your cart →</div>
                </div>
            </div>
        </a>
    </div>
    <div class="col-md-4">
        <a class="text-decoration-none" href="/SmartCanteenAI/student/orders.php">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-secondary small">Active Orders</div>
                    <div class="fs-4 fw-bold mt-1"><?= $activeOrders ?></div>
                    <div class="text-primary mt-2">Track orders →</div>
                </div>
            </div>
        </a>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white py-3">
        <h2 class="h5 mb-0">Recent Orders</h2>
    </div>
    <?php if ($recentOrders === []): ?>
        <div class="card-body text-center py-5">
            <p class="text-secondary mb-3">You haven't placed an order yet.</p>
            <a class="btn btn-primary" href="/SmartCanteenAI/student/menu.php">Start Your First Order</a>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th>Order</th>
                        <th>Date</th>
                        <th>Status</th>
                        <th class="text-end">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentOrders as $order): ?>
                        <tr>
                            <td>
                                <a class="fw-semibold text-decoration-none"
                                   href="/SmartCanteenAI/student/orders.php?order_id=<?= (int) $order['order_id'] ?>">
                                    <?= e($order['order_number']) ?>
                                </a>
                            </td>
                            <td><?= e(date('d M Y, h:i A', strtotime((string) $order['order_date']))) ?></td>
                            <td><span class="badge text-bg-primary"><?= e($order['order_status']) ?></span></td>
                            <td class="text-end">₹<?= number_format((float) $order['total_amount'], 2) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
