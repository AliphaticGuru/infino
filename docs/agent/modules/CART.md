# Cart Module — Infino E-commerce Platform

## Module Overview

The Cart module manages shopping cart functionality for both authenticated and guest customers. It owns cart records, cart items, and cart operations, following the modular-monolith architecture principles.

- **Status:** Implemented (Task 008.1)
- **Version:** 1.0.0
- **Branch:** `feature/cart`
- **Base:** `feature/pricing` (DEC-023 Accepted)

## Module Manifest

```json
{
    "name": "Cart",
    "description": "Shopping cart management for authenticated and guest customers.",
    "version": "1.0.0",
    "enabled": true,
    "dependencies": [
        "Core",
        "Catalog",
        "Pricing"
    ],
    "providers": [
        "App\\Modules\\Cart\\Providers\\CartServiceProvider"
    ]
}
```

## Dependencies

| Dependency | Direction | Reason |
|---|---|---|
| **Core** | ← Cart | Generic helpers, base model utilities |
| **Catalog** | ← Cart | ProductVariant references for variant validation |
| **Pricing** | ← Cart | Read-only price lookups via `FindsVariantPrices` contract |
| **Cart** | → — | Owns carts, cart items, and cart operations |
| **Order** | — (deferred) | Order creation consumes validated cart via defined workflow |

## Database Schema

### `carts` Table

| Column | Type | Description |
|---|---|---|
| `id` | bigint (PK) | Primary key |
| `user_id` | bigint (FK→users, nullable) | Authenticated user scoping; nullable for guest carts |
| `token` | string (unique, nullable) | Guest cart token; unique identifier for anonymous carts |
| `status` | string (default: 'active') | Cart status: active, abandoned, converted, expired |
| `expires_at` | timestamp (nullable) | Cart expiration time |
| `created_at` | timestamp | Creation timestamp |
| `updated_at` | timestamp | Last update timestamp |

**Indexes:** `user_id + status`, `token + status`, `expires_at`

### `cart_items` Table

| Column | Type | Description |
|---|---|---|
| `id` | bigint (PK) | Primary key |
| `cart_id` | bigint (FK→carts) | Cart reference, cascade delete |
| `variant_id` | bigint (FK→product_variants) | Product variant reference, cascade delete |
| `quantity` | integer (default: 1) | Item quantity, min 1 |
| `price_amount_minor` | integer | Price in minor units, snapshotted at add-time |
| `price_currency` | char(3) (default: 'NGN') | Currency, snapshotted at add-time |
| `compare_at_amount_minor` | integer (nullable) | Compare-at price snapshot |

**Constraints:** `UNIQUE(cart_id, variant_id)` — one line item per variant per cart  
**Indexes:** `cart_id`, `variant_id`, `(cart_id, variant_id)`

## Key Models

### `App\Modules\Cart\Models\Cart`

- Implements `CartContract`
- `userId()` — returns user_id or null (guest cart)
- `token()` — returns token or null (authenticated cart)
- `isExpired()` — checks expires_at is past
- `isGuest()` — returns true if user_id is null
- `isAuthenticated()` — returns true if user_id is not null
- `addVariant(int $variantId, int $quantity = 1, ?int $actorId = null): bool` — adds a variant, validates it through the Catalog `FindsProductVariants` contract, reads price via `FindsVariantPrices`, captures price snapshots at add-time, and enforces quantity/expiry rules
- `updateQuantity(int $variantId, int $quantity): bool` — updates item quantity (1..`MAX_ITEM_QUANTITY`); false when missing or expired
- `removeVariant(int $variantId): bool` — removes item from cart; false when missing or expired
- `getQuantity(int $variantId): int` — returns quantity (0 if not in cart)
- `getItems(): Collection` — returns all cart items with variant eager-loaded
- `getTotalMinorAndCurrency(): array` — returns ['amount_minor' => int, 'currency' => string]; empty carts return 0 in the default currency
- `hasVariant(int $variantId): bool` — checks if variant is in cart
- `MAX_ITEM_QUANTITY` — 999, the per-line upper bound enforced on add/update

### `App\Modules\Cart\Models\CartItem`

- `cart(): BelongsTo` — relationship to Cart
- `variant(): BelongsTo` — relationship to ProductVariant
- `isValid(): bool` — returns true if the variant is active and quantity is within bounds
- `getSubtotalMinor(): int` — returns price_amount_minor * quantity
- `getSubtotalCurrency(): string` — returns price_currency

## Key Contracts

### `App\Modules\Cart\Contracts\CartContract`

Defines the interface for all cart operations:

- `addVariant(int $variantId, int $quantity = 1, ?int $actorId = null): bool`
- `updateQuantity(int $variantId, int $quantity): bool`
- `removeVariant(int $variantId): bool`
- `getQuantity(int $variantId): int`
- `getItems(): Collection`
- `getTotalMinorAndCurrency(): array`
- `hasVariant(int $variantId): bool`
- `isExpired(): bool`

## Actions

| Action | Responsibility |
|---|---|
| `AddCartItem` | Adapts `Cart::addVariant()` for use-case layers; validates quantity bounds/expiry and converts failures into `ValidationException` |
| `RemoveCartItem` | Delegates removal to `Cart::removeVariant()` |
| `UpdateCartItemQuantity` | Delegates quantity updates to `Cart::updateQuantity()`; validates quantity bounds and expiry |

## Pricing Integration

- Cart reads current prices through the **existing `FindsVariantPrices` contract**
- Prices are **snapshotted at add-time** into cart_item records
- Snapshots include: `price_amount_minor`, `price_currency`, `compare_at_amount_minor`
- **No modification** to Pricing internals — contract is read-only
- Order creation will capture its own immutable price snapshots
- Subsequent `SetVariantPrice` operations have no effect on cart or order items

## Inventory Integration

- Cart does **not** query Inventory tables directly
- No existing Inventory contract currently exposes a read-only availability query, so availability checks are not wired in Task 008.1
- Stock reservation is deferred to checkout start, not when an item is added
- Row locking (`lockForUpdate`) is available for the checkout phase

## Pest Tests

**Test file:** `src/tests/Feature/Cart/CartFoundationTest.php`

**Test categories:**
- Module discovery and registration
- Schema validation (carts, cart_items tables)
- Cart creation (authenticated + guest)
- Guest token isolation and authenticated user isolation
- Variant validation (active/inactive/missing)
- Quantity bounds (minimum, maximum, duplicates)
- Quantity updates
- Item removal
- Cart expiration (status + mutation rejection)
- Total calculation and empty-cart default currency
- Price snapshot stability after later price changes
- Subtotal calculation

**Results:** 24/24 tests pass (63 assertions). Pricing suite remains 34/34. The 7 remaining full-suite failures are pre-existing and unrelated to Cart (two-factor redirects, Catalog Filament `VariantPricePolicy` resolution, `PostCreatePageTest`).

## Architecture Boundary

### Cart ↔ Order

- **Cart owns:** cart records, cart items, cart operations (add, update, remove)
- **Order owns:** order records, order items, order numbering, order lifecycle
- **Cart must NOT directly create or persist Order records**
- **Order creation** consumes a validated cart through a clearly defined application-level workflow
- **Price snapshots** are captured at cart add-time for display and stability; Order creation captures its own independent immutable snapshots
- **Stock reservation** at checkout start, not when item is added to cart

### Security & Authorization

- Authenticated users see their own user_id-scoped carts
- Guests see token-scoped carts
- Price modifications admin-only via `VariantPricePolicy`
- Inventory checks read-only; write access deferred to checkout

## Deferred Scope (Phase 4.2+)

- Order persistence and order_number generation (`ORD-YYYYMMDD-XXXX`)
- Order status lifecycle (`draft` → `confirmed` → `processing` → ...)
- Payment integration and payment gateway connections
- Stock reservation/deduction at checkout
- Cart-to-order conversion workflow
- Guest cart persistence beyond session