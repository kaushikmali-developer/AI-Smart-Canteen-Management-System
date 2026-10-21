<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role('ADMIN');

$pdo = db();
$generated = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token. Please refresh and try again.';
    } else {
        $count = max(1, min(10000, (int)($_POST['count'] ?? 1000)));
        $days = max(1, min(365, (int)($_POST['days'] ?? 90)));

        try {
            $studentStmt = $pdo->query(
                "SELECT user_id FROM users
                 WHERE role_id = (SELECT role_id FROM roles WHERE role_name = 'STUDENT')
                   AND is_active = 1
                 ORDER BY user_id
                 LIMIT 1"
            );
            $student = $studentStmt->fetch();

            if (!$student) {
                throw new RuntimeException('Create at least one active STUDENT user before generating sales data.');
            }

            $items = $pdo->query(
                "SELECT item_id, item_name, price
                 FROM menu_items
                 WHERE is_available = 1
                 ORDER BY item_id"
            )->fetchAll();

            if (!$items) {
                throw new RuntimeException('No available menu items were found.');
            }

            $offers = $pdo->query(
                "SELECT offer_id, discount_type, discount_value, min_order_amount
                 FROM offers
                 WHERE is_active = 1
                   AND start_at <= NOW()
                   AND end_at >= NOW()
                 ORDER BY offer_id"
            )->fetchAll();

            $pdo->beginTransaction();

            $orderStmt = $pdo->prepare(
                "INSERT INTO orders
                    (user_id, offer_id, order_number, order_date, subtotal,
                     discount_amount, tax_rate, tax_amount, total_amount,
                     payment_status, order_status, payment_method, notes)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'PAID', 'COMPLETED', ?, ?)"
            );

            $itemStmt = $pdo->prepare(
                "INSERT INTO order_items
                    (order_id, item_id, item_name, quantity, unit_price, subtotal)
                 VALUES (?, ?, ?, ?, ?, ?)"
            );

            $taxStmt = $pdo->prepare(
                "SELECT setting_value FROM settings WHERE setting_key = 'tax_rate' LIMIT 1"
            );
            $taxStmt->execute();
            $taxRate = (float)($taxStmt->fetchColumn() ?: 5.0);

            $methods = ['CASH', 'UPI', 'CARD', 'WALLET'];
            $statuses = ['COMPLETED', 'COMPLETED', 'COMPLETED', 'COMPLETED', 'CANCELLED'];
            $createdOrders = 0;
            $createdItems = 0;

            for ($n = 1; $n <= $count; $n++) {
                $itemCount = random_int(1, 4);
                $picked = [];
                $subtotal = 0.0;

                for ($j = 0; $j < $itemCount; $j++) {
                    $item = $items[array_rand($items)];
                    $qty = random_int(1, 3);
                    $line = $qty * (float)$item['price'];
                    $subtotal += $line;

                    $picked[] = [
                        'item_id' => (int)$item['item_id'],
                        'item_name' => $item['item_name'],
                        'quantity' => $qty,
                        'unit_price' => (float)$item['price'],
                        'subtotal' => $line
                    ];
                }

                $offerId = null;
                $discount = 0.0;

                if ($offers && random_int(1, 100) <= 18) {
                    $offer = $offers[array_rand($offers)];
                    if ($subtotal >= (float)$offer['min_order_amount']) {
                        $offerId = (int)$offer['offer_id'];

                        if ($offer['discount_type'] === 'PERCENTAGE') {
                            $discount = $subtotal * ((float)$offer['discount_value'] / 100);
                        } else {
                            $discount = (float)$offer['discount_value'];
                        }

                        // Keep generated discounts within the order subtotal.
                        // max_discount is optional and is not required by the dataset generator.
                        $discount = min($discount, $subtotal);
                    }
                }

                $taxable = max(0, $subtotal - $discount);
                $tax = round($taxable * $taxRate / 100, 2);
                $total = round($taxable + $tax, 2);

                $daysBack = random_int(0, $days - 1);
                $hour = random_int(8, 19);
                $minute = random_int(0, 59);
                $second = random_int(0, 59);
                $orderDate = date(
                    'Y-m-d H:i:s',
                    strtotime("-{$daysBack} days {$hour}:{$minute}:{$second}")
                );

                $status = $statuses[array_rand($statuses)];

                // Cancelled orders do not contribute to revenue.
                if ($status === 'CANCELLED') {
                    $paymentStatus = 'REFUNDED';
                    $totalForOrder = $total;
                } else {
                    $paymentStatus = 'PAID';
                    $totalForOrder = $total;
                }

                $orderNumber = 'DEMO' . date('ymdHis') . str_pad((string)$n, 5, '0', STR_PAD_LEFT) . random_int(10, 99);
                $method = $methods[array_rand($methods)];

                $orderStmt->execute([
                    (int)$student['user_id'],
                    $offerId,
                    $orderNumber,
                    $orderDate,
                    round($subtotal, 2),
                    round($discount, 2),
                    $taxRate,
                    $tax,
                    $totalForOrder,
                    $method,
                    'Generated demo analytics dataset'
                ]);

                $orderId = (int)$pdo->lastInsertId();

                foreach ($picked as $line) {
                    $itemStmt->execute([
                        $orderId,
                        $line['item_id'],
                        $line['item_name'],
                        $line['quantity'],
                        $line['unit_price'],
                        $line['subtotal']
                    ]);
                    $createdItems++;
                }

                $createdOrders++;

                if ($createdOrders % 250 === 0) {
                    // Keep one transaction for consistency while generating the dataset.
                    // This checkpoint only updates the UI progress counter after completion.
                }
            }

            $pdo->commit();

            $generated = [
                'orders' => $createdOrders,
                'items' => $createdItems,
                'days' => $days
            ];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = $e->getMessage();
        }
    }
}

$page_title = 'Generate Demo Dataset';
require __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="mb-4">
        <h2 class="mb-1">Generate Demo Sales Dataset</h2>
        <p class="text-muted mb-0">
            Create realistic historical orders for dashboards, reports and AI analytics testing.
        </p>
    </div>

    <?php if ($generated): ?>
        <div class="alert alert-success">
            Generated <strong><?= (int)$generated['orders'] ?> orders</strong> and
            <strong><?= (int)$generated['items'] ?> order items</strong> across the last
            <strong><?= (int)$generated['days'] ?> days</strong>.
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?= e($error) ?></div>
    <?php endif; ?>

    <div class="card shadow-sm" style="max-width: 760px;">
        <div class="card-body">
            <div class="alert alert-warning">
                <strong>Demo data only.</strong> This tool inserts additional completed,
                paid and cancelled orders. Do not use it on a production database.
            </div>

            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Number of Orders</label>
                        <input type="number" name="count" class="form-control"
                               min="1" max="10000" value="1000" required>
                        <div class="form-text">Recommended for demo: 1000–5000.</div>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Historical Period (days)</label>
                        <input type="number" name="days" class="form-control"
                               min="1" max="365" value="90" required>
                    </div>
                </div>

                <button class="btn btn-primary mt-4" type="submit"
                        onclick="return confirm('Generate demo sales data now?');">
                    Generate Dataset
                </button>
            </form>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
