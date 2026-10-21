<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_role('ADMIN');
$pageTitle='Purchases';

if($_SERVER['REQUEST_METHOD']==='POST'){
 if(!verify_csrf((string)($_POST['csrf_token']??''))){set_flash('error','Invalid security token.');redirect('/SmartCanteenAI/admin/purchases.php');}
 try{
  $supplier=(int)$_POST['supplier_id'];$invoice=trim((string)$_POST['invoice_number']);$tax=(float)($_POST['tax_amount']??0);$status=(string)($_POST['status']??'DRAFT');$notes=trim((string)$_POST['notes']);$inv=(array)($_POST['inventory_id']??[]);$qty=(array)($_POST['quantity']??[]);$cost=(array)($_POST['unit_cost']??[]);
  if($supplier<=0||!in_array($status,['DRAFT','RECEIVED','CANCELLED'],true))throw new RuntimeException('Select a valid supplier and status.');
  $lines=[];$subtotal=0.0;
  foreach($inv as $k=>$inventoryId){$iid=(int)$inventoryId;$q=(float)($qty[$k]??0);$c=(float)($cost[$k]??0);if($iid>0&&$q>0&&$c>=0){$lines[]=[$iid,$q,$c];$subtotal+=round($q*$c,2);}}
  if(!$lines)throw new RuntimeException('Add at least one purchase item.');
  $total=$subtotal+$tax;$pdo=db();$pdo->beginTransaction();
  $s=$pdo->prepare('INSERT INTO purchases(supplier_id,invoice_number,purchase_date,subtotal,tax_amount,total_amount,status,notes,created_by) VALUES(?,?,CURDATE(),?,?,?,?,?,?)');$s->execute([$supplier,$invoice?:null,$subtotal,$tax,$total,$status,$notes?:null,current_user_id()]);$pid=(int)$pdo->lastInsertId();
  $pi=$pdo->prepare('INSERT INTO purchase_items(purchase_id,inventory_id,quantity,unit_cost,subtotal) VALUES(?,?,?,?,?)');$up=$pdo->prepare('UPDATE inventory SET current_stock=current_stock+?, cost_per_unit=? WHERE inventory_id=?');$tx=$pdo->prepare("INSERT INTO inventory_transactions(inventory_id,transaction_type,quantity_change,unit_cost,reference_type,reference_id,notes,created_by) VALUES(?,?,?,?,?,?,?,?)");
  foreach($lines as [$iid,$q,$c]){$pi->execute([$pid,$iid,$q,$c,round($q*$c,2)]);if($status==='RECEIVED'){$up->execute([$q,$c,$iid]);$tx->execute([$iid,'PURCHASE',$q,$c,'PURCHASE',$pid,'Purchase received',current_user_id()]);}}
  $pdo->commit();set_flash('success','Purchase recorded successfully.');
 }catch(Throwable $e){if(isset($pdo)&&$pdo instanceof PDO&&$pdo->inTransaction())$pdo->rollBack();set_flash('error',$e->getMessage());}
 redirect('/SmartCanteenAI/admin/purchases.php');
}
$suppliers=db()->query('SELECT supplier_id,supplier_name FROM suppliers WHERE is_active=1 ORDER BY supplier_name')->fetchAll();
$inventory=db()->query('SELECT inventory_id,ingredient_name,unit,cost_per_unit FROM inventory WHERE is_active=1 ORDER BY ingredient_name')->fetchAll();
$purchases=db()->query('SELECT p.*,s.supplier_name FROM purchases p JOIN suppliers s ON s.supplier_id=p.supplier_id ORDER BY p.purchase_id DESC LIMIT 50')->fetchAll();
include __DIR__.'/../includes/header.php';?>
<div class="container-fluid py-4"><div class="mb-4"><h1 class="h3">Purchases</h1><p class="text-secondary mb-0">Record supplier purchases and update stock when received.</p></div>
<div class="card border-0 shadow-sm mb-4"><div class="card-body"><form method="post" id="purchaseForm"><input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>">
<div class="row g-3"><div class="col-md-4"><label class="form-label">Supplier</label><select class="form-select" name="supplier_id" required><option value="">Select</option><?php foreach($suppliers as $s):?><option value="<?=$s['supplier_id']?>"><?=e($s['supplier_name'])?></option><?php endforeach;?></select></div><div class="col-md-3"><label class="form-label">Invoice number</label><input class="form-control" name="invoice_number"></div><div class="col-md-3"><label class="form-label">Status</label><select class="form-select" name="status"><option>RECEIVED</option><option>DRAFT</option><option>CANCELLED</option></select></div><div class="col-md-2"><label class="form-label">Tax</label><input class="form-control" type="number" step="0.01" min="0" name="tax_amount" value="0"></div></div>
<div class="table-responsive mt-4"><table class="table" id="purchaseLines"><thead><tr><th>Ingredient</th><th>Qty</th><th>Unit cost</th><th></th></tr></thead><tbody><tr><td><select class="form-select" name="inventory_id[]" required><option value="">Select</option><?php foreach($inventory as $i):?><option value="<?=$i['inventory_id']?>" data-cost="<?=$i['cost_per_unit']?>"><?=e($i['ingredient_name'])?> (<?=e($i['unit'])?>)</option><?php endforeach;?></select></td><td><input class="form-control" type="number" step="0.001" min="0.001" name="quantity[]" required></td><td><input class="form-control" type="number" step="0.01" min="0" name="unit_cost[]" required></td><td><button type="button" class="btn btn-outline-danger remove-line">Remove</button></td></tr></tbody></table></div>
<button type="button" class="btn btn-outline-secondary" id="addLine">+ Add Item</button><input type="hidden" name="notes" value=""><button class="btn btn-primary float-end">Save Purchase</button></form></div></div>
<div class="card border-0 shadow-sm"><div class="card-body"><h2 class="h5">Recent purchases</h2><div class="table-responsive"><table class="table"><thead><tr><th>#</th><th>Supplier</th><th>Invoice</th><th>Total</th><th>Status</th><th>Date</th></tr></thead><tbody><?php foreach($purchases as $p):?><tr><td><?=$p['purchase_id']?></td><td><?=e($p['supplier_name'])?></td><td><?=e((string)$p['invoice_number'])?></td><td>₹<?=number_format((float)$p['total_amount'],2)?></td><td><?=e($p['status'])?></td><td><?=e($p['purchase_date'])?></td></tr><?php endforeach;?></tbody></table></div></div></div></div>
<script>
const tbody=document.querySelector('#purchaseLines tbody');
document.querySelector('#addLine').addEventListener('click',()=>{const row=tbody.querySelector('tr').cloneNode(true);row.querySelectorAll('input').forEach(x=>x.value='');row.querySelector('select').selectedIndex=0;tbody.appendChild(row);});
tbody.addEventListener('click',e=>{if(e.target.classList.contains('remove-line')&&tbody.rows.length>1)e.target.closest('tr').remove();});
tbody.addEventListener('change',e=>{if(e.target.name==='inventory_id[]'){const o=e.target.selectedOptions[0];const c=o?.dataset.cost;if(c!==undefined)e.target.closest('tr').querySelector('input[name="unit_cost[]"]').value=c;}});
</script>
<?php include __DIR__.'/../includes/footer.php';?>