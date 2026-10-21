# SmartCanteen AI — Phase 1 ER Architecture

## Final table count: 19

### Core identity
- `roles` 1 ─── N `users`

### Customer ordering
- `users` 1 ─── 1 `carts`
- `carts` 1 ─── N `cart_items`
- `menu_items` 1 ─── N `cart_items`
- `users` 1 ─── N `orders`
- `offers` 1 ─── N `orders` (nullable on orders)
- `orders` 1 ─── N `order_items`
- `menu_items` 1 ─── N `order_items`
- `orders` 1 ─── N `payments`
- `users` 1 ─── N `reviews`
- `menu_items` 1 ─── N `reviews`
- `orders` 1 ─── N `reviews` (optional)
- `users` 1 ─── N `notifications`
- `orders` 1 ─── N `notifications` (optional)

### Menu and recipes
- `categories` 1 ─── N `menu_items`
- `menu_items` 1 ─── N `menu_item_ingredients`
- `inventory` 1 ─── N `menu_item_ingredients`

### Inventory and supply chain
- `suppliers` 1 ─── N `inventory`
- `inventory` 1 ─── N `inventory_transactions`
- `suppliers` 1 ─── N `purchases`
- `purchases` 1 ─── N `purchase_items`
- `inventory` 1 ─── N `purchase_items`

### Configuration
- `users` 1 ─── N `settings` through `updated_by`

## Critical business flow

```text
USER
  │
  ├── CART
  │    └── CART_ITEMS
  │             │
  │             └── MENU_ITEM
  │
  └── CHECKOUT
        │
        ▼
      ORDER
        ├── ORDER_ITEMS ──► MENU_ITEM
        ├── PAYMENT
        └── OFFER

MENU_ITEM
    │
    └── MENU_ITEM_INGREDIENTS
              │
              ▼
          INVENTORY
              │
              └── INVENTORY_TRANSACTIONS

SUPPLIER
    ├── INVENTORY
    └── PURCHASE ──► PURCHASE_ITEMS ──► INVENTORY
```

## Important architectural decisions

### 1. Cart is not an order
`carts` and `cart_items` represent the customer's current shopping state.  
`orders` and `order_items` represent finalized historical transactions.

### 2. Historical order values are immutable snapshots
`order_items.unit_price` stores the price at checkout.

`orders.tax_rate`, `orders.tax_amount`, `orders.discount_amount`, and
`orders.total_amount` store the values actually applied to that order.

Changing the current menu price or tax setting therefore does not rewrite history.

### 3. Inventory is ingredient based
Inventory stores raw materials such as bread, cheese, oil, vegetables, etc.

`menu_item_ingredients` acts as the recipe/BOM bridge between menu sales and raw-material consumption.

### 4. Inventory movements are auditable
Every purchase, sale consumption, wastage, return, or manual adjustment can create an `inventory_transactions` row.

### 5. Deleted menu items do not destroy historical sales
`order_items.item_id` is nullable with `ON DELETE SET NULL`, while `item_name` remains as a historical snapshot.

### 6. AI must use database-grounded data
The intended analytics pipeline is:

```text
MySQL query
    ↓
Validated structured result
    ↓
Analytics / forecasting calculation
    ↓
AI interpretation
    ↓
User-facing insight
```

The AI layer must not invent revenue, stock, order counts, or other database facts.
