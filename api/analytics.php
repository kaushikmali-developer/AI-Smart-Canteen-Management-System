<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_role('ADMIN','MANAGER');
header('Content-Type: application/json; charset=utf-8');

$days=max(1,min(365,(int)($_GET['days']??30)));
$start=date('Y-m-d 00:00:00',strtotime("-".($days-1)." days"));
$end=date('Y-m-d 23:59:59');
$pdo=db();

$s=$pdo->prepare("SELECT DATE(order_date) date, COUNT(*) orders, COALESCE(SUM(total_amount),0) revenue FROM orders WHERE order_date BETWEEN ? AND ? AND payment_status='PAID' AND order_status<>'CANCELLED' GROUP BY DATE(order_date) ORDER BY date");
$s->execute([$start,$end]); $daily=$s->fetchAll();

$s=$pdo->prepare("SELECT oi.item_name, SUM(oi.quantity) quantity, COALESCE(SUM(oi.subtotal),0) sales FROM order_items oi JOIN orders o ON o.order_id=oi.order_id WHERE o.order_date BETWEEN ? AND ? AND o.payment_status='PAID' AND o.order_status<>'CANCELLED' GROUP BY oi.item_id,oi.item_name ORDER BY quantity DESC LIMIT 10");
$s->execute([$start,$end]); $top=$s->fetchAll();

$s=$pdo->prepare("SELECT COUNT(*) orders, COALESCE(SUM(total_amount),0) revenue, COALESCE(AVG(total_amount),0) avg_order FROM orders WHERE order_date BETWEEN ? AND ? AND payment_status='PAID' AND order_status<>'CANCELLED'");
$s->execute([$start,$end]); $summary=$s->fetch();

echo json_encode(['success'=>true,'period'=>['days'=>$days,'from'=>$start,'to'=>$end],'summary'=>$summary,'daily'=>$daily,'top_items'=>$top],JSON_UNESCAPED_UNICODE|JSON_PRESERVE_ZERO_FRACTION);