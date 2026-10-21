<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/functions.php';
$page_title = $page_title ?? 'SmartCanteen AI';
$user = current_user();
$role = $user['role_name'] ?? null;

// Works both on local XAMPP (/SmartCanteenAI) and root-hosted sites (InfinityFree /).
$projectRoot = realpath(__DIR__ . '/..');
$documentRoot = isset($_SERVER['DOCUMENT_ROOT']) ? realpath($_SERVER['DOCUMENT_ROOT']) : false;
$scBasePath = '';
if ($projectRoot && $documentRoot && str_starts_with(str_replace('\\', '/', $projectRoot), str_replace('\\', '/', $documentRoot))) {
    $relative = str_replace('\\', '/', substr(str_replace('\\', '/', $projectRoot), strlen(str_replace('\\', '/', $documentRoot))));
    $scBasePath = rtrim($relative, '/');
}
$scBasePath = $scBasePath === '/' ? '' : $scBasePath;
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($page_title) ?> | SmartCanteen AI</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link href="<?= e($scBasePath) ?>/assets/css/premium.css" rel="stylesheet">
<link href="<?= e($scBasePath) ?>/assets/css/swiggy-inspired.css" rel="stylesheet">
</head>
<body class="sc-app sc-page-<?= e(basename($_SERVER['PHP_SELF'], '.php')) ?> sc-role-<?= e(strtolower((string)($role ?? 'guest'))) ?>">
<nav class="navbar navbar-expand-lg sticky-top">
 <div class="container-fluid px-3 px-lg-4">
  <a class="navbar-brand" href="<?= e($scBasePath) ?>/">
   <i class="bi bi-cup-hot-fill"></i><span>SmartCanteen <span style="color:#ff5200">AI</span></span>
  </a>
  <button class="navbar-toggler border-0 text-white" type="button" data-bs-toggle="collapse" data-bs-target="#scNav" aria-controls="scNav" aria-expanded="false" aria-label="Toggle navigation">
   <i class="bi bi-list fs-3"></i>
  </button>
  <div class="collapse navbar-collapse" id="scNav">
   <?php if ($role === 'STUDENT'): ?>
    <div class="d-flex flex-wrap align-items-center gap-2 ms-lg-4 mt-3 mt-lg-0 me-auto">
      <a class="btn btn-sm btn-outline-secondary" href="<?= e($scBasePath) ?>/student/menu.php"><i class="bi bi-grid me-1"></i>Menu</a>
      <a class="btn btn-sm btn-outline-secondary" href="<?= e($scBasePath) ?>/student/cart.php"><i class="bi bi-cart3 me-1"></i>Cart</a>
      <a class="btn btn-sm btn-outline-secondary" href="<?= e($scBasePath) ?>/student/orders.php"><i class="bi bi-receipt me-1"></i>Orders</a>
    </div>
   <?php elseif ($role === 'STAFF'): ?>
    <div class="d-flex flex-wrap align-items-center gap-2 ms-lg-4 mt-3 mt-lg-0 me-auto">
      <a class="btn btn-sm btn-outline-secondary" href="<?= e($scBasePath) ?>/staff/staff_dashboard.php"><i class="bi bi-speedometer2 me-1"></i>Dashboard</a>
      <a class="btn btn-sm btn-outline-secondary" href="<?= e($scBasePath) ?>/staff/orders.php"><i class="bi bi-receipt me-1"></i>Orders</a>
      <a class="btn btn-sm btn-outline-secondary" href="<?= e($scBasePath) ?>/staff/kitchen.php"><i class="bi bi-fire me-1"></i>Kitchen</a>
    </div>
   <?php elseif ($role === 'ADMIN'): ?>
    <div class="d-flex flex-wrap align-items-center gap-2 ms-lg-4 mt-3 mt-lg-0 me-auto">
      <a class="btn btn-sm btn-outline-secondary" href="<?= e($scBasePath) ?>/admin/admin_dashboard.php"><i class="bi bi-grid-1x2 me-1"></i>Dashboard</a>
      <a class="btn btn-sm btn-outline-secondary" href="<?= e($scBasePath) ?>/admin/menu.php"><i class="bi bi-journal-text me-1"></i>Menu</a>
      <a class="btn btn-sm btn-outline-secondary" href="<?= e($scBasePath) ?>/admin/inventory.php"><i class="bi bi-box-seam me-1"></i>Inventory</a>
      <a class="btn btn-sm btn-outline-secondary" href="<?= e($scBasePath) ?>/admin/reports.php"><i class="bi bi-bar-chart-line me-1"></i>Reports</a>
      <a class="btn btn-sm btn-primary" href="<?= e($scBasePath) ?>/ai/"><i class="bi bi-stars me-1"></i>AI Command Center</a>
    </div>
   <?php elseif ($role === 'MANAGER'): ?>
    <div class="d-flex flex-wrap align-items-center gap-2 ms-lg-4 mt-3 mt-lg-0 me-auto">
      <a class="btn btn-sm btn-outline-secondary" href="<?= e($scBasePath) ?>/manager/manager_dashboard.php"><i class="bi bi-grid-1x2 me-1"></i>Dashboard</a>
      <a class="btn btn-sm btn-outline-secondary" href="<?= e($scBasePath) ?>/manager/analytics.php"><i class="bi bi-bar-chart-line me-1"></i>Analytics</a>
      <a class="btn btn-sm btn-primary" href="<?= e($scBasePath) ?>/ai/"><i class="bi bi-stars me-1"></i>AI Command Center</a>
    </div>
   <?php else: ?><div class="me-auto"></div><?php endif; ?>
   <?php if ($user): ?>
   <div class="d-flex align-items-center gap-2 mt-3 mt-lg-0">
    <div class="text-end d-none d-md-block">
      <div class="small fw-semibold text-white"><?= e($user['full_name']) ?></div>
      <div class="small" style="color:#686b78"><?= e($role ?? '') ?></div>
    </div>
    <div class="rounded-circle d-flex align-items-center justify-content-center" style="width:38px;height:38px;background:#fff3ed;color:#ff5200;font-weight:800;border:1px solid #ffe0d0">
      <?= e(strtoupper(substr((string)$user['full_name'],0,1))) ?>
    </div>
    <a class="btn btn-outline-secondary btn-sm" href="<?= e($scBasePath) ?>/auth/logout.php" title="Logout"><i class="bi bi-box-arrow-right"></i></a>
   </div>
   <?php endif; ?>
  </div>
 </div>
</nav>
<main class="page-shell container-fluid px-3 px-lg-4">
