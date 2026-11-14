# Task 008.1: Cart Foundation

**Status:** Implementation complete; committed on `feature/cart`  
**Branch:** `feature/cart` (created from `feature/pricing`)  
**Base Commit:** `91bb1bf` (Pricing 007D prerequisite commit, fast-forwarded into `feature/cart`)  

## Overview

Implemented the Cart module foundation for Infino e-commerce platform, including:
- Cart and CartItem models with migrations
- Authenticated (user_id-scoped) and guest (token-scoped) cart ownership
- Add item, update quantity, and remove item operations
- Variant validation through the Catalog boundary
- Quantity validation and duplicate variant handling
- Cart expiration checks
- Pricing reads through the existing `FindsVariantPrices` contract
- Contracts, actions, and module registration
- Pest tests following project conventions

Inventory availability checks were intentionally not wired: no existing Inventory
contract provides the read-only availability query needed, and stock reservation
is deferred to checkout (Task 008.4).

## Acceptance Criteria

- ✅ Cart can be created for authenticated users (user_id-scoped) and guests (token-scoped)
- ✅ Cart items reference `product_variants` via `variant_id` FK
- ✅ Quantity validation (min 1, max `Cart::MAX_ITEM_QUANTITY` = 999)
- ✅ Invalid or inactive variants → `addVariant()` returns false
- ✅ Cart expiration (`isExpired()` checks `expires_at`); expired carts reject mutations
- ✅ Cart ownership and access authorization (user_id- and token-scoped carts isolated)
- ✅ Duplicate variant handling (addVariant updates quantity if item already in cart)
- ✅ Price snapshots captured at add-time through `FindsVariantPrices`
- ✅ Existing Pricing tests still pass (34/34 no regressions)
- ✅ Database migrations applied successfully
- ✅ Cart Pest suite: 24/24 passing

## Files Created

### Models
- `src/app/Modules/Cart/Models/Cart.php` — Cart model with user_id/token scoping, `isExpired()`, `isGuest()`, `isAuthenticated()`, and all CartContract methods. Validates variants through the Catalog `FindsProductVariants` contract, reads prices through `FindsVariantPrices`, snapshots price/currency/compare-at at add-time, enforces quantity bounds, rejects mutations on expired carts, and locks the cart row inside a transaction.
- `src/app/Modules/Cart/Models/CartItem.php` — CartItem model with variant relation, price snapshots, `isValid()`, `getSubtotalMinor()`, `getSubtotalCurrency()`.

### Contracts
- `src/app/Modules/Cart/Contracts/CartContract.php` — Interface defining all cart operations (addVariant, updateQuantity, removeVariant, getQuantity, getItems, getTotalMinorAndCurrency, hasVariant, isExpired)

### Actions
- `src/app/Modules/Cart/Actions/AddCartItem.php` — Adapts `Cart::addVariant()` for use-case layers, converting validation failures into `ValidationException`
- `src/app/Modules/Cart/Actions/RemoveCartItem.php` — Delegates removal to `Cart::removeVariant()`
- `src/app/Modules/Cart/Actions/UpdateCartItemQuantity.php` — Delegates quantity updates to `Cart::updateQuantity()`, validating quantity bounds and expiry

### Database Migrations
- `src/app/Modules/Cart/Database/Migrations/z14_create_carts_table.php` — carts table with user_id, token, status, expires_at, foreign key to users
- `src/app/Modules/Cart/Database/Migrations/z15_create_cart_items_table.php` — cart_items with variant FK, price snapshots, unique (cart_id, variant_id) constraint

### Service Provider
- `src/app/Modules/Cart/Providers/CartServiceProvider.php` — Extends `BaseModuleServiceProvider` to load module providers, routes, and migrations

### Module Manifest
- `src/app/Modules/Cart/module.json` — Registered with dependencies on Core, Catalog, Pricing; providers CartServiceProvider

### Tests
- `src/tests/Feature/Cart/CartFoundationTest.php` — 24 Pest tests covering cart foundation operations, isolation, validation, price snapshots, and expiration

## Architecture Decisions

### Cart Ownership
- **Authenticated carts** are scoped to `user_id` — one cart per user
- **Guest carts** are scoped to a secure, unique token — one cart per token
- Both models support browsing and checkout for either customer type

### Price Snapshot Strategy
- Prices are **snapshotted at add-time** through the `FindsVariantPrices` contract
- The contract is read-only — no modification to Pricing internals
- Order creation will capture immutable price and currency snapshots
- Subsequent price changes via `SetVariantPrice` will NOT alter cart or order items

### Stock Reservation
- **Stock checks are read-only** — use existing Inventory contracts for availability checks
- **Stock reservation deferred** to checkout start, not when item is added
- No direct table queries on Inventory module from Cart

### Quantity Validation
- Minimum quantity: 1; maximum quantity per line item: `Cart::MAX_ITEM_QUANTITY` (999)
- Duplicate variants in the same cart are handled by updating quantity instead of creating separate line items
- Unique constraint on `(cart_id, variant_id)` prevents duplicate entries

### Cart Expiration
- `expires_at` timestamp on cart records
- `isExpired()` method checks if `expires_at` is in the past
- Expired carts reject `addVariant()`, `updateQuantity()`, and `removeVariant()`; reads remain available for display/cleanup
- Full TTL management (automatic cleanup/status transitions) is deferred to the application layer

## Test Results

| Metric | Result |
|---|---|
| Cart Pest tests passed | 24/24 (63 assertions) |
| Pricing Pest tests passed | 43/43 (122 assertions) |
| Cart + Pricing focused suite | 67 passed (185 assertions) |
| Catalog Filament suite | 81 passed (396 assertions) |
| Full suite | 487 passed, 3 failed (all pre-existing and unrelated to Cart) |
| Database migrations | Applied successfully on PostgreSQL, including a fresh-database run |

**Test environment:** Feature tests run against SQLite in-memory via `RefreshDatabase`. Carts reference real `users` rows created with `User::factory()`; variants are created with `ProductVariant::factory()` and priced through the `SetsVariantPrice` contract. This removed the earlier false failures caused by inserting a literal `user_id = 1` with no matching user row.

**Coverage:** module discovery, schema, authenticated/guest creation, guest token isolation, authenticated user isolation, active/inactive/missing variants, quantity bounds, duplicate handling, removal, quantity updates, totals, empty-cart default currency, add-time price snapshot stability across later price changes, and expired-cart mutation rejection.

**Verification command:**

```bash
docker compose run --rm --no-deps cli ./vendor/bin/pest tests/Feature/Cart/ tests/Feature/Pricing/
docker compose run --rm --no-deps cli ./vendor/bin/phpstan analyse app/Modules/Cart --no-progress
docker compose run --rm --no-deps cli ./vendor/bin/pint --test app/Modules/Cart tests/Feature/Cart
```

The 3 remaining full-suite failures are unrelated: the two-factor redirect tests (2) and `PostCreatePageTest` (1).

**Known limitations (not fixed in Task 008.1):**

- A variant with no current price can still be added: `Cart::addVariant()` snapshots `0` minor units when `FindsVariantPrices` returns `null`. Task 008.2 must reject unpriced variants before order creation, or Task 008.1 follow-up must make them unpurchasable.
- Cart migrations were renamed to `z14`/`z15`: the original timestamped names sorted before Catalog on a fresh PostgreSQL database, so `cart_items` referenced a not-yet-created `product_variants` table. Local `migrations` rows were updated in place rather than dropping data.

## Next Recommended Tasks

- **Task 008.2**: Order Foundation — order creation, order items, order status lifecycle
- **Task 008.3**: Checkout workflow — cart-to-order conversion, payment boundary
- **Task 008.4**: Inventory integration — stock reservation at checkout

## Dependencies

- Core module (generic helpers, base models)
- Catalog module (ProductVariant model for variant references)
- Pricing module (FindsVariantPrices contract for read-only price lookups)