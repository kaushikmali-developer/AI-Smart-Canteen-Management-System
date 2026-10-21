<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_role('ADMIN');
$pageTitle='Admin Dashboard';
$stats=[
 'users'=>(int)db()->query('SELECT COUNT(*) FROM users')->fetchColumn(),
 'items'=>(int)db()->query('SELECT COUNT(*) FROM menu_items')->fetchColumn(),
 'categories'=>(int)db()->query('SELECT COUNT(*) FROM categories')->fetchColumn(),
 'low_stock'=>(int)db()->query('SELECT COUNT(*) FROM inventory WHERE is_active=1 AND current_stock<=minimum_stock')->fetchColumn(),
 'orders_today'=>(int)db()->query('SELECT COUNT(*) FROM orders WHERE DATE(order_date)=CURDATE()')->fetchColumn(),
 'sales_today'=>(float)db()->query("SELECT COALESCE(SUM(total_amount),0) FROM orders WHERE DATE(order_date)=CURDATE() AND payment_status='PAID' AND order_status<>'CANCELLED'")->fetchColumn()
];
include __DIR__.'/../includes/header.php';?>
<div class="container-fluid py-4"><div class="mb-4"><h1 class="h3">Admin Dashboard</h1><p class="text-secondary">Manage the SmartCanteen platform.</p></div>
<div class="row g-3 mb-4"><?php foreach([['Users',$stats['users'],'bi-people'],['Menu Items',$stats['items'],'bi-cup-hot'],['Categories',$stats['categories'],'bi-grid'],['Low Stock',$stats['low_stock'],'bi-exclamation-triangle'],['Orders Today',$stats['orders_today'],'bi-receipt'],['Sales Today','₹'.number_format($stats['sales_today'],2),'bi-currency-rupee']] as $c):?><div class="col-md-4 col-xl-2"><div class="card kpi-card border-0 shadow-sm h-100"><div class="card-body"><i class="bi <?=$c[2]?> text-primary"></i><div class="text-secondary small mt-2"><?=e($c[0])?></div><div class="h4 mb-0"><?=e((string)$c[1])?></div></div></div></div><?php endforeach;?></div>
<div class="row g-3"><div class="col-md-6 col-xl-3"><a class="card border-0 shadow-sm text-decoration-none h-100" href="/SmartCanteenAI/admin/menu.php"><div class="card-body"><h2 class="h5">Menu</h2><p class="text-secondary mb-0">Add, edit and control menu availability.</p></div></a></div><div class="col-md-6 col-xl-3"><a class="card border-0 shadow-sm text-decoration-none h-100" href="/SmartCanteenAI/admin/categories.php"><div class="card-body"><h2 class="h5">Categories</h2><p class="text-secondary mb-0">Organize the menu.</p></div></a></div><div class="col-md-6 col-xl-3"><a class="card border-0 shadow-sm text-decoration-none h-100" href="/SmartCanteenAI/admin/inventory.php"><div class="card-body"><h2 class="h5">Inventory</h2><p class="text-secondary mb-0">Monitor stock and adjustments.</p></div></a></div><div class="col-md-6 col-xl-3"><a class="card border-0 shadow-sm text-decoration-none h-100" href="/SmartCanteenAI/admin/suppliers.php"><div class="card-body"><h2 class="h5">Suppliers</h2><p class="text-secondary mb-0">Manage supplier contacts.</p></div></a></div><div class="col-md-6 col-xl-3"><a class="card border-0 shadow-sm text-decoration-none h-100" href="/SmartCanteenAI/admin/purchases.php"><div class="card-body"><h2 class="h5">Purchases</h2><p class="text-secondary mb-0">Record received stock.</p></div></a></div></div></div>
<?php include __DIR__.'/../includes/footer.php';?>