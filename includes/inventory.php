<?php
/**
 * SmartCanteen AI - Inventory consumption helpers
 *
 * Inventory is consumed only when an order reaches COMPLETED.
 * All stock changes are transactional and audited in inventory_transactions.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';

function consume_order_inventory(int $orderId, ?int $actorUserId = null): array
{
    $pdo = db();

    try {
        $pdo->beginTransaction();

        // Lock the order so two completion requests cannot process it simultaneously.
        $stmt = $pdo->prepare(
            "SELECT order_id, order_status
             FROM orders
             WHERE order_id = ?
             FOR UPDATE"
        );
        $stmt->execute([$orderId]);
        $order = $stmt->fetch();

        if (!$order) {
            throw new RuntimeException('Order not found.');
        }

        // Idempotency: if this order was already consumed, do not consume it again.
        $stmt = $pdo->prepare(
            "SELECT transaction_id
             FROM inventory_transactions
             WHERE reference_type = 'ORDER'
               AND reference_id = ?
               AND transaction_type = 'SALE_CONSUMPTION'
             LIMIT 1"
        );
        $stmt->execute([$orderId]);

        if ($stmt->fetch()) {
            $pdo->commit();
            return [
                'success' => true,
                'already_consumed' => true,
                'message' => 'Inventory for this order was already consumed.'
            ];
        }

        if ($order['order_status'] === 'CANCELLED') {
            throw new RuntimeException('Cancelled orders cannot consume inventory.');
        }

        // Build ingredient requirements from order quantities x recipe quantities.
        $stmt = $pdo->prepare(
            "SELECT
                oi.item_id,
                oi.item_name,
                oi.quantity AS order_quantity,
                mii.inventory_id,
                i.ingredient_name,
                i.unit,
                i.current_stock,
                i.cost_per_unit,
                (oi.quantity * mii.quantity_required) AS required_quantity
             FROM order_items oi
             INNER JOIN menu_item_ingredients mii ON mii.item_id = oi.item_id
             INNER JOIN inventory i ON i.inventory_id = mii.inventory_id
             WHERE oi.order_id = ?
             ORDER BY i.inventory_id
             FOR UPDATE"
        );
        $stmt->execute([$orderId]);
        $rows = $stmt->fetchAll();

        // Items without recipes are allowed; only mapped ingredients are consumed.
        if (!$rows) {
            $pdo->commit();
            return [
                'success' => true,
                'already_consumed' => false,
                'consumed_count' => 0,
                'message' => 'Order completed. No recipe ingredients are mapped for this order.'
            ];
        }

        // Aggregate in case an ingredient is used by multiple order items.
        $requirements = [];
        foreach ($rows as $row) {
            $inventoryId = (int)$row['inventory_id'];

            if (!isset($requirements[$inventoryId])) {
                $requirements[$inventoryId] = [
                    'inventory_id' => $inventoryId,
                    'ingredient_name' => $row['ingredient_name'],
                    'unit' => $row['unit'],
                    'current_stock' => (float)$row['current_stock'],
                    'cost_per_unit' => (float)$row['cost_per_unit'],
                    'required_quantity' => 0.0
                ];
            }

            $requirements[$inventoryId]['required_quantity'] += (float)$row['required_quantity'];
        }

        // Validate the complete order before changing any stock.
        $shortages = [];
        foreach ($requirements as $req) {
            if ($req['current_stock'] < $req['required_quantity']) {
                $shortages[] = sprintf(
                    '%s: need %.3f %s, available %.3f %s',
                    $req['ingredient_name'],
                    $req['required_quantity'],
                    $req['unit'],
                    $req['current_stock'],
                    $req['unit']
                );
            }
        }

        if ($shortages) {
            throw new RuntimeException(
                'Insufficient inventory: ' . implode('; ', $shortages)
            );
        }

        $update = $pdo->prepare(
            "UPDATE inventory
             SET current_stock = current_stock - ?
             WHERE inventory_id = ?"
        );

        $insert = $pdo->prepare(
            "INSERT INTO inventory_transactions
                (inventory_id, transaction_type, quantity_change, unit_cost,
                 reference_type, reference_id, notes, created_by)
             VALUES (?, 'SALE_CONSUMPTION', ?, ?, 'ORDER', ?, ?, ?)"
        );

        $consumedCount = 0;

        foreach ($requirements as $req) {
            $quantity = $req['required_quantity'];
            $update->execute([$quantity, $req['inventory_id']]);

            $notes = sprintf(
                'Automatic consumption for completed order #%d',
                $orderId
            );

            $insert->execute([
                $req['inventory_id'],
                -$quantity,
                $req['cost_per_unit'],
                $orderId,
                $notes,
                $actorUserId
            ]);

            $consumedCount++;
        }

        $pdo->commit();

        return [
            'success' => true,
            'already_consumed' => false,
            'consumed_count' => $consumedCount,
            'message' => 'Inventory consumed successfully.'
        ];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        return [
            'success' => false,
            'already_consumed' => false,
            'consumed_count' => 0,
            'message' => $e->getMessage()
        ];
    }
}
