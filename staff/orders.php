<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role('STAFF');

$pdo = db();
$allowedStatuses = ['PLACED','CONFIRMED','PREPARING','READY','COMPLETED','CANCELLED'];
$transitions = [
    'PLACED' => ['CONFIRMED', 'CANCELLED'],
    'CONFIRMED' => ['PREPARING', 'CANCELLED'],
    'PREPARING' => ['READY', 'CANCELLED'],
    'READY' => ['COMPLETED'],
    'COMPLETED' => [],
    'CANCELLED' => [],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf((string)($_POST['csrf_token'] ?? ''))) {
        set_flash('error', 'Invalid security token. Please try again.');
        redirect('/SmartCanteenAI/staff/orders.php');
    }

    $orderId = (int)($_POST['order_id'] ?? 0);
    $newStatus = strtoupper(trim((string)($_POST['new_status'] ?? '')));

    if ($orderId < 1 || !in_array($newStatus, $allowedStatuses, true)) {
        set_flash('error', 'Invalid order status request.');
        redirect('/SmartCanteenAI/staff/orders.php');
    }

    $stmt = $pdo->prepare('SELECT order_number, user_id, order_status FROM orders WHERE order_id = ? LIMIT 1');
    $stmt->execute([$orderId]);
    $order = $stmt->fetch();

    if (!$order) {
        set_flash('error', 'Order not found.');
        redirect('/SmartCanteenAI/staff/orders.php');
    }

    $currentStatus = (string)$order['order_status'];
    if (!in_array($newStatus, $transitions[$currentStatus] ?? [], true)) {
        set_flash('error', 'That status transition is not allowed.');
        redirect('/SmartCanteenAI/staff/orders.php?order_id=' . $orderId);
    }

    $pdo->beginTransaction();
    try {
        $update = $pdo->prepare('UPDATE orders SET order_status = ? WHERE order_id = ?');
        $update->execute([$newStatus, $orderId]);

        $titles = [
            'CONFIRMED' => 'Order Confirmed',
            'PREPARING' => 'Order Being Prepared',
            'READY' => 'Order Ready',
            'COMPLETED' => 'Order Completed',
            'CANCELLED' => 'Order Cancelled',
        ];
        $messages = [
            'CONFIRMED' => 'Your order ' . $order['order_number'] . ' has been confirmed by the canteen.',
            'PREPARING' => 'Your order ' . $order['order_number'] . ' is now being prepared.',
            'READY' => 'Your order ' . $order['order_number'] . ' is ready for pickup.',
            'COMPLETED' => 'Your order ' . $order['order_number'] . ' has been completed. Thank you!',
            'CANCELLED' => 'Your order ' . $order['order_number'] . ' has been cancelled by the canteen.',
        ];

        $notify = $pdo->prepare(
            'INSERT INTO notifications (user_id, title, message, notification_type, related_order_id)
             VALUES (?, ?, ?, ?, ?)'
        );
        $notify->execute([
            (int)$order['user_id'],
            $titles[$newStatus],
            $messages[$newStatus],
            'ORDER_STATUS',
            $orderId,
        ]);

        $pdo->commit();
        set_flash('success', 'Order ' . $order['order_number'] . ' moved to ' . $newStatus . '.');
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        set_flash('error', 'Could not update the order. Please try again.');
    }

    redirect('/SmartCanteenAI/staff/orders.php?order_id=' . $orderId);
}

$statusFilter = strtoupper(trim((string)($_GET['status'] ?? '')));
if ($statusFilter !== '' && !in_array($statusFilter, $allowedStatuses, true)) {
    $statusFilter = '';
}

$where = [];
$params = [];
if ($statusFilter !== '') {
    $where[] = 'o.order_status = ?';
    $params[] = $statusFilter;
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$stmt = $pdo->prepare(
    "SELECT o.order_id, o.order_number, o.order_date, o.subtotal, o.discount_amount,
            o.tax_amount, o.total_amount, o.payment_status, o.order_status,
            o.payment_method, u.full_name, u.email, u.phone
     FROM orders o
     INNER JOIN users u ON u.user_id = o.user_id
     $whereSql
     ORDER BY o.order_date DESC
     LIMIT 100"
);
$stmt->execute($params);
$orders = $stmt->fetchAll();

$selectedOrder = null;
$selectedItems = [];
$selectedId = (int)($_GET['order_id'] ?? 0);
if ($selectedId > 0) {
    $detail = $pdo->prepare(
        'SELECT o.*, u.full_name, u.email, u.phone
         FROM orders o INNER JOIN users u ON u.user_id = o.user_id
         WHERE o.order_id = ? LIMIT 1'
    );
    $detail->execute([$selectedId]);
    $selectedOrder = $detail->fetch() ?: null;

    if ($selectedOrder) {
        $items = $pdo->prepare(
            'SELECT item_name, quantity, unit_price, subtotal
             FROM order_items WHERE order_id = ? ORDER BY order_item_id'
        );
        $items->execute([$selectedId]);
        $selectedItems = $items->fetchAll();
    }
}

$flashSuccess = get_flash('success');
$flashError = get_flash('error');
$page_title = 'Staff Orders';
require __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div><h1 class="h3 fw-bold mb-1">Orders</h1><p class="text-secondary mb-0">Review orders and advance them through the canteen workflow.</p></div>
    <a class="btn btn-outline-primary" href="/SmartCanteenAI/staff/kitchen.php"><i class="bi bi-fire me-1"></i>Kitchen Board</a>
</div>

<?php if ($flashSuccess): ?><div class="alert alert-success"><?= e($flashSuccess) ?></div><?php endif; ?>
<?php if ($flashError): ?><div class="alert alert-danger"><?= e($flashError) ?></div><?php endif; ?>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <div class="d-flex flex-wrap gap-2">
            <a class="btn btn-sm <?= $statusFilter === '' ? 'btn-primary' : 'btn-outline-primary' ?>" href="/SmartCanteenAI/staff/orders.php">All</a>
            <?php foreach ($allowedStatuses as $status): ?>
                <a class="btn btn-sm <?= $statusFilter === $status ? 'btn-primary' : 'btn-outline-primary' ?>" href="/SmartCanteenAI/staff/orders.php?status=<?= urlencode($status) ?>"><?= e($status) ?></a>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm">
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead><tr><th>Order</th><th>Customer</th><th>Status</th><th>Payment</th><th>Total</th></tr></thead>
                    <tbody>
                    <?php if ($orders === []): ?>
                        <tr><td colspan="5" class="text-center text-secondary py-5">No orders found.</td></tr>
                    <?php else: foreach ($orders as $order): ?>
                        <tr class="<?= $selectedId === (int)$order['order_id'] ? 'table-primary' : '' ?>">
                            <td><a class="fw-semibold text-decoration-none" href="/SmartCanteenAI/staff/orders.php?order_id=<?= (int)$order['order_id'] ?><?= $statusFilter ? '&status=' . urlencode($statusFilter) : '' ?>"><?= e($order['order_number']) ?></a><div class="small text-secondary"><?= e(date('d M, h:i A', strtotime((string)$order['order_date']))) ?></div></td>
                            <td><?= e($order['full_name']) ?></td>
                            <td><span class="badge text-bg-primary"><?= e($order['order_status']) ?></span></td>
                            <td><span class="badge <?= $order['payment_status'] === 'PAID' ? 'text-bg-success' : 'text-bg-warning' ?>"><?= e($order['payment_status']) ?></span></td>
                            <td>₹<?= number_format((float)$order['total_amount'], 2) ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card border-0 shadow-sm">
            <?php if (!$selectedOrder): ?>
                <div class="card-body text-center py-5 text-secondary"><i class="bi bi-receipt fs-1 d-block mb-3"></i>Select an order to view details.</div>
            <?php else: ?>
                <div class="card-header bg-white"><h2 class="h5 mb-1"><?= e($selectedOrder['order_number']) ?></h2><div class="small text-secondary"><?= e($selectedOrder['full_name']) ?> · <?= e($selectedOrder['email']) ?></div></div>
                <div class="card-body">
                    <div class="row g-2 small mb-3">
                        <div class="col-6"><strong>Status:</strong><br><?= e($selectedOrder['order_status']) ?></div>
                        <div class="col-6"><strong>Payment:</strong><br><?= e($selectedOrder['payment_status']) ?></div>
                        <div class="col-6"><strong>Method:</strong><br><?= e($selectedOrder['payment_method'] ?? '—') ?></div>
                        <div class="col-6"><strong>Date:</strong><br><?= e(date('d M Y, h:i A', strtotime((string)$selectedOrder['order_date']))) ?></div>
                    </div>
                    <h3 class="h6">Items</h3>
                    <div class="table-responsive"><table class="table table-sm"><tbody>
                    <?php foreach ($selectedItems as $item): ?><tr><td><?= e($item['item_name']) ?> × <?= (int)$item['quantity'] ?></td><td class="text-end">₹<?= number_format((float)$item['subtotal'], 2) ?></td></tr><?php endforeach; ?>
                    </tbody><tfoot><tr><th>Total</th><th class="text-end">₹<?= number_format((float)$selectedOrder['total_amount'], 2) ?></th></tr></tfoot></table></div>

                    <?php $nextStatuses = $transitions[(string)$selectedOrder['order_status']] ?? []; ?>
                    <?php if ($nextStatuses): ?>
                        <h3 class="h6 mt-4">Update Status</h3>
                        <div class="d-flex flex-wrap gap-2">
                        <?php foreach ($nextStatuses as $next): ?>
                            <form method="post" class="d-inline">
                                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                <input type="hidden" name="order_id" value="<?= (int)$selectedOrder['order_id'] ?>">
                                <input type="hidden" name="new_status" value="<?= e($next) ?>">
                                <button class="btn btn-sm <?= $next === 'CANCELLED' ? 'btn-outline-danger' : 'btn-primary' ?>" type="submit" onclick="return confirm('Move this order to <?= e($next) ?>?')"><?= e($next) ?></button>
                            </form>
                        <?php endforeach; ?>
                        </div>
                    <?php else: ?><div class="alert alert-light border mt-3 mb-0">No further status changes are available.</div><?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
