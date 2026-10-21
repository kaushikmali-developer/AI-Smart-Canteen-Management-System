<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_role('ADMIN','MANAGER');
$pageTitle='AI Analytics Assistant';

$question=trim((string)($_GET['q']??''));
$answer=null;$data=null;
if($question!==''){
    $days=30;
    if(preg_match('/(\d+)\s*days?/i',$question,$m)) $days=max(1,min(365,(int)$m[1]));
    $start=date('Y-m-d 00:00:00',strtotime("-".($days-1)." days")); $end=date('Y-m-d 23:59:59');
    $pdo=db();
    $s=$pdo->prepare("SELECT COUNT(*) orders, COALESCE(SUM(total_amount),0) revenue, COALESCE(AVG(total_amount),0) avg_order FROM orders WHERE order_date BETWEEN ? AND ? AND payment_status='PAID' AND order_status<>'CANCELLED'");
    $s->execute([$start,$end]);$summary=$s->fetch();
    $s=$pdo->prepare("SELECT oi.item_name,SUM(oi.quantity) quantity,COALESCE(SUM(oi.subtotal),0) sales FROM order_items oi JOIN orders o ON o.order_id=oi.order_id WHERE o.order_date BETWEEN ? AND ? AND o.payment_status='PAID' AND o.order_status<>'CANCELLED' GROUP BY oi.item_id,oi.item_name ORDER BY quantity DESC LIMIT 5");
    $s->execute([$start,$end]);$top=$s->fetchAll();
    $s=$pdo->query("SELECT ingredient_name,current_stock,minimum_stock,unit FROM inventory WHERE is_active=1 AND current_stock<=minimum_stock ORDER BY current_stock ASC LIMIT 5");$low=$s->fetchAll();
    $data=['summary'=>$summary,'top_items'=>$top,'low_stock'=>$low,'days'=>$days];

    $q=strtolower($question);
    if(str_contains($q,'low stock')||str_contains($q,'inventory')){
        $answer=$low ? 'There are '.count($low).' low-stock ingredients in the database for the current inventory snapshot.' : 'The current database snapshot shows no low-stock ingredients.';
    } elseif(str_contains($q,'top')||str_contains($q,'popular')||str_contains($q,'selling')){
        $answer=$top ? 'Top-selling items for the selected period are: '.implode(', ',array_map(fn($x)=>$x['item_name'].' ('.$x['quantity'].' units)',$top)).'.' : 'There are no paid sales in the selected period.';
    } else {
        $answer='For the selected '.$days.'-day period, the database contains '.(int)$summary['orders'].' paid, non-cancelled orders with revenue of ₹'.number_format((float)$summary['revenue'],2).' and an average order value of ₹'.number_format((float)$summary['avg_order'],2).'.';
    }
}
include __DIR__.'/../includes/header.php'; ?>
<div class="container-fluid py-4"><div class="mb-4"><h1 class="h3">AI Analytics Assistant</h1><p class="text-secondary mb-0">Prototype natural-language analytics grounded only in live database query results.</p></div>
<div class="card border-0 shadow-sm"><div class="card-body"><form method="get" class="row g-2"><div class="col-md-10"><input class="form-control form-control-lg" name="q" value="<?=e($question)?>" placeholder="Ask: What were the top selling items in the last 30 days?"></div><div class="col-md-2"><button class="btn btn-primary btn-lg w-100">Analyze</button></div></form></div></div>
<?php if($answer!==null):?><div class="card border-0 shadow-sm mt-4"><div class="card-body"><span class="badge text-bg-primary mb-2">DB-grounded answer</span><p class="lead mb-0"><?=e($answer)?></p></div></div><?php endif;?>
<div class="alert alert-info mt-4"><strong>AI safety rule:</strong> this prototype does not invent sales or inventory values. It queries the database first and then formats the returned structured data. A full LLM connector can be added later without changing the analytics data layer.</div>
</div>
<?php include __DIR__.'/../includes/footer.php'; ?>