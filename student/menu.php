<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role('STUDENT');

$pdo = db();
$categories = $pdo->query(
    'SELECT category_id, category_name
     FROM categories
     WHERE is_active = 1
     ORDER BY sort_order, category_name'
)->fetchAll();

$categoryId = filter_input(INPUT_GET, 'category_id', FILTER_VALIDATE_INT) ?: 0;
$search = trim((string) ($_GET['search'] ?? ''));

$sql = 'SELECT mi.item_id, mi.item_name, mi.description, mi.price,
               mi.image, mi.preparation_time, c.category_name
        FROM menu_items mi
        INNER JOIN categories c ON c.category_id = mi.category_id
        WHERE mi.is_available = 1 AND c.is_active = 1';
$params = [];

if ($categoryId > 0) {
    $sql .= ' AND mi.category_id = ?';
    $params[] = $categoryId;
}
if ($search !== '') {
    $sql .= ' AND (mi.item_name LIKE ? OR mi.description LIKE ?)';
    $like = '%' . $search . '%';
    $params[] = $like;
    $params[] = $like;
}
$sql .= ' ORDER BY c.sort_order, mi.item_name';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$items = $stmt->fetchAll();

$page_title = 'Menu';
require __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <div>
        <h1 class="h3 fw-bold mb-1">Canteen Menu</h1>
        <p class="text-secondary mb-0">Choose your items and add them to your persistent cart.</p>
    </div>
    <div class="d-flex gap-2">
        <a class="btn btn-outline-primary" href="<?= e($scBasePath) ?>/student/cart.php">
            <i class="bi bi-cart3 me-1"></i>Cart
        </a>
        <a class="btn btn-primary" href="<?= e($scBasePath) ?>/student/orders.php">
            <i class="bi bi-receipt me-1"></i>My Orders
        </a>
    </div>
</div>

<div class="sc-category-strip">
    <a class="sc-category-pill <?= $categoryId === 0 ? 'active' : '' ?>" href="<?= e($scBasePath) ?>/student/menu.php">All</a>
    <?php foreach ($categories as $category): ?>
        <a class="sc-category-pill <?= $categoryId === (int) $category['category_id'] ? 'active' : '' ?>" href="<?= e($scBasePath) ?>/student/menu.php?category_id=<?= (int) $category['category_id'] ?>"><?= e($category['category_name']) ?></a>
    <?php endforeach; ?>
</div>

<form class="card border-0 shadow-sm mb-4 sc-menu-toolbar" method="get">
    <div class="card-body">
        <div class="row g-2">
            <div class="col-md-6">
                <label class="form-label" for="search">Search food</label>
                <input class="form-control" id="search" name="search"
                       value="<?= e($search) ?>" placeholder="e.g. sandwich, coffee">
            </div>
            <div class="col-md-4">
                <label class="form-label" for="category_id">Category</label>
                <select class="form-select" id="category_id" name="category_id">
                    <option value="0">All categories</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?= (int) $category['category_id'] ?>"
                            <?= $categoryId === (int) $category['category_id'] ? 'selected' : '' ?>>
                            <?= e($category['category_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button class="btn btn-primary w-100" type="submit">
                    <i class="bi bi-search me-1"></i>Search
                </button>
            </div>
        </div>
    </div>
</form>

<?php if (($flash = get_flash('success')) !== null): ?>
    <div class="alert alert-success"><?= e($flash) ?></div>
<?php endif; ?>
<?php if (($flash = get_flash('error')) !== null): ?>
    <div class="alert alert-danger"><?= e($flash) ?></div>
<?php endif; ?>

<?php if ($items === []): ?>
    <div class="alert alert-info">No menu items matched your search.</div>
<?php else: ?>
    <div class="row g-3">
        <?php foreach ($items as $item): ?>
            <div class="col-sm-6 col-lg-4 col-xl-3">
                <?php
                    // Image priority: admin-uploaded image -> locally downloaded real food photo -> default.
                    $foodImage = trim((string) ($item['image'] ?? ''));
                    if ($foodImage !== '') {
                        if (!preg_match('#^https?://#i', $foodImage)) {
                            $foodImage = rtrim($scBasePath, '/') . '/' . ltrim($foodImage, '/');
                        }
                    } else {
                        $slug = preg_replace('/[^a-z0-9]+/', '-', strtolower((string) $item['item_name'])) ?? '';
                        $slug = trim($slug, '-');
                        $localFile = __DIR__ . '/../assets/uploads/menu/builtin/' . $slug . '.jpg';
                        if ($slug !== '' && is_file($localFile) && filesize($localFile) > 5000) {
                            $foodImage = rtrim($scBasePath, '/') . '/assets/uploads/menu/builtin/' . $slug . '.jpg';
                        } else {
                            $foodImage = rtrim($scBasePath, '/') . '/assets/uploads/menu/default-food.svg';
                        }
                    }
                ?>
                <article class="sc-food-card">
                    <div class="sc-food-image" style="background-image:url('<?= e($foodImage) ?>')"></div>
                    <div class="sc-food-body">
                        <div class="d-flex justify-content-between align-items-start gap-2">
                            <div>
                                <h2 class="sc-food-title"><?= e($item['item_name']) ?></h2>
                                <div class="sc-food-price">₹<?= number_format((float) $item['price'], 2) ?></div>
                            </div>
                            <form method="post" action="<?= e($scBasePath) ?>/student/cart.php">
                                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                <input type="hidden" name="action" value="add">
                                <input type="hidden" name="item_id" value="<?= (int) $item['item_id'] ?>">
                                <input type="hidden" name="quantity" value="1">
                                <button class="btn sc-add-btn" type="submit">ADD</button>
                            </form>
                        </div>
                        <div class="sc-food-desc mt-1"><?= e($item['description'] ?: 'Freshly prepared canteen item.') ?></div>
                        <div class="sc-food-meta mt-2"><i class="bi bi-star-fill" style="color:#ffb000"></i> Fresh &nbsp; · &nbsp; <i class="bi bi-clock"></i> <?= (int) $item['preparation_time'] ?> min</div>
                    </div>
                </article>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
