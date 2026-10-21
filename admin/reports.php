<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_role('ADMIN');
$pageTitle = 'Sales Reports';

$from = (string)($_GET['from'] ?? date('Y-m-01'));
$to   = (string)($_GET['to'] ?? date('Y-m-d'));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) $from = date('Y-m-01');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) $to = date('Y-m-d');

$pdo = db();
$params = [$from . ' 00:00:00', $to . ' 23:59:59'];

$summaryStmt = $pdo->prepare("
    SELECT COUNT(*) orders,
           COALESCE(SUM(subtotal),0) subtotal,
           COALESCE(SUM(discount_amount),0) discounts,
           COALESCE(SUM(tax_amount),0) tax,
           COALESCE(SUM(total_amount),0) revenue,
           COALESCE(AVG(total_amount),0) avg_order
    FROM orders
    WHERE order_date BETWEEN ? AND ?
      AND payment_status='PAID'
      AND order_status<>'CANCELLED'
");
$summaryStmt->execute($params);
$summary = $summaryStmt->fetch();

$itemStmt = $pdo->prepare("
    SELECT oi.item_name,
           SUM(oi.quantity) quantity,
           SUM(oi.subtotal) sales
    FROM order_items oi
    JOIN orders o ON o.order_id=oi.order_id
    WHERE o.order_date BETWEEN ? AND ?
      AND o.payment_status='PAID'
      AND o.order_status<>'CANCELLED'
    GROUP BY oi.item_id, oi.item_name
    ORDER BY quantity DESC, sales DESC
    LIMIT 20
");
$itemStmt->execute($params);
$topItems = $itemStmt->fetchAll();

$dailyStmt = $pdo->prepare("
    SELECT DATE(order_date) sale_date,
           COUNT(*) orders,
           COALESCE(SUM(total_amount),0) revenue
    FROM orders
    WHERE order_date BETWEEN ? AND ?
      AND payment_status='PAID'
      AND order_status<>'CANCELLED'
    GROUP BY DATE(order_date)
    ORDER BY sale_date
");
$dailyStmt->execute($params);
$daily = $dailyStmt->fetchAll();

$categoryStmt = $pdo->prepare("
    SELECT c.category_name,
           SUM(oi.quantity) quantity,
           SUM(oi.subtotal) sales
    FROM order_items oi
    JOIN orders o ON o.order_id=oi.order_id
    LEFT JOIN menu_items m ON m.item_id=oi.item_id
    LEFT JOIN categories c ON c.category_id=m.category_id
    WHERE o.order_date BETWEEN ? AND ?
      AND o.payment_status='PAID'
      AND o.order_status<>'CANCELLED'
    GROUP BY c.category_id, c.category_name
    ORDER BY sales DESC
");
$categoryStmt->execute($params);
$categories = $categoryStmt->fetchAll();

include __DIR__ . '/../includes/header.php';
?>
<div class="container-fluid py-4">
  <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4">
    <div><h1 class="h3 mb-1">Sales Reports</h1><p class="text-secondary mb-0">DB-grounded sales reporting for the selected period.</p></div>
    <form class="row g-2" method="get">
      <div class="col-auto"><label class="form-label small mb-1">From</label><input class="form-control" type="date" name="from" value="<?=e($from)?>"></div>
      <div class="col-auto"><label class="form-label small mb-1">To</label><input class="form-control" type="date" name="to" value="<?=e($to)?>"></div>
      <div class="col-auto d-flex align-items-end"><button class="btn btn-primary">Apply</button></div>
    </form>
  </div>

  <div class="row g-3 mb-4">
    <?php foreach([
      ['Orders',(int)$summary['orders'],'bi-receipt'],
      ['Revenue','₹'.number_format((float)$summary['revenue'],2),'bi-currency-rupee'],
      ['Discounts','₹'.number_format((float)$summary['discounts'],2),'bi-tag'],
      ['Tax','₹'.number_format((float)$summary['tax'],2),'bi-percent'],
      ['Avg. Order','₹'.number_format((float)$summary['avg_order'],2),'bi-graph-up']
    ] as $card): ?>
      <div class="col-md-4 col-xl">
        <div class="card border-0 shadow-sm h-100"><div class="card-body">
          <i class="bi <?=$card[2]?> text-primary"></i><div class="small text-secondary mt-2"><?=e($card[0])?></div>
          <div class="h4 mb-0"><?=e((string)$card[1])?></div>
        </div></div>
      </div>
    <?php endforeach; ?>
  </div>

  <div class="row g-4">
    <div class="col-xl-7"><div class="card border-0 shadow-sm"><div class="card-body">
      <h2 class="h5">Daily Revenue</h2><canvas id="dailyChart" height="110"></canvas>
    </div></div></div>
    <div class="col-xl-5"><div class="card border-0 shadow-sm"><div class="card-body">
      <h2 class="h5">Category Performance</h2><canvas id="categoryChart" height="150"></canvas>
    </div></div></div>
    <div class="col-xl-7"><div class="card border-0 shadow-sm"><div class="card-body">
      <h2 class="h5">Top Menu Items</h2>
      <div class="table-responsive"><table class="table align-middle"><thead><tr><th>Item</th><th>Qty</th><th>Sales</th></tr></thead><tbody>
      <?php foreach($topItems as $r): ?><tr><td><?=e($r['item_name'])?></td><td><?=number_format((float)$r['quantity'],0)?></td><td>₹<?=number_format((float)$r['sales'],2)?></td></tr><?php endforeach; ?>
      <?php if(!$topItems): ?><tr><td colspan="3" class="text-secondary">No paid sales in this period.</td></tr><?php endif; ?>
      </tbody></table></div>
    </div></div>
    <div class="col-xl-5"><div class="card border-0 shadow-sm"><div class="card-body">
      <h2 class="h5">Category Table</h2>
      <div class="table-responsive"><table class="table"><thead><tr><th>Category</th><th>Qty</th><th>Sales</th></tr></thead><tbody>
      <?php foreach($categories as $r): ?><tr><td><?=e((string)$r['category_name'])?></td><td><?=number_format((float)$r['quantity'],0)?></td><td>₹<?=number_format((float)$r['sales'],2)?></td></tr><?php endforeach; ?>
      </tbody></table></div>
    </div></div>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const dailyLabels = <?=json_encode(array_column($daily,'sale_date'), JSON_UNESCAPED_UNICODE)?>;
const dailyRevenue = <?=json_encode(array_map('floatval',array_column($daily,'revenue')))?>;
new Chart(document.getElementById('dailyChart'), {type:'line',data:{labels:dailyLabels,datasets:[{label:'Revenue',data:dailyRevenue,tension:.25,fill:false}]},options:{responsive:true,plugins:{legend:{display:false}}}});
const catLabels = <?=json_encode(array_map(fn($r)=>(string)$r['category_name'],$categories), JSON_UNESCAPED_UNICODE)?>;
const catSales = <?=json_encode(array_map(fn($r)=>(float)$r['sales'],$categories))?>;
new Chart(document.getElementById('categoryChart'), {type:'doughnut',data:{labels:catLabels,datasets:[{data:catSales}]},options:{responsive:true}});
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>