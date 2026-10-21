<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role('STAFF');

$pdo = db();
$columns = [
    'PLACED' => ['title' => 'New', 'next' => 'CONFIRMED'],
    'CONFIRMED' => ['title' => 'Confirmed', 'next' => 'PREPARING'],
    'PREPARING' => ['title' => 'Preparing', 'next' => 'READY'],
    'READY' => ['title' => 'Ready', 'next' => 'COMPLETED'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf((string)($_POST['csrf_token'] ?? ''))) {
        set_flash('error', 'Invalid security token.');
        redirect('/SmartCanteenAI/staff/kitchen.php');
    }
    $orderId = (int)($_POST['order_id'] ?? 0);
    $newStatus = strtoupper(trim((string)($_POST['new_status'] ?? '')));
    $validNext = in_array($newStatus, ['CONFIRMED','PREPARING','READY','COMPLETED'], true);

    if ($orderId < 1 || !$validNext) {
        set_flash('error', 'Invalid kitchen update.');
        redirect('/SmartCanteenAI/staff/kitchen.php');
    }

    $stmt = $pdo->prepare(
        'SELECT order_number, user_id, order_status FROM orders WHERE order_id = ? LIMIT 1'
    );
    $stmt->execute([$orderId]);
    $order = $stmt->fetch();
    $allowed = [
        'PLACED' => 'CONFIRMED',
        'CONFIRMED' => 'PREPARING',
        'PREPARING' => 'READY',
        'READY' => 'COMPLETED',
    ];

    if (!$order || ($allowed[(string)$order['order_status']] ?? null) !== $newStatus) {
        set_flash('error', 'That order cannot be moved to the selected stage.');
        redirect('/SmartCanteenAI/staff/kitchen.php');
    }

    $pdo->beginTransaction();
    try {
        $update = $pdo->prepare('UPDATE orders SET order_status = ? WHERE order_id = ?');
        $update->execute([$newStatus, $orderId]);
        $titleMap = ['CONFIRMED'=>'Order Confirmed','PREPARING'=>'Order Being Prepared','READY'=>'Order Ready','COMPLETED'=>'Order Completed'];
        $messageMap = [
            'CONFIRMED' => 'Your order ' . $order['order_number'] . ' has been confirmed by the canteen.',
            'PREPARING' => 'Your order ' . $order['order_number'] . ' is now being prepared.',
            'READY' => 'Your order ' . $order['order_number'] . ' is ready for pickup.',
            'COMPLETED' => 'Your order ' . $order['order_number'] . ' has been completed. Thank you!',
        ];
        $notify = $pdo->prepare(
            'INSERT INTO notifications (user_id, title, message, notification_type, related_order_id)
             VALUES (?, ?, ?, ?, ?)'
        );
        $notify->execute([(int)$order['user_id'], $titleMap[$newStatus], $messageMap[$newStatus], 'ORDER_STATUS', $orderId]);
        $pdo->commit();
        set_flash('success', $order['order_number'] . ' moved to ' . $newStatus . '.');
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        set_flash('error', 'Kitchen update failed.');
    }
    redirect('/SmartCanteenAI/staff/kitchen.php');
}

$flashSuccess = get_flash('success');
$flashError = get_flash('error');
$ordersByStatus = array_fill_keys(array_keys($columns), []);

$stmt = $pdo->query(
    "SELECT o.order_id, o.order_number, o.order_date, o.total_amount, o.payment_status,
            o.order_status, u.full_name
     FROM orders o
     INNER JOIN users u ON u.user_id = o.user_id
     WHERE o.order_status IN ('PLACED','CONFIRMED','PREPARING','READY')
     ORDER BY o.order_date ASC"
);
foreach ($stmt->fetchAll() as $order) {
    $ordersByStatus[$order['order_status']][] = $order;
}

$itemStmt = $pdo->prepare(
    'SELECT item_name, quantity FROM order_items WHERE order_id = ? ORDER BY order_item_id'
);

$page_title = 'Kitchen Board';
require __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div><h1 class="h3 fw-bold mb-1">Kitchen Board</h1><p class="text-secondary mb-0">Move active orders through preparation stages.</p></div>
    <a class="btn btn-outline-primary" href="/SmartCanteenAI/staff/orders.php"><i class="bi bi-receipt me-1"></i>Order List</a>
</div>

<?php if ($flashSuccess): ?><div class="alert alert-success"><?= e($flashSuccess) ?></div><?php endif; ?>
<?php if ($flashError): ?><div class="alert alert-danger"><?= e($flashError) ?></div><?php endif; ?>

<div class="row g-3">
<?php foreach ($columns as $status => $column): ?>
    <div class="col-12 col-md-6 col-xl-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <span class="fw-semibold"><?= e($column['title']) ?></span>
                <span class="badge text-bg-secondary"><?= count($ordersByStatus[$status]) ?></span>
            </div>
            <div class="card-body bg-light">
                <?php if ($ordersByStatus[$status] === []): ?>
                    <div class="text-center text-secondary py-4 small">No orders</div>
                <?php else: foreach ($ordersByStatus[$status] as $order): ?>
                    <?php $itemStmt->execute([(int)$order['order_id']]); $items = $itemStmt->fetchAll(); ?>
                    <div class="card border-0 shadow-sm mb-3 order-card">
                        <div class="card-body p-3">
                            <div class="d-flex justify-content-between gap-2"><strong><?= e($order['order_number']) ?></strong><span class="small text-secondary"><?= e(date('h:i A', strtotime((string)$order['order_date']))) ?></span></div>
                            <div class="small text-secondary mb-2"><?= e($order['full_name']) ?></div>
                            <ul class="small ps-3 mb-3">
                                <?php foreach ($items as $item): ?><li><?= e($item['item_name']) ?> × <?= (int)$item['quantity'] ?></li><?php endforeach; ?>
                            </ul>
                            <div class="d-flex justify-content-between align-items-center mb-2"><span class="small">₹<?= number_format((float)$order['total_amount'],2) ?></span><span class="badge <?= $order['payment_status']==='PAID'?'text-bg-success':'text-bg-warning' ?>"><?= e($order['payment_status']) ?></span></div>
                            <form method="post">
                                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                <input type="hidden" name="order_id" value="<?= (int)$order['order_id'] ?>">
                                <input type="hidden" name="new_status" value="<?= e($column['next']) ?>">
                                <button class="btn btn-sm btn-primary w-100" type="submit"><?= e($column['next']) ?></button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; endif; ?>
            </div>
        </div>
    </div>
<?php endforeach; ?>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
