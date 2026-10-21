<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role('STUDENT');

$pdo = db();
$userId = current_user_id();

function get_or_create_cart(PDO $pdo, int $userId): int
{
    $stmt = $pdo->prepare('SELECT cart_id FROM carts WHERE user_id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $cartId = $stmt->fetchColumn();

    if ($cartId !== false) {
        return (int) $cartId;
    }

    $stmt = $pdo->prepare('INSERT INTO carts (user_id) VALUES (?)');
    $stmt->execute([$userId]);
    return (int) $pdo->lastInsertId();
}

function calculate_offer(PDO $pdo, ?string $code, float $subtotal): array
{
    if ($code === null || trim($code) === '') {
        return ['offer' => null, 'discount' => 0.0, 'error' => null];
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
        return ['offer' => null, 'discount' => 0.0, 'error' => 'Invalid or expired offer code.'];
    }

    if ($subtotal < (float) $offer['min_order_amount']) {
        return [
            'offer' => $offer,
            'discount' => 0.0,
            'error' => 'This offer requires a minimum order of ₹' . number_format((float) $offer['min_order_amount'], 2) . '.'
        ];
    }

    if ($offer['discount_type'] === 'PERCENT') {
        $discount = $subtotal * ((float) $offer['discount_value'] / 100);
        if ($offer['max_discount_amount'] !== null) {
            $discount = min($discount, (float) $offer['max_discount_amount']);
        }
    } else {
        $discount = (float) $offer['discount_value'];
    }

    return [
        'offer' => $offer,
        'discount' => max(0.0, min($discount, $subtotal)),
        'error' => null
    ];
}

$cartId = get_or_create_cart($pdo, $userId);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf((string) ($_POST['csrf_token'] ?? ''))) {
        set_flash('error', 'Invalid security token. Please try again.');
        redirect('/SmartCanteenAI/student/cart.php');
    }

    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'add') {
        $itemId = filter_var($_POST['item_id'] ?? null, FILTER_VALIDATE_INT);
        $quantity = filter_var($_POST['quantity'] ?? 1, FILTER_VALIDATE_INT);

        if (!$itemId || !$quantity || $quantity < 1 || $quantity > 50) {
            set_flash('error', 'Invalid item or quantity.');
            redirect('/SmartCanteenAI/student/menu.php');
        }

        $stmt = $pdo->prepare(
            'SELECT item_id FROM menu_items WHERE item_id = ? AND is_available = 1 LIMIT 1'
        );
        $stmt->execute([$itemId]);

        if (!$stmt->fetchColumn()) {
            set_flash('error', 'That menu item is currently unavailable.');
            redirect('/SmartCanteenAI/student/menu.php');
        }

        $stmt = $pdo->prepare(
            'INSERT INTO cart_items (cart_id, item_id, quantity)
             VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE quantity = LEAST(quantity + VALUES(quantity), 50)'
        );
        $stmt->execute([$cartId, $itemId, $quantity]);

        set_flash('success', 'Item added to your cart.');
        redirect('/SmartCanteenAI/student/cart.php');
    }

    if ($action === 'update') {
        $itemId = filter_var($_POST['item_id'] ?? null, FILTER_VALIDATE_INT);
        $quantity = filter_var($_POST['quantity'] ?? null, FILTER_VALIDATE_INT);

        if (!$itemId || $quantity === false || $quantity < 0 || $quantity > 50) {
            set_flash('error', 'Invalid quantity.');
            redirect('/SmartCanteenAI/student/cart.php');
        }

        if ($quantity === 0) {
            $stmt = $pdo->prepare('DELETE FROM cart_items WHERE cart_id = ? AND item_id = ?');
            $stmt->execute([$cartId, $itemId]);
        } else {
            $stmt = $pdo->prepare(
                'UPDATE cart_items SET quantity = ? WHERE cart_id = ? AND item_id = ?'
            );
            $stmt->execute([$quantity, $cartId, $itemId]);
        }

        set_flash('success', 'Cart updated.');
        redirect('/SmartCanteenAI/student/cart.php');
    }

    if ($action === 'remove') {
        $itemId = filter_var($_POST['item_id'] ?? null, FILTER_VALIDATE_INT);
        if ($itemId) {
            $stmt = $pdo->prepare('DELETE FROM cart_items WHERE cart_id = ? AND item_id = ?');
            $stmt->execute([$cartId, $itemId]);
        }
        set_flash('success', 'Item removed from your cart.');
        redirect('/SmartCanteenAI/student/cart.php');
    }

    if ($action === 'clear') {
        $stmt = $pdo->prepare('DELETE FROM cart_items WHERE cart_id = ?');
        $stmt->execute([$cartId]);
        set_flash('success', 'Cart cleared.');
        redirect('/SmartCanteenAI/student/cart.php');
    }
}

$stmt = $pdo->prepare(
    'SELECT ci.item_id, ci.quantity, mi.item_name, mi.price,
            (ci.quantity * mi.price) AS line_total
     FROM cart_items ci
     INNER JOIN menu_items mi ON mi.item_id = ci.item_id
     WHERE ci.cart_id = ?
     ORDER BY mi.item_name'
);
$stmt->execute([$cartId]);
$items = $stmt->fetchAll();

$subtotal = 0.0;
foreach ($items as $item) {
    $subtotal += (float) $item['line_total'];
}

$offerCode = trim((string) ($_GET['offer'] ?? ''));
$offerResult = calculate_offer($pdo, $offerCode !== '' ? $offerCode : null, $subtotal);
$discount = $offerResult['discount'];
$taxRateStmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'tax_rate' LIMIT 1");
$taxRateStmt->execute();
$taxRate = (float) ($taxRateStmt->fetchColumn() ?: 0);
$taxAmount = max(0, ($subtotal - $discount) * $taxRate / 100);
$total = max(0, $subtotal - $discount + $taxAmount);

$page_title = 'My Cart';
require __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <div>
        <h1 class="h3 fw-bold mb-1">My Cart</h1>
        <p class="text-secondary mb-0">Your cart is saved to your account.</p>
    </div>
    <a class="btn btn-outline-primary" href="/SmartCanteenAI/student/menu.php">
        <i class="bi bi-arrow-left me-1"></i>Continue Shopping
    </a>
</div>

<?php if (($flash = get_flash('success')) !== null): ?>
    <div class="alert alert-success"><?= e($flash) ?></div>
<?php endif; ?>
<?php if (($flash = get_flash('error')) !== null): ?>
    <div class="alert alert-danger"><?= e($flash) ?></div>
<?php endif; ?>

<?php if ($offerResult['error'] !== null): ?>
    <div class="alert alert-warning"><?= e($offerResult['error']) ?></div>
<?php endif; ?>

<?php if ($items === []): ?>
    <div class="card border-0 shadow-sm">
        <div class="card-body text-center py-5">
            <i class="bi bi-cart-x display-5 text-secondary"></i>
            <h2 class="h5 mt-3">Your cart is empty</h2>
            <p class="text-secondary">Add some food from the menu to get started.</p>
            <a class="btn btn-primary" href="/SmartCanteenAI/student/menu.php">Browse Menu</a>
        </div>
    </div>
<?php else: ?>
    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-0">
                    <?php foreach ($items as $item): ?>
                        <div class="p-3 border-bottom">
                            <div class="row align-items-center g-3">
                                <div class="col">
                                    <h2 class="h6 mb-1"><?= e($item['item_name']) ?></h2>
                                    <div class="small text-secondary">
                                        ₹<?= number_format((float) $item['price'], 2) ?> each
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <form method="post" class="d-flex align-items-center gap-2">
                                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                        <input type="hidden" name="action" value="update">
                                        <input type="hidden" name="item_id" value="<?= (int) $item['item_id'] ?>">
                                        <input class="form-control" style="width:90px" type="number"
                                               name="quantity" min="0" max="50"
                                               value="<?= (int) $item['quantity'] ?>"
                                               onchange="this.form.submit()">
                                    </form>
                                </div>
                                <div class="col-auto fw-semibold">
                                    ₹<?= number_format((float) $item['line_total'], 2) ?>
                                </div>
                                <div class="col-auto">
                                    <form method="post">
                                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                        <input type="hidden" name="action" value="remove">
                                        <input type="hidden" name="item_id" value="<?= (int) $item['item_id'] ?>">
                                        <button class="btn btn-sm btn-outline-danger" title="Remove" type="submit">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="card-footer bg-white border-0 p-3">
                    <form method="post">
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="action" value="clear">
                        <button class="btn btn-sm btn-outline-danger" type="submit">Clear Cart</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h2 class="h5 mb-3">Order Summary</h2>

                    <form method="get" class="mb-3">
                        <label class="form-label small" for="offer">Offer code</label>
                        <div class="input-group">
                            <input class="form-control" id="offer" name="offer"
                                   value="<?= e($offerCode) ?>" placeholder="WELCOME10">
                            <button class="btn btn-outline-primary" type="submit">Apply</button>
                        </div>
                    </form>

                    <div class="d-flex justify-content-between mb-2">
                        <span>Subtotal</span>
                        <span>₹<?= number_format($subtotal, 2) ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-2 text-success">
                        <span>Discount</span>
                        <span>- ₹<?= number_format($discount, 2) ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span>Tax (<?= number_format($taxRate, 2) ?>%)</span>
                        <span>₹<?= number_format($taxAmount, 2) ?></span>
                    </div>
                    <hr>
                    <div class="d-flex justify-content-between fs-5 fw-bold mb-3">
                        <span>Total</span>
                        <span>₹<?= number_format($total, 2) ?></span>
                    </div>

                    <a class="btn btn-primary btn-lg w-100"
                       href="/SmartCanteenAI/student/checkout.php<?= $offerCode !== '' ? '?offer=' . urlencode($offerCode) : '' ?>">
                        Proceed to Checkout
                    </a>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
