<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role('ADMIN');

$pdo = db();

$from = $_GET['from'] ?? date('Y-m-01');
$to = $_GET['to'] ?? date('Y-m-d');

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
    $from = date('Y-m-01');
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
    $to = date('Y-m-d');
}

$stmt = $pdo->prepare(
    "SELECT
        o.order_number,
        o.order_date,
        u.full_name AS customer,
        o.subtotal,
        o.discount_amount,
        o.tax_rate,
        o.tax_amount,
        o.total_amount,
        o.payment_status,
        o.order_status,
        o.payment_method,
        oi.item_name,
        oi.quantity,
        oi.unit_price,
        oi.subtotal AS item_subtotal
     FROM orders o
     INNER JOIN users u ON u.user_id = o.user_id
     INNER JOIN order_items oi ON oi.order_id = o.order_id
     WHERE o.order_date >= ?
       AND o.order_date < DATE_ADD(?, INTERVAL 1 DAY)
     ORDER BY o.order_date DESC, o.order_id DESC, oi.order_item_id"
);
$stmt->execute([$from, $to]);

$filename = 'smartcanteen_sales_' . $from . '_to_' . $to . '.csv';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

$out = fopen('php://output', 'w');

fputcsv($out, [
    'Order Number',
    'Order Date',
    'Customer',
    'Order Subtotal',
    'Discount',
    'Tax Rate %',
    'Tax Amount',
    'Order Total',
    'Payment Status',
    'Order Status',
    'Payment Method',
    'Item',
    'Quantity',
    'Unit Price',
    'Item Subtotal'
]);

while ($row = $stmt->fetch()) {
    fputcsv($out, [
        $row['order_number'],
        $row['order_date'],
        $row['customer'],
        $row['subtotal'],
        $row['discount_amount'],
        $row['tax_rate'],
        $row['tax_amount'],
        $row['total_amount'],
        $row['payment_status'],
        $row['order_status'],
        $row['payment_method'],
        $row['item_name'],
        $row['quantity'],
        $row['unit_price'],
        $row['item_subtotal']
    ]);
}

fclose($out);
exit;
