<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role('STUDENT');

$pdo = db();
$userId = current_user_id();
$orderId = filter_input(INPUT_GET, 'order_id', FILTER_VALIDATE_INT) ?: 0;

if ($orderId > 0) {
    $stmt = $pdo->prepare(
        'SELECT o.order_id, o.order_number, o.order_date, o.subtotal,
                o.discount_amount, o.tax_rate, o.tax_amount, o.total_amount,
                o.payment_status, o.order_status, o.payment_method, o.notes,
                ofr.title AS offer_title
         FROM orders o
         LEFT JOIN offers ofr ON ofr.offer_id = o.offer_id
         WHERE o.order_id = ? AND o.user_id = ?
         LIMIT 1'
    );
    $stmt->execute([$orderId, $userId]);
    $selectedOrder = $stmt->fetch();

    if ($selectedOrder) {
        $itemStmt = $pdo->prepare(
            'SELECT item_name, quantity, unit_price, subtotal
             FROM order_items
             WHERE order_id = ?
             ORDER BY order_item_id'
        );
        $itemStmt->execute([$orderId]);
        $selectedItems = $itemStmt->fetchAll();
    }
}

$stmt = $pdo->prepare(
    'SELECT order_id, order_number, order_date, total_amount,
            payment_status, order_status, payment_method
     FROM orders
     WHERE user_id = ?
     ORDER BY order_date DESC
     LIMIT 50'
);
$stmt->execute([$userId]);
$orders = $stmt->fetchAll();

$page_title = 'My Orders';
require __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <div>
        <h1 class="h3 fw-bold mb-1">My Orders</h1>
        <p class="text-secondary mb-0">Track your recent canteen orders.</p>
    </div>
    <a class="btn btn-primary" href="/SmartCanteenAI/student/menu.php">
        <i class="bi bi-plus-lg me-1"></i>New Order
    </a>
</div>

<?php if ($selectedOrder): ?>
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <div class="d-flex flex-wrap justify-content-between gap-3">
                <div>
                    <span class="small text-secondary">Order</span>
                    <h2 class="h4 mb-1"><?= e($selectedOrder['order_number']) ?></h2>
                    <div class="small text-secondary">
                        <?= e(date('d M Y, h:i A', strtotime((string) $selectedOrder['order_date']))) ?>
                    </div>
                </div>
                <div class="text-end">
                    <span class="badge text-bg-primary"><?= e($selectedOrder['order_status']) ?></span>
                    <div class="fw-bold fs-4 mt-2">₹<?= number_format((float) $selectedOrder['total_amount'], 2) ?></div>
                </div>
            </div>

            <div class="row g-3 mt-2">
                <div class="col-md-8">
                    <?php foreach ($selectedItems as $item): ?>
                        <div class="d-flex justify-content-between border-bottom py-2">
                            <span><?= e($item['item_name']) ?> × <?= (int) $item['quantity'] ?></span>
                            <span>₹<?= number_format((float) $item['subtotal'], 2) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="col-md-4">
                    <div class="small text-secondary">Payment</div>
                    <div><?= e($selectedOrder['payment_method']) ?> · <?= e($selectedOrder['payment_status']) ?></div>
                    <?php if ($selectedOrder['offer_title']): ?>
                        <div class="small text-success mt-2">
                            Offer: <?= e($selectedOrder['offer_title']) ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
<?php elseif ($orderId > 0): ?>
    <div class="alert alert-warning">That order was not found in your account.</div>
<?php endif; ?>

<?php if ($orders === []): ?>
    <div class="card border-0 shadow-sm">
        <div class="card-body text-center py-5">
            <i class="bi bi-receipt display-5 text-secondary"></i>
            <h2 class="h5 mt-3">No orders yet</h2>
            <p class="text-secondary">Your completed checkout orders will appear here.</p>
            <a class="btn btn-primary" href="/SmartCanteenAI/student/menu.php">Browse Menu</a>
        </div>
    </div>
<?php else: ?>
    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th>Order</th>
                        <th>Date</th>
                        <th>Status</th>
                        <th>Payment</th>
                        <th class="text-end">Total</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orders as $order): ?>
                        <tr>
                            <td class="fw-semibold"><?= e($order['order_number']) ?></td>
                            <td><?= e(date('d M Y, h:i A', strtotime((string) $order['order_date']))) ?></td>
                            <td>
                                <span class="badge text-bg-<?= $order['order_status'] === 'COMPLETED' ? 'success' : ($order['order_status'] === 'CANCELLED' ? 'danger' : 'primary') ?>">
                                    <?= e($order['order_status']) ?>
                                </span>
                            </td>
                            <td><?= e($order['payment_method']) ?> · <?= e($order['payment_status']) ?></td>
                            <td class="text-end">₹<?= number_format((float) $order['total_amount'], 2) ?></td>
                            <td class="text-end">
                                <a class="btn btn-sm btn-outline-primary"
                                   href="?order_id=<?= (int) $order['order_id'] ?>">View</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
