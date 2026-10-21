<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/menu_images.php';
require_role('ADMIN');

$pageTitle = 'Menu Management';
$pdo = db();
$uploadDir = __DIR__ . '/../assets/uploads/menu';
$uploadWebPath = '/SmartCanteenAI/assets/uploads/menu/';
$defaultImage = $uploadWebPath . 'default-food.svg';
$allowedMimes = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/webp' => 'webp',
];
$maxUploadBytes = 5 * 1024 * 1024;

if (!is_dir($uploadDir)) {
    @mkdir($uploadDir, 0755, true);
}

function menu_image_web_path(?string $image, string $defaultImage): string
{
    $image = trim((string)$image);
    if ($image === '') {
        return $defaultImage;
    }
    if (preg_match('~^https?://~i', $image) || str_starts_with($image, '/')) {
        return $image;
    }
    return '/SmartCanteenAI/' . ltrim($image, '/');
}

function remove_menu_image(?string $image, string $uploadDir): void
{
    $image = trim((string)$image);
    if ($image === '' || preg_match('~^https?://~i', $image)) {
        return;
    }
    $relative = ltrim($image, '/');
    if (!str_starts_with($relative, 'SmartCanteenAI/assets/uploads/menu/')) {
        return;
    }
    $filename = basename($relative);
    if ($filename === '' || $filename === 'default-food.svg') {
        return;
    }
    $path = $uploadDir . DIRECTORY_SEPARATOR . $filename;
    if (is_file($path)) {
        @unlink($path);
    }
}

function save_menu_upload(array $file, string $uploadDir, array $allowedMimes, int $maxUploadBytes): string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return '';
    }
    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('The image upload failed. Please try again.');
    }
    if (($file['size'] ?? 0) <= 0 || (int)$file['size'] > $maxUploadBytes) {
        throw new RuntimeException('Food image must be smaller than 5 MB.');
    }

    $tmp = (string)($file['tmp_name'] ?? '');
    if ($tmp === '' || !is_uploaded_file($tmp)) {
        throw new RuntimeException('Invalid image upload.');
    }

    $info = @getimagesize($tmp);
    if ($info === false || empty($info['mime']) || !isset($allowedMimes[$info['mime']])) {
        throw new RuntimeException('Only JPG, PNG and WEBP food images are allowed.');
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = $finfo ? finfo_file($finfo, $tmp) : false;
    if ($finfo) {
        finfo_close($finfo);
    }
    if (!is_string($mime) || !isset($allowedMimes[$mime])) {
        throw new RuntimeException('The uploaded file is not a supported image.');
    }

    $filename = bin2hex(random_bytes(16)) . '.' . $allowedMimes[$mime];
    $destination = rtrim($uploadDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $filename;
    if (!move_uploaded_file($tmp, $destination)) {
        throw new RuntimeException('Could not save the food image. Check the uploads folder permissions.');
    }
    return 'assets/uploads/menu/' . $filename;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf((string)($_POST['csrf_token'] ?? ''))) {
        set_flash('error', 'Invalid security token. Please try again.');
        redirect('/SmartCanteenAI/admin/menu.php');
    }

    try {
        $action = (string)($_POST['action'] ?? '');

        if ($action === 'save') {
            $id = (int)($_POST['item_id'] ?? 0);
            $cat = (int)($_POST['category_id'] ?? 0);
            $name = trim((string)($_POST['item_name'] ?? ''));
            $desc = trim((string)($_POST['description'] ?? ''));
            $price = (float)($_POST['price'] ?? 0);
            $cost = (float)($_POST['cost_price'] ?? 0);
            $prep = max(0, (int)($_POST['preparation_time'] ?? 10));
            $available = isset($_POST['is_available']) ? 1 : 0;
            $removeImage = isset($_POST['remove_image']);

            if ($name === '' || $cat <= 0 || $price < 0 || $cost < 0) {
                throw new RuntimeException('Enter valid item details.');
            }

            $existing = null;
            if ($id > 0) {
                $s = $pdo->prepare('SELECT * FROM menu_items WHERE item_id = ? LIMIT 1');
                $s->execute([$id]);
                $existing = $s->fetch(PDO::FETCH_ASSOC);
                if (!$existing) {
                    throw new RuntimeException('Menu item not found.');
                }
            }

            $newImage = '';
            if (!empty($_FILES['food_image']['name'] ?? '')) {
                $newImage = save_menu_upload($_FILES['food_image'], $uploadDir, $allowedMimes, $maxUploadBytes);
            }

            $currentImage = (string)($existing['image'] ?? '');
            $imageToStore = $currentImage;

            if ($newImage !== '') {
                $imageToStore = $newImage;
            } elseif ($removeImage) {
                $imageToStore = null;
            }

            if ($id > 0) {
                $s = $pdo->prepare(
                    'UPDATE menu_items
                     SET category_id=?, item_name=?, description=?, price=?, cost_price=?, image=?, preparation_time=?, is_available=?
                     WHERE item_id=?'
                );
                $s->execute([$cat, $name, $desc !== '' ? $desc : null, $price, $cost, $imageToStore ?: null, $prep, $available, $id]);

                if ($newImage !== '' || $removeImage) {
                    if ($newImage !== '' && $currentImage !== '') {
                        remove_menu_image($currentImage, $uploadDir);
                    } elseif ($removeImage && $currentImage !== '') {
                        remove_menu_image($currentImage, $uploadDir);
                    }
                }
                set_flash('success', $newImage !== '' ? 'Menu item updated with a new food image.' : 'Menu item updated.');
            } else {
                $s = $pdo->prepare(
                    'INSERT INTO menu_items(category_id,item_name,description,price,cost_price,image,preparation_time,is_available)
                     VALUES(?,?,?,?,?,?,?,?)'
                );
                $s->execute([$cat, $name, $desc !== '' ? $desc : null, $price, $cost, $imageToStore ?: null, $prep, $available]);
                set_flash('success', 'Menu item created.');
            }
        } elseif ($action === 'toggle') {
            $id = (int)($_POST['item_id'] ?? 0);
            $pdo->prepare('UPDATE menu_items SET is_available = 1 - is_available WHERE item_id = ?')->execute([$id]);
            set_flash('success', 'Availability updated.');
        } elseif ($action === 'remove_image') {
            $id = (int)($_POST['item_id'] ?? 0);
            $s = $pdo->prepare('SELECT image FROM menu_items WHERE item_id = ? LIMIT 1');
            $s->execute([$id]);
            $row = $s->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                throw new RuntimeException('Menu item not found.');
            }
            remove_menu_image((string)$row['image'], $uploadDir);
            $pdo->prepare('UPDATE menu_items SET image = NULL WHERE item_id = ?')->execute([$id]);
            set_flash('success', 'Custom image removed. The default food image is now active.');
        }
    } catch (Throwable $e) {
        set_flash('error', str_contains($e->getMessage(), 'Duplicate') ? 'Menu item already exists.' : $e->getMessage());
    }

    redirect('/SmartCanteenAI/admin/menu.php');
}

$edit = null;
$id = (int)($_GET['edit'] ?? 0);
if ($id > 0) {
    $s = $pdo->prepare('SELECT * FROM menu_items WHERE item_id = ? LIMIT 1');
    $s->execute([$id]);
    $edit = $s->fetch(PDO::FETCH_ASSOC) ?: null;
}

$categories = $pdo->query(
    'SELECT category_id, category_name FROM categories WHERE is_active=1 ORDER BY sort_order, category_name'
)->fetchAll(PDO::FETCH_ASSOC);

$items = $pdo->query(
    'SELECT m.*, c.category_name FROM menu_items m
     JOIN categories c ON c.category_id=m.category_id
     ORDER BY c.sort_order, m.item_name'
)->fetchAll(PDO::FETCH_ASSOC);

include __DIR__ . '/../includes/header.php';
?>

<style>
.sc-admin-menu-hero{background:linear-gradient(135deg,#fff7f2 0%,#fff 58%);border:1px solid #f1e7e1;border-radius:18px;padding:24px;margin-bottom:22px}
.sc-menu-editor{border:1px solid #eee8e4;border-radius:16px;box-shadow:0 6px 24px rgba(40,44,63,.06);overflow:hidden}
.sc-menu-editor .card-body{padding:24px}
.sc-image-preview{height:190px;border-radius:14px;background:#f6f6f6;overflow:hidden;border:1px solid #eee;position:relative}
.sc-image-preview img{width:100%;height:100%;object-fit:cover;display:block}
.sc-upload-zone{border:1px dashed #ffb99b;background:#fffaf7;border-radius:13px;padding:15px}
.sc-upload-zone .form-control{background:#fff}
.sc-menu-table-card{border:1px solid #eee8e4;border-radius:16px;overflow:hidden;box-shadow:0 6px 24px rgba(40,44,63,.05)}
.sc-admin-thumb{width:76px;height:58px;object-fit:cover;border-radius:10px;border:1px solid #eee;background:#f6f6f6}
.sc-default-badge{font-size:11px;font-weight:700;background:#f3f3f3;color:#6f737c;border-radius:20px;padding:4px 8px}
</style>

<div class="sc-admin-menu-hero d-flex flex-wrap justify-content-between align-items-center gap-3">
    <div>
        <div class="text-uppercase small fw-bold" style="color:#ff5200;letter-spacing:.08em">Canteen catalogue</div>
        <h1 class="h3 fw-bold mb-1 mt-1">Menu Management</h1>
        <p class="text-secondary mb-0">Give every food item its own image, price and availability.</p>
    </div>
    <div class="d-flex align-items-center gap-2">
        <span class="badge rounded-pill text-bg-light border px-3 py-2"><?= count($items) ?> items</span>
        <a class="btn btn-primary" href="/SmartCanteenAI/student/menu.php" target="_blank"><i class="bi bi-eye me-1"></i>View Menu</a>
    </div>
</div>

<?php if (($flash = get_flash('success')) !== null): ?>
    <div class="alert alert-success border-0 shadow-sm"><?= e($flash) ?></div>
<?php endif; ?>
<?php if (($flash = get_flash('error')) !== null): ?>
    <div class="alert alert-danger border-0 shadow-sm"><?= e($flash) ?></div>
<?php endif; ?>

<div class="row g-4">
    <div class="col-xl-4">
        <div class="card bg-white sc-menu-editor h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div>
                        <h2 class="h5 fw-bold mb-1"><?= $edit ? 'Edit food item' : 'Add food item' ?></h2>
                        <p class="small text-secondary mb-0">Upload a real food photo or use the default automatically.</p>
                    </div>
                    <?php if ($edit): ?><span class="badge rounded-pill" style="background:#fff0e9;color:#ff5200">Editing #<?= (int)$edit['item_id'] ?></span><?php endif; ?>
                </div>

                <form method="post" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="save">
                    <input type="hidden" name="item_id" value="<?= (int)($edit['item_id'] ?? 0) ?>">

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Food image</label>
                        <div class="sc-image-preview mb-2">
                            <?php $preview = smartcanteen_resolve_menu_image($edit['image'] ?? '', $edit['item_name'] ?? '', $defaultImage); ?>
                            <img id="foodImagePreview" src="<?= e($preview) ?>" alt="Food preview" onerror="this.src='<?= e($defaultImage) ?>'">
                        </div>
                        <div class="sc-upload-zone">
                            <input class="form-control" type="file" id="food_image" name="food_image" accept="image/jpeg,image/png,image/webp">
                            <div class="small text-secondary mt-2"><i class="bi bi-info-circle me-1"></i>JPG, PNG or WEBP · maximum 5 MB</div>
                            <?php if ($edit && !empty($edit['image'])): ?>
                                <div class="form-check mt-3">
                                    <input class="form-check-input" type="checkbox" name="remove_image" id="remove_image">
                                    <label class="form-check-label small" for="remove_image">Remove current image and use default</label>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Category</label>
                        <select class="form-select" name="category_id" required>
                            <option value="">Select category</option>
                            <?php foreach ($categories as $c): ?>
                                <option value="<?= (int)$c['category_id'] ?>" <?= ((int)($edit['category_id'] ?? 0) === (int)$c['category_id']) ? 'selected' : '' ?>><?= e($c['category_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Item name</label>
                        <input class="form-control" name="item_name" required value="<?= e($edit['item_name'] ?? '') ?>" placeholder="e.g. Paneer Roll">
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Description</label>
                        <textarea class="form-control" name="description" rows="3" placeholder="Short description shown to students"><?= e($edit['description'] ?? '') ?></textarea>
                    </div>

                    <div class="row">
                        <div class="col-6 mb-3">
                            <label class="form-label fw-semibold">Selling price</label>
                            <div class="input-group"><span class="input-group-text">₹</span><input class="form-control" type="number" step="0.01" min="0" name="price" required value="<?= e((string)($edit['price'] ?? '')) ?>"></div>
                        </div>
                        <div class="col-6 mb-3">
                            <label class="form-label fw-semibold">Cost price</label>
                            <div class="input-group"><span class="input-group-text">₹</span><input class="form-control" type="number" step="0.01" min="0" name="cost_price" required value="<?= e((string)($edit['cost_price'] ?? '')) ?>"></div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Preparation time</label>
                        <div class="input-group"><input class="form-control" type="number" min="0" name="preparation_time" value="<?= e((string)($edit['preparation_time'] ?? 10)) ?>"><span class="input-group-text">minutes</span></div>
                    </div>

                    <div class="form-check form-switch mb-4">
                        <input class="form-check-input" type="checkbox" role="switch" name="is_available" id="available" <?= (!$edit || (int)$edit['is_available']) ? 'checked' : '' ?>>
                        <label class="form-check-label fw-semibold" for="available">Available for students</label>
                    </div>

                    <button class="btn btn-primary w-100" type="submit"><i class="bi bi-check2-circle me-1"></i><?= $edit ? 'Update food item' : 'Add food item' ?></button>
                    <?php if ($edit): ?><a href="/SmartCanteenAI/admin/menu.php" class="btn btn-light border w-100 mt-2">Cancel editing</a><?php endif; ?>
                </form>
            </div>
        </div>
    </div>

    <div class="col-xl-8">
        <div class="sc-menu-table-card bg-white">
            <div class="p-3 p-lg-4 border-bottom d-flex flex-wrap justify-content-between align-items-center gap-2">
                <div><h2 class="h5 fw-bold mb-1">All food items</h2><p class="small text-secondary mb-0">Every item without a custom photo automatically uses the default image.</p></div>
            </div>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead><tr><th class="ps-4">Food</th><th>Category</th><th>Price</th><th>Status</th><th class="text-end pe-4">Actions</th></tr></thead>
                    <tbody>
                    <?php foreach ($items as $i): ?>
                        <?php $image = menu_image_web_path($i['image'] ?? '', $defaultImage); $hasCustomImage = trim((string)($i['image'] ?? '')) !== ''; ?>
                        <tr>
                            <td class="ps-4">
                                <div class="d-flex align-items-center gap-3">
                                    <img class="sc-admin-thumb" loading="lazy" src="<?= e($image) ?>" alt="<?= e($i['item_name']) ?>" onerror="this.src='<?= e($defaultImage) ?>'">
                                    <div><strong><?= e($i['item_name']) ?></strong><br><small class="text-secondary"><?= e((string)$i['description']) ?></small></div>
                                </div>
                            </td>
                            <td><?= e($i['category_name']) ?></td>
                            <td><strong>₹<?= number_format((float)$i['price'], 2) ?></strong></td>
                            <td><?= $i['is_available'] ? '<span class="badge text-bg-success">Available</span>' : '<span class="badge text-bg-secondary">Hidden</span>' ?><br><span class="sc-default-badge mt-1 d-inline-block"><?= $hasCustomImage ? 'Custom image' : 'Default image' ?></span></td>
                            <td class="text-end pe-4">
                                <a href="?edit=<?= (int)$i['item_id'] ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i> Edit</a>
                                <?php if ($hasCustomImage): ?>
                                    <form method="post" class="d-inline" onsubmit="return confirm('Remove this custom image and use the default food image?');">
                                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="remove_image"><input type="hidden" name="item_id" value="<?= (int)$i['item_id'] ?>">
                                        <button class="btn btn-sm btn-outline-secondary" title="Use default image"><i class="bi bi-image"></i></button>
                                    </form>
                                <?php endif; ?>
                                <form method="post" class="d-inline">
                                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="toggle"><input type="hidden" name="item_id" value="<?= (int)$i['item_id'] ?>">
                                    <button class="btn btn-sm btn-outline-secondary"><?= $i['is_available'] ? 'Hide' : 'Show' ?></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('food_image')?.addEventListener('change', function () {
    const file = this.files?.[0];
    const preview = document.getElementById('foodImagePreview');
    if (!file || !preview) return;
    if (!file.type.startsWith('image/')) return;
    preview.src = URL.createObjectURL(file);
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
