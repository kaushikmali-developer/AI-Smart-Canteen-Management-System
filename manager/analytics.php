<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_role('MANAGER');
$pageTitle='Business Analytics';

$days = max(7, min(90, (int)($_GET['days'] ?? 30)));
$pdo = db();
$start = date('Y-m-d 00:00:00', strtotime("-".($days-1)." days"));
$end = date('Y-m-d 23:59:59');

$daily = $pdo->prepare("SELECT DATE(order_date) d, COUNT(*) orders, COALESCE(SUM(total_amount),0) revenue FROM orders WHERE order_date BETWEEN ? AND ? AND payment_status='PAID' AND order_status<>'CANCELLED' GROUP BY DATE(order_date) ORDER BY d");
$daily->execute([$start,$end]); $dailyRows=$daily->fetchAll();

$kpis = $pdo->prepare("SELECT COUNT(*) orders, COALESCE(SUM(total_amount),0) revenue, COALESCE(AVG(total_amount),0) avg_order FROM orders WHERE order_date BETWEEN ? AND ? AND payment_status='PAID' AND order_status<>'CANCELLED'");
$kpis->execute([$start,$end]); $kpi=$kpis->fetch();

$low = $pdo->query("SELECT ingredient_name,current_stock,minimum_stock,unit FROM inventory WHERE is_active=1 AND current_stock<=minimum_stock ORDER BY current_stock ASC LIMIT 10")->fetchAll();

include __DIR__.'/../includes/header.php'; ?>
<div class="container-fluid py-4">
<div class="d-flex flex-wrap justify-content-between align-items-end mb-4"><div><h1 class="h3 mb-1">Business Analytics</h1><p class="text-secondary mb-0">Management view based directly on stored sales and inventory data.</p></div>
<form method="get"><select class="form-select" name="days" onchange="this.form.submit()"><?php foreach([7,14,30,60,90] as $d):?><option value="<?=$d?>" <?=$days===$d?'selected':''?>>Last <?=$d?> days</option><?php endforeach;?></select></form></div>
<div class="row g-3 mb-4"><?php foreach([['Orders',(int)$kpi['orders']],['Revenue','₹'.number_format((float)$kpi['revenue'],2)],['Avg Order','₹'.number_format((float)$kpi['avg_order'],2)],['Low Stock',count($low)]] as $x):?><div class="col-md-3"><div class="card border-0 shadow-sm"><div class="card-body"><div class="small text-secondary"><?=e($x[0])?></div><div class="h4 mb-0"><?=e((string)$x[1])?></div></div></div></div><?php endforeach;?></div>
<div class="row g-4"><div class="col-xl-8"><div class="card border-0 shadow-sm"><div class="card-body"><h2 class="h5">Revenue Trend</h2><canvas id="trend" height="110"></canvas></div></div></div>
<div class="col-xl-4"><div class="card border-0 shadow-sm"><div class="card-body"><h2 class="h5">Low Stock Watch</h2><?php foreach($low as $x):?><div class="d-flex justify-content-between border-bottom py-2"><span><?=e($x['ingredient_name'])?></span><span class="text-warning"><?=number_format((float)$x['current_stock'],2).' '.e($x['unit'])?></span></div><?php endforeach;?><?php if(!$low):?><p class="text-secondary mb-0">No low-stock ingredients.</p><?php endif;?></div></div></div></div></div>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script><script>
new Chart(document.getElementById('trend'),{type:'line',data:{labels:<?=json_encode(array_column($dailyRows,'d'))?>,datasets:[{label:'Revenue',data:<?=json_encode(array_map(fn($r)=>(float)$r['revenue'],$dailyRows))?>,tension:.25}]},options:{responsive:true}});
</script>
<?php include __DIR__.'/../includes/footer.php'; ?>