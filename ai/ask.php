<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role('ADMIN', 'MANAGER');

$pdo = db();
$answer = null;
$question = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $answer = 'Invalid security token.';
    } else {
        $question = trim($_POST['question'] ?? '');
        $q = strtolower($question);

        $days = 30;
        if (preg_match('/(\d+)\s*(day|days|week|weeks|month|months)/', $q, $m)) {
            $number = (int)$m[1];
            if (str_contains($m[2], 'week')) {
                $days = $number * 7;
            } elseif (str_contains($m[2], 'month')) {
                $days = $number * 30;
            } else {
                $days = $number;
            }
            $days = max(1, min(365, $days));
        }

        $start = date('Y-m-d', strtotime("-" . ($days - 1) . " days"));
        $end = date('Y-m-d');

        if (str_contains($q, 'low stock') || str_contains($q, 'low inventory') || str_contains($q, 'stock')) {
            $stmt = $pdo->query(
                "SELECT ingredient_name, current_stock, minimum_stock, unit
                 FROM inventory
                 WHERE is_active = 1
                   AND current_stock <= minimum_stock
                 ORDER BY current_stock ASC"
            );
            $items = $stmt->fetchAll();

            if (!$items) {
                $answer = "There are currently no inventory items at or below their minimum stock level.";
            } else {
                $parts = [];
                foreach ($items as $item) {
                    $parts[] = $item['ingredient_name'] . ': ' .
                        $item['current_stock'] . ' ' . $item['unit'] .
                        ' available vs minimum ' . $item['minimum_stock'];
                }
                $answer = 'Low-stock items: ' . implode('; ', $parts) . '.';
            }
        } elseif (str_contains($q, 'top') || str_contains($q, 'popular') || str_contains($q, 'best selling') || str_contains($q, 'selling')) {
            $stmt = $pdo->prepare(
                "SELECT oi.item_name, SUM(oi.quantity) AS units_sold,
                        SUM(oi.subtotal) AS sales
                 FROM order_items oi
                 INNER JOIN orders o ON o.order_id = oi.order_id
                 WHERE o.order_date >= ?
                   AND o.order_date < DATE_ADD(?, INTERVAL 1 DAY)
                   AND o.payment_status = 'PAID'
                   AND o.order_status <> 'CANCELLED'
                 GROUP BY oi.item_id, oi.item_name
                 ORDER BY units_sold DESC
                 LIMIT 5"
            );
            $stmt->execute([$start, $end]);
            $items = $stmt->fetchAll();

            if (!$items) {
                $answer = "There are no paid, non-cancelled sales in the selected period.";
            } else {
                $parts = [];
                foreach ($items as $i => $item) {
                    $parts[] = ($i + 1) . '. ' . $item['item_name'] .
                        ' — ' . $item['units_sold'] . ' units, ₹' .
                        number_format((float)$item['sales'], 2) . ' sales';
                }
                $answer = 'Top-selling items for the last ' . $days . ' days: ' .
                    implode('; ', $parts) . '.';
            }
        } else {
            $stmt = $pdo->prepare(
                "SELECT COUNT(*) AS orders,
                        COALESCE(SUM(subtotal), 0) AS subtotal,
                        COALESCE(SUM(discount_amount), 0) AS discounts,
                        COALESCE(SUM(tax_amount), 0) AS tax,
                        COALESCE(SUM(total_amount), 0) AS revenue
                 FROM orders
                 WHERE order_date >= ?
                   AND order_date < DATE_ADD(?, INTERVAL 1 DAY)
                   AND payment_status = 'PAID'
                   AND order_status <> 'CANCELLED'"
            );
            $stmt->execute([$start, $end]);
            $summary = $stmt->fetch();

            $answer = 'For the last ' . $days . ' days, there were ' .
                number_format((int)$summary['orders']) . ' paid, non-cancelled orders ' .
                'with revenue of ₹' . number_format((float)$summary['revenue'], 2) .
                ', subtotal of ₹' . number_format((float)$summary['subtotal'], 2) .
                ', discounts of ₹' . number_format((float)$summary['discounts'], 2) .
                ' and tax of ₹' . number_format((float)$summary['tax'], 2) . '.';
        }
    }
}

$page_title = 'AI Analytics Assistant';
require __DIR__ . '/../includes/header.php';
?>

<div class="container py-5">
    <div class="card shadow-sm">
        <div class="card-body p-4">
            <h2 class="mb-1">AI Analytics Assistant</h2>
            <p class="text-muted">
                Ask about sales, popular items or low stock. Answers are generated
                directly from the database.
            </p>

            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <div class="input-group">
                    <input type="text" name="question" class="form-control"
                           value="<?= e($question) ?>"
                           placeholder="Example: What were the top selling items in the last 30 days?"
                           required>
                    <button class="btn btn-primary" type="submit">Ask</button>
                </div>
            </form>

            <?php if ($answer !== null): ?>
                <div class="alert alert-light border mt-4 mb-0">
                    <strong>Answer:</strong><br>
                    <?= e($answer) ?>
                </div>
            <?php endif; ?>

            <div class="mt-4">
                <div class="small text-muted">Try:</div>
                <ul class="small text-muted">
                    <li>What were the top selling items in the last 30 days?</li>
                    <li>Show low stock items.</li>
                    <li>Give me sales for the last 7 days.</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
