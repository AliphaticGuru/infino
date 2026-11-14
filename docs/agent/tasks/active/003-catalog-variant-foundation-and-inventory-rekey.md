# Task 003 — Catalog Variant Foundation and Inventory Variant Re-key

**Status:** Complete and verified 2026-11-11 — uncommitted  
**Priority:** High  
**Branch:** `feature/catalog`  
**Base commit:** `636b0fc` — `feat(catalog): implemented catalog filament administration`  
**Decision:** DEC-019 in `docs/agent/DECISIONS.md`

## Objective

Adopt the approved variant-first product architecture:

1. Introduce `ProductVariant` as the canonical purchasable unit.
2. Create a default variant for every product, atomically for new products and
   idempotently for existing products.
3. Re-key Inventory from `product_id` to `variant_id` while preserving existing
   inventory rows, movement history, IDs, quantities and timestamps.
4. Fix the PostgreSQL migration-ordering problem that prevented a fresh database
   from migrating cleanly.

## Constraints

- Stay on `feature/catalog`; inspect Git state before changing files.
- Do not commit, merge, reset or discard user work.
- Preserve the modular monolith and module ownership.
- Keep business logic in Catalog/Inventory, not in Filament.
- Follow the existing Actions/Contracts/Data conventions.
- Preserve transactional adjustments, append-only movements, negative-stock
  protection, location tracking and reporting.
- Do not implement Pricing, attributes, media, Cart, Checkout, Orders, product
  deletion administration or unrelated Identity changes.
- Do not add dependencies.
- Verify fresh PostgreSQL migrations, not only SQLite.

## 1. Migration-ordering prerequisite

Module migrations are untimestamped and globally sorted by filename.
`create_inventories_table` sorts before `create_products_table`, so its inline
foreign key to `products` failed on a fresh PostgreSQL database.

Fix:
- Removed the inline `products` foreign key from `create_inventories_table`.
- Added the final `variant_id` foreign key in the re-key migrations, after
  `product_variants` exists.

## 2. Variant schema

`product_variants`:

| Column | Definition |
|---|---|
| `id` | bigint primary key |
| `product_id` | foreign key to `products`, `ON DELETE CASCADE` |
| `sku` | nullable string, globally unique |
| `label` | nullable string |
| `variant_key` | nullable string, unique per `(product_id, variant_key)` |
| `is_active` | boolean, default `true` |
| `position` | integer, default `0` |
| `created_at` / `updated_at` | timestamps |

Indexes:
- `(product_id, is_active)`
- `(product_id, position)`
- unique `(product_id, variant_key)`
- unique `sku` (where present)

Rules:
- Every product owns at least one variant.
- SKU is nullable to allow drafts, but globally unique when present.
- `variant_key` is a deterministic option signature such as `color:1|size:4`;
  multiple `null` keys are allowed.
- Deleting a product cascades to variants, inventory and movements. Once order
  history exists, deletion must become deactivation/archiving.

## 3. Variant foundation implementation

Created:
- `Models/ProductVariant.php`
- `Database/Factories/ProductVariantFactory.php`
- `Database/Migrations/z01_create_product_variants_table.php`
- `Database/Migrations/z02_backfill_default_product_variants.php`
- `Support/UniqueSku.php`
- `Support/DefaultVariantBackfill.php`

Modified:
- `Models/Product.php`: added `variants()`.
- `Data/CreateProductData.php`: added optional `sku`.
- `Actions/CreateProduct.php`:
  - validates SKU uniqueness;
  - creates the product and default variant in one `DB::transaction`;
  - generates a collision-safe `SKU-{productId}` when no SKU is supplied.
- `Database/Factories/ProductFactory.php`:
  - creates a default variant by default;
  - exposes `withoutVariant()`.
- Filament product admin:
  - optional default-variant SKU on create;
  - read-only default-variant SKU on edit;
  - variant count and SKU columns in the table.

Backfill:
- `DefaultVariantBackfill` creates one default variant per product with
  `sku = SKU-{productId}`, `label = Default`, `variant_key = null`,
  `position = 0`, and `is_active` copied from the product.
- It is idempotent and does not duplicate an existing default variant.

## 4. Inventory re-key

Migrations:
- `z03_add_variant_id_to_inventories_table.php`
- `z04_add_variant_id_to_stock_movements_table.php`
- `z05_finalize_inventories_table.php`
- `z06_finalize_stock_movements_table.php`

Up path:
1. Add nullable `variant_id`.
2. Backfill each row from its product's default variant.
3. Abort if any row cannot be mapped.
4. Rebuild `inventories` and `stock_movements` with non-null `variant_id`
   foreign keys, final unique/index definitions and reset PostgreSQL sequences.
5. Preserve primary keys and row IDs.

Final `inventories`:
- `variant_id` foreign key to `product_variants`, `ON DELETE CASCADE`
- unique `(variant_id, location)`

Final `stock_movements`:
- `variant_id` foreign key to `product_variants`, `ON DELETE CASCADE`
- indexes on `(variant_id, location)` and `(variant_id, created_at)`
- append-only shape retained: no `updated_at`

Down path:
- Rebuild the product-keyed shape from the variant join.

Code updated:
- `Models/Inventory.php` and `Models/StockMovement.php`
- `Database/Factories/InventoryFactory.php` and `StockMovementFactory.php`
- `Data/CreateInventoryData.php` (`variantId`)
- `Actions/CreateInventory.php` and `Actions/AdjustStock.php`
- `Services/StockMovementReadService.php`:
  - `variant_id` filtering;
  - `forVariant()`;
  - `summariesForVariant()`;
  - product filtering via `whereHas('variant')`.

## 5. Tests added or updated

Added:
- `src/tests/Feature/Catalog/ProductVariantFoundationTest.php`

Updated:
- `src/tests/Feature/Catalog/ProductCreationTest.php`
- `src/tests/Feature/Inventory/InventoryFoundationTest.php`
- `src/tests/Feature/Inventory/StockMovementTest.php`
- `src/tests/Feature/Inventory/StockMovementReadTest.php`

Coverage:
- variant schema, factory, relationship and cascade;
- default variant creation from the product factory and `withoutVariant()`;
- unique SKU, unique variant key per product and multiple null keys;
- atomic product + variant creation;
- custom SKU, duplicate-SKU rejection, generated SKU and rollback;
- variant-keyed stock, duplicate location rejection and cascade;
- variant-keyed movements, rollback and append-only behaviour;
- variant/product/reason/date filtering and summaries.

## 6. Verification results

Focused suites:

| Suite | Result |
|---|---|
| `tests/Feature/Catalog` | 144 passed (431 assertions) |
| `tests/Feature/Inventory` | 52 passed (174 assertions) |
| `ProductVariantFoundationTest.php` | 10 passed (19 assertions) |
| `ProductCreationTest.php` | 14 passed (46 assertions) |
| `InventoryFoundationTest.php` | 18 passed (51 assertions) |
| `StockMovementTest.php` | 15 passed (62 assertions) |
| `StockMovementReadTest.php` | 19 passed (61 assertions) |

Full suite:

- **227 passed, 3 failed (686 assertions)**.
- The 3 failures are pre-existing and unrelated:
  - `Tests\Feature\Auth\AuthenticationTest`
  - `Tests\Feature\Auth\TwoFactorChallengeTest`
  - `Tests\Feature\PostCreatePageTest`

Static analysis:

- Pint on changed Catalog/Inventory source and tests: passed.
- PHPStan on `app/Modules/Catalog` and `app/Modules/Inventory`: no errors.
- Full-project PHPStan: 21 pre-existing errors outside Catalog/Inventory.

PostgreSQL verification:

- Fresh `migrate:fresh` passed on throwaway database `infino_task003_check`.
- `migrate:rollback --step=7` then `migrate` passed on the same database.
- Throwaway database dropped afterwards.
- Development database backed up to
  `/tmp/opencode/infino-dev-pre-task003.sql` before migrating.
- All pending development migrations applied successfully.

Data preservation (development database):

| Entity | Before | After |
|---|---|---|
| Products | 2 | 2 |
| Default variants | 0 | 2 (`SKU-1`, `SKU-2`) |
| Inventory rows | 1 (id 1, product 2, qty 13) | 1 (id 1, variant 2, qty 13) |
| Stock movements | 1 (id 1, product 2, delta 3, resulting 13) | 1 (id 1, variant 2, delta 3, resulting 13) |

Product IDs, names, slugs, timestamps, inventory IDs, quantities and movement
history were preserved. Both existing rows now reference their product's default
variant.

## 7. Files changed

Created:
- `src/app/Modules/Catalog/Models/ProductVariant.php`
- `src/app/Modules/Catalog/Database/Factories/ProductVariantFactory.php`
- `src/app/Modules/Catalog/Database/Migrations/z01_create_product_variants_table.php`
- `src/app/Modules/Catalog/Database/Migrations/z02_backfill_default_product_variants.php`
- `src/app/Modules/Catalog/Support/UniqueSku.php`
- `src/app/Modules/Catalog/Support/DefaultVariantBackfill.php`
- `src/app/Modules/Inventory/Database/Migrations/z03_add_variant_id_to_inventories_table.php`
- `src/app/Modules/Inventory/Database/Migrations/z04_add_variant_id_to_stock_movements_table.php`
- `src/app/Modules/Inventory/Database/Migrations/z05_finalize_inventories_table.php`
- `src/app/Modules/Inventory/Database/Migrations/z06_finalize_stock_movements_table.php`
- `src/tests/Feature/Catalog/ProductVariantFoundationTest.php`
- `docs/agent/tasks/active/003-catalog-variant-foundation-and-inventory-rekey.md`

Modified:
- `src/app/Modules/Catalog/Models/Product.php`
- `src/app/Modules/Catalog/Data/CreateProductData.php`
- `src/app/Modules/Catalog/Actions/CreateProduct.php`
- `src/app/Modules/Catalog/Database/Factories/ProductFactory.php`
- `src/app/Modules/Catalog/Filament/Resources/Products/Schemas/ProductForm.php`
- `src/app/Modules/Catalog/Filament/Resources/Products/Pages/CreateProduct.php`
- `src/app/Modules/Catalog/Filament/Resources/Products/Tables/ProductsTable.php`
- `src/app/Modules/Inventory/Database/Migrations/create_inventories_table.php`
- `src/app/Modules/Inventory/Models/Inventory.php`
- `src/app/Modules/Inventory/Models/StockMovement.php`
- `src/app/Modules/Inventory/Database/Factories/InventoryFactory.php`
- `src/app/Modules/Inventory/Database/Factories/StockMovementFactory.php`
- `src/app/Modules/Inventory/Data/CreateInventoryData.php`
- `src/app/Modules/Inventory/Actions/CreateInventory.php`
- `src/app/Modules/Inventory/Actions/AdjustStock.php`
- `src/app/Modules/Inventory/Services/StockMovementReadService.php`
- `src/tests/Feature/Catalog/ProductCreationTest.php`
- `src/tests/Feature/Inventory/InventoryFoundationTest.php`
- `src/tests/Feature/Inventory/StockMovementTest.php`
- `src/tests/Feature/Inventory/StockMovementReadTest.php`
- `src/app/Modules/Catalog/README.md`
- `src/app/Modules/Inventory/README.md`
- `docs/agent/DECISIONS.md`
- `docs/agent/modules/CATALOG.md`
- `docs/agent/PRODUCT_BLUEPRINT.md`
- `docs/agent/PROJECT_STATE.md`
- `docs/agent/ROADMAP.md`
- `docs/agent/WORK_LOG.md`
- `docs/agent/HANDOFF.md`

Restored from `HEAD` after NUL-byte corruption (not Task 003 changes):
- `src/app/Foundation/Modules/ModuleManager.php`
- `src/app/Foundation/Modules/ModuleManifest.php`
- `src/app/Foundation/Modules/ModuleRepository.php`
- `src/app/Console/Commands/MakeModuleCommand.php`

## 8. Outstanding issues

- Variant administration (multiple variants, editing, deactivation, positions) is
  not implemented.
- Attributes/options, media and pricing remain future phases.
- `UpdateProduct` still cannot clear a description.
- `UpdateCategory` still cannot clear a parent back to root.
- No product deletion administration exists.
- The 3 pre-existing full-suite failures remain.
- Full-project PHPStan still reports 21 pre-existing errors outside
  Catalog/Inventory.
- No CI job runs PostgreSQL `migrate:fresh`.
- Rebuilt Inventory tables retain cosmetic PostgreSQL sequence/PK names derived
  from the temporary `_new` tables (for example `inventories_new_id_seq`). Final
  foreign keys, unique constraint and indexes use explicit names.

## 9. Next recommended action

Implement variant administration (Phase 2) behind Catalog contracts and
policies, then attributes/options, media and the Pricing module. Add a
PostgreSQL `migrate:fresh` CI check before the schema grows further.
