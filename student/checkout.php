<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role('STUDENT');

$pdo = db();
$userId = current_user_id();

$stmt = $pdo->prepare('SELECT cart_id FROM carts WHERE user_id = ? LIMIT 1');
$stmt->execute([$userId]);
$cartId = (int) ($stmt->fetchColumn() ?: 0);

if ($cartId === 0) {
    set_flash('error', 'Your cart is empty.');
    redirect('/SmartCanteenAI/student/menu.php');
}

function checkout_offer(PDO $pdo, string $code, float $subtotal): array
{
    if ($code === '') {
        return [null, 0.0, null];
    }

    $stmt = $pdo->prepare(
        'SELECT *
         FROM offers
         WHERE offer_code = ?
           AND is_active = 1
           AND start_at <= NOW()
           AND end_at >= NOW()
         LIMIT 1'
    );
    $stmt->execute([strtoupper(trim($code))]);
    $offer = $stmt->fetch();

    if (!$offer) {
        return [null, 0.0, 'Invalid or expired offer code.'];
    }

    if ($subtotal < (float) $offer['min_order_amount']) {
        return [null, 0.0, 'Minimum order for ' . $offer['offer_code'] . ' is ₹' . number_format((float) $offer['min_order_amount'], 2) . '. Your current subtotal is ₹' . number_format($subtotal, 2) . '.'];
    }

    if ($offer['discount_type'] === 'PERCENT') {
        $discount = $subtotal * ((float) $offer['discount_value'] / 100);
        if ($offer['max_discount_amount'] !== null) {
            $discount = min($discount, (float) $offer['max_discount_amount']);
        }
    } else {
        $discount = (float) $offer['discount_value'];
    }

    return [$offer, max(0.0, min($discount, $subtotal)), null];
}

function checkout_cart(PDO $pdo, int $cartId): array
{
    $stmt = $pdo->prepare(
        'SELECT ci.item_id, ci.quantity, mi.item_name, mi.price
         FROM cart_items ci
         INNER JOIN menu_items mi ON mi.item_id = ci.item_id
         WHERE ci.cart_id = ?
         ORDER BY mi.item_name
         FOR UPDATE'
    );
    $stmt->execute([$cartId]);
    return $stmt->fetchAll();
}

$offerCode = strtoupper(trim((string) ($_GET['offer'] ?? $_POST['offer'] ?? '')));

$stmt = $pdo->prepare(
    'SELECT ci.item_id, ci.quantity, mi.item_name, mi.price
     FROM cart_items ci
     INNER JOIN menu_items mi ON mi.item_id = ci.item_id
     WHERE ci.cart_id = ?
     ORDER BY mi.item_name'
);
$stmt->execute([$cartId]);
$items = $stmt->fetchAll();

if ($items === []) {
    set_flash('error', 'Your cart is empty.');
    redirect('/SmartCanteenAI/student/menu.php');
}

$subtotal = 0.0;
foreach ($items as $item) {
    $subtotal += (float) $item['price'] * (int) $item['quantity'];
}

[$offer, $discount, $offerError] = checkout_offer($pdo, $offerCode, $subtotal);

$taxStmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'tax_rate' LIMIT 1");
$taxStmt->execute();
$taxRate = (float) ($taxStmt->fetchColumn() ?: 0);
$taxAmount = max(0, ($subtotal - $discount) * $taxRate / 100);
$total = max(0, $subtotal - $discount + $taxAmount);

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf((string) ($_POST['csrf_token'] ?? ''))) {
        $errors[] = 'Invalid security token. Please try again.';
    }

    $paymentMethod = strtoupper(trim((string) ($_POST['payment_method'] ?? '')));
    $allowedMethods = ['CASH', 'UPI', 'CARD', 'WALLET'];

    if (!in_array($paymentMethod, $allowedMethods, true)) {
        $errors[] = 'Please select a valid payment method.';
    }

    $notes = trim((string) ($_POST['notes'] ?? ''));
    if (mb_strlen($notes) > 500) {
        $errors[] = 'Order notes must be 500 characters or fewer.';
    }

    if ($offerError !== null) {
        $errors[] = $offerError;
    }

    if ($errors === []) {
        try {
            $pdo->beginTransaction();

            $lockedItems = checkout_cart($pdo, $cartId);
            if ($lockedItems === []) {
                throw new RuntimeException('Your cart became empty. Please add items again.');
            }

            $subtotal = 0.0;
            foreach ($lockedItems as $item) {
                $availableStmt = $pdo->prepare(
                    'SELECT is_available, price, item_name FROM menu_items WHERE item_id = ? FOR UPDATE'
                );
                $availableStmt->execute([(int) $item['item_id']]);
                $current = $availableStmt->fetch();

                if (!$current || !(int) $current['is_available']) {
                    throw new RuntimeException($item['item_name'] . ' is no longer available.');
                }

                $subtotal += (float) $current['price'] * (int) $item['quantity'];
            }

            [$lockedOffer, $discount] = checkout_offer($pdo, $offerCode, $subtotal);
            if ($offerCode !== '' && !$lockedOffer) {
                throw new RuntimeException('The offer is no longer valid.');
            }

            $taxStmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'tax_rate' LIMIT 1");
            $taxStmt->execute();
            $taxRate = (float) ($taxStmt->fetchColumn() ?: 0);
            $taxAmount = max(0, ($subtotal - $discount) * $taxRate / 100);
            $total = max(0, $subtotal - $discount + $taxAmount);

            $orderNumber = 'SC' . date('YmdHis') . strtoupper(bin2hex(random_bytes(3)));

            $orderStmt = $pdo->prepare(
                'INSERT INTO orders
                    (user_id, offer_id, order_number, subtotal, discount_amount,
                     tax_rate, tax_amount, total_amount, payment_status,
                     order_status, payment_method, notes)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );

            // Phase 4 uses a clearly labeled simulated payment so the complete demo flow can be tested.
            // No real UPI/card/wallet transaction is performed.
            $paymentStatus = 'PAID';
            $orderStatus = 'PLACED';

            $orderStmt->execute([
                $userId,
                $lockedOffer ? (int) $lockedOffer['offer_id'] : null,
                $orderNumber,
                $subtotal,
                $discount,
                $taxRate,
                $taxAmount,
                $total,
                $paymentStatus,
                $orderStatus,
                $paymentMethod,
                $notes !== '' ? $notes : null
            ]);

            $orderId = (int) $pdo->lastInsertId();

            $itemStmt = $pdo->prepare(
                'INSERT INTO order_items
                    (order_id, item_id, item_name, quantity, unit_price, subtotal)
                 VALUES (?, ?, ?, ?, ?, ?)'
            );

            foreach ($lockedItems as $item) {
                $unitPrice = (float) $item['price'];
                $quantity = (int) $item['quantity'];
                $lineTotal = $unitPrice * $quantity;

                $itemStmt->execute([
                    $orderId,
                    (int) $item['item_id'],
                    $item['item_name'],
                    $quantity,
                    $unitPrice,
                    $lineTotal
                ]);
            }

            $paymentReference = 'DEMO-' . $orderNumber;
            $paymentStmt = $pdo->prepare(
                'INSERT INTO payments
                    (order_id, transaction_reference, payment_method, amount, status, paid_at)
                 VALUES (?, ?, ?, ?, ?, NOW())'
            );
            $paymentStmt->execute([
                $orderId,
                $paymentReference,
                $paymentMethod,
                $total,
                'SUCCESS'
            ]);

            $clearStmt = $pdo->prepare('DELETE FROM cart_items WHERE cart_id = ?');
            $clearStmt->execute([$cartId]);

            $notificationStmt = $pdo->prepare(
                'INSERT INTO notifications
                    (user_id, title, message, notification_type, related_order_id)
                 VALUES (?, ?, ?, ?, ?)'
            );
            $notificationStmt->execute([
                $userId,
                'Order placed',
                'Your order ' . $orderNumber . ' has been placed successfully.',
                'ORDER',
                $orderId
            ]);

            $pdo->commit();

            redirect('/SmartCanteenAI/student/orders.php?order_id=' . $orderId);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('SmartCanteen checkout error: ' . $e->getMessage());
            $errors[] = $e instanceof RuntimeException
                ? $e->getMessage()
                : 'We could not place your order. Please try again.';
        }
    }
}

$page_title = 'Checkout';
require __DIR__ . '/../includes/header.php';
?>

<div class="mb-4">
    <h1 class="h3 fw-bold mb-1">Checkout</h1>
    <p class="text-secondary mb-0">Review your order before placing it.</p>
</div>

<?php if ($errors !== []): ?>
    <div class="alert alert-danger">
        <ul class="mb-0">
            <?php foreach ($errors as $error): ?>
                <li><?= e($error) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<div class="row g-4">
    <div class="col-lg-7">
        <form method="post" class="card border-0 shadow-sm">
            <div class="card-body">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="offer" value="<?= e($offerCode) ?>">

                <h2 class="h5 mb-3">Offer Code</h2>
                <form method="get" class="input-group mb-2">
                    <input class="form-control" type="text" name="offer"
                           value="<?= e($offerCode) ?>" placeholder="Enter offer code (e.g. WELCOME10)"
                           autocomplete="off">
                    <button class="btn btn-outline-primary" type="submit">Apply</button>
                </form>
                <div class="small text-secondary mb-3">
                    Enter a code and click <strong>Apply</strong>. Offer eligibility is checked against your current subtotal.
                </div>
                <div class="d-flex flex-wrap gap-2 mb-4">
                    <a class="btn btn-sm <?= $offerCode === 'WELCOME10' ? 'btn-success' : 'btn-outline-secondary' ?>" href="?offer=WELCOME10">WELCOME10</a>
                    <a class="btn btn-sm <?= $offerCode === 'FLAT20' ? 'btn-success' : 'btn-outline-secondary' ?>" href="?offer=FLAT20">FLAT20</a>
                    <a class="btn btn-sm <?= $offerCode === 'STUDENT15' ? 'btn-success' : 'btn-outline-secondary' ?>" href="?offer=STUDENT15">STUDENT15</a>
                    <?php if ($offerCode !== ''): ?>
                        <a class="btn btn-sm btn-outline-danger" href="/SmartCanteenAI/student/checkout.php">Remove offer</a>
                    <?php endif; ?>
                </div>
                <?php if ($offer): ?>
                    <div class="alert alert-success py-2 small">
                        <i class="bi bi-tag-fill me-1"></i>
                        <strong><?= e($offer['offer_code']) ?></strong> applied —
                        <?= e($offer['title']) ?>. You save <strong>₹<?= number_format($discount, 2) ?></strong>.
                    </div>
                <?php elseif ($offerError !== null): ?>
                    <div class="alert alert-warning py-2 small">
                        <i class="bi bi-exclamation-triangle me-1"></i>
                        <?= e($offerError) ?>
                    </div>
                <?php endif; ?>

                <h2 class="h5 mb-3">Payment Method</h2>
                <div class="row g-2 mb-4">
                    <?php foreach ([
                        'UPI' => 'bi-phone',
                        'CARD' => 'bi-credit-card',
                        'WALLET' => 'bi-wallet2',
                        'CASH' => 'bi-cash-coin',
                    ] as $method => $icon): ?>
                        <div class="col-sm-6">
                            <label class="border rounded-3 p-3 w-100 d-flex gap-2 align-items-center">
                                <input class="form-check-input mt-0" type="radio"
                                       name="payment_method" value="<?= $method ?>"
                                       <?= ($method === 'UPI') ? 'checked' : '' ?>>
                                <i class="bi <?= $icon ?>"></i>
                                <span><?= $method ?></span>
                            </label>
                        </div>
                    <?php endforeach; ?>
                </div>

                <h2 class="h5 mb-3">Order Notes</h2>
                <textarea class="form-control mb-4" name="notes" rows="4"
                          maxlength="500" placeholder="Optional instructions for the canteen"><?= e($_POST['notes'] ?? '') ?></textarea>

                <div class="alert alert-info small">
                    <i class="bi bi-info-circle me-1"></i>
                    Payment is recorded as <strong>Pending</strong>. A live UPI/card/wallet gateway will be connected in the billing phase.
                </div>

                <button class="btn btn-primary btn-lg w-100" type="submit">
                    <i class="bi bi-check2-circle me-1"></i>
                    Place Order · ₹<?= number_format($total, 2) ?>
                </button>
            </div>
        </form>
    </div>

    <div class="col-lg-5">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <h2 class="h5 mb-3">Order Summary</h2>
                <?php foreach ($items as $item): ?>
                    <div class="d-flex justify-content-between gap-3 mb-2">
                        <span><?= e($item['item_name']) ?> × <?= (int) $item['quantity'] ?></span>
                        <span>₹<?= number_format((float) $item['price'] * (int) $item['quantity'], 2) ?></span>
                    </div>
                <?php endforeach; ?>
                <hr>
                <div class="d-flex justify-content-between mb-2">
                    <span>Subtotal</span><span>₹<?= number_format($subtotal, 2) ?></span>
                </div>
                <div class="d-flex justify-content-between mb-2 text-success">
                    <span>Discount</span><span>- ₹<?= number_format($discount, 2) ?></span>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span>Tax (<?= number_format($taxRate, 2) ?>%)</span>
                    <span>₹<?= number_format($taxAmount, 2) ?></span>
                </div>
                <hr>
                <div class="d-flex justify-content-between fs-5 fw-bold">
                    <span>Total</span><span>₹<?= number_format($total, 2) ?></span>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
