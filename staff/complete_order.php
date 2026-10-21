<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/inventory.php';

require_role('STAFF');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: kitchen.php');
    exit;
}

if (!verify_csrf($_POST['csrf_token'] ?? '')) {
    set_flash('error', 'Invalid security token. Please try again.');
    header('Location: kitchen.php');
    exit;
}

$orderId = (int)($_POST['order_id'] ?? 0);

if ($orderId <= 0) {
    set_flash('error', 'Invalid order.');
    header('Location: kitchen.php');
    exit;
}

$result = consume_order_inventory($orderId, (int)current_user()['user_id']);

if (!$result['success']) {
    set_flash('error', 'Order was not completed: ' . $result['message']);
    header('Location: kitchen.php');
    exit;
}

// Inventory consumption is complete/idempotent; now move the order to COMPLETED.
try {
    $pdo = db();

    $stmt = $pdo->prepare(
        "UPDATE orders
         SET order_status = 'COMPLETED'
         WHERE order_id = ?
           AND order_status <> 'CANCELLED'"
    );
    $stmt->execute([$orderId]);

    if ($stmt->rowCount() > 0) {
        if (!empty($result['already_consumed'])) {
            set_flash('success', 'Order marked as completed. Inventory had already been consumed.');
        } else {
            set_flash('success', 'Order completed and inventory consumed automatically.');
        }
    } else {
        set_flash('warning', 'No order status change was required.');
    }
} catch (Throwable $e) {
    set_flash('error', 'Inventory was processed, but the order status could not be updated: ' . $e->getMessage());
}

header('Location: kitchen.php');
exit;
