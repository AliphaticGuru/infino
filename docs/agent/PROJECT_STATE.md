# Infino — Project State

**Last inspected:** 2026-11-10
**Current branch at inspection:** `feature/inventory` (merge of `develop` in progress)
**Latest commit at inspection:** `bc8fc03` — `feat(inventory): implemented inventory stock movement read & reporting`

This document records observed repository state. Reinspect the code before relying on it.

## 1. Verified environment

| Component | Verified version / state |
|---|---|
| PHP CLI in Docker | 8.5.7 (verified 2026-11-09) |
| Laravel | 13.22.0 (verified 2026-11-09) |
| Livewire | 4.3.3 |
| Filament | 5.7.4 |
| Pest | 4.7.5 |
| Database direction | PostgreSQL |
| Cache direction | Redis |
| Web server direction | Nginx |
| Local orchestration | Docker Compose |

Livewire, Filament and Pest versions were reported by earlier project inspection and should be rechecked when upgrading dependencies.

## 2. Repository structure

- Repository root: infrastructure, Docker configuration and shared agent documentation.
- `src/`: Laravel application.
- `src/app/Modules/`: business modules.
- `src/config/modules.php`: module path and scaffold directory definitions.
- `docs/agent/`: shared project memory for AI agents and developers.

The Compose file is `compose.yaml`.

## 3. Existing modules

### Core
**Status:** Present; implementation needs continued verification.

Observed files include:
- `Core/Providers/BaseModuleServiceProvider.php`
- `Core/Providers/CoreServiceProvider.php`
- `Core/Models/BaseModel.php`
- `Core/Data/Data.php`
- `Core/module.json`

Core is declared as a dependency of Identity, Catalog and Inventory.

### Identity
**Status:** Foundation committed; current working-tree changes need verification.

Observed capabilities/files include:
- User model.
- Registration, authentication and logout actions.
- Contracts and data objects.
- User factory and migrations.
- Identity service provider.
- Fortify integration and feature tests.

The current working tree includes changes to the Identity User model, Fortify actions, validation concerns and authentication-related tests.

Do not assume the current tests pass.

### Catalog
**Status:** Product and category foundations complete; retrieval/update, publication lifecycle and product-category assignment verified with focused tests.

Observed files include:
- `CatalogServiceProvider`
- `module.json`
- Product: `CreateProduct`, `FindProduct`, `UpdateProduct`, `TransitionProductStatus`, `AssignProductCategories`, `RemoveProductCategories` and `SyncProductCategories` actions; `CreatesProducts`, `FindsProducts`, `UpdatesProducts`, `TransitionsProductStatus`, `AssignsProductCategories`, `RemovesProductCategories` and `SyncsProductCategories` contracts; `CreateProductData` and `UpdateProductData`; `Product` model; `ProductFactory`; `create_products_table.php` and `z_add_status_to_products_table.php` migrations.
- Product status: `Support/ProductStatus` backed enum (`draft`, `published`, `archived`).
- Category: `Category` model, `CategoryFactory`, `create_categories_table.php`, `CreateCategory` and `UpdateCategory` actions, `CreatesCategories` and `UpdatesCategories` contracts, `CreateCategoryData` and `UpdateCategoryData`.
- Product-category pivot: `create_z_category_product_table.php`.
- Shared slug helper: `Support/UniqueSlug.php`.

The product implementation represents `name`, `slug`, `description`, `is_active` and `status`. Categories add `parent_id`, `name`, `slug`, `description` and `is_active`.

Verified on 2026-11-09:
- Catalog migrations are discovered through `BaseModuleServiceProvider::loadModuleMigrations()` and execute under `RefreshDatabase` and PostgreSQL.
- Product and category factory resolution is explicit through `newFactory()`.
- Product and category contracts resolve through the module provider.
- Product and category creation persist expected fields.
- Validation requires a non-empty string name of at most 255 characters; descriptions are optional and nullable.
- Generated slugs are collision-safe (`name`, `name-2`, `name-3`, ...) with `product`/`category` fallbacks.
- Categories support nullable self-referencing parents with `nullOnDelete` promotion to root.
- Products and categories expose a many-to-many relationship through `category_product`.
- Category updates cover rename slug generation, parent reassignment and rejection of self/descendant parents.
- Focused tests: `ProductCreationTest.php` (9 tests, 28 assertions) and `CategoryFoundationTest.php` (17 tests, 58 assertions).

Verified on 2026-11-10 (retrieval/update and publication lifecycle):
- Products can be retrieved by ID and by slug through `FindsProducts`; unknown values throw `ModelNotFoundException`.
- `UpdateProduct` supports partial updates of name, description and active status; omitted fields keep their stored values.
- Renaming regenerates the slug through `Support\UniqueSlug`, preserving an unchanged name's existing slug and suffixing collisions.
- Products carry a `status` backed enum with `draft`, `published` and `archived`.
- New products default to draft; existing rows were backfilled to draft by the migration.
- Draft products can be published or archived; published products can be unpublished or archived.
- Archived products can only return to draft through `restore`, then be published.
- Invalid transitions raise `ValidationException`; deleted products raise `ModelNotFoundException`.
- Lifecycle transitions preserve name, slug, description and `is_active`.
- `is_active` remains independent of publication status, and `UpdateProduct` does not change publication status.
- Focused tests: `ProductRetrievalAndUpdateTest.php` (15 tests, 40 assertions) and `ProductPublicationLifecycleTest.php` (21 tests, 46 assertions).

Verified on 2026-11-10 (product-category assignment):
- Categories can be assigned individually or in bulk; existing assignments are preserved.
- Duplicate assignments are prevented by the pivot primary key and idempotent attach.
- Selected categories can be removed while unrelated assignments remain.
- Removing unassigned or nonexistent category IDs is safe.
- Synchronisation attaches missing categories and removes omitted ones; an empty array clears all.
- Nonexistent or duplicate category IDs raise `ValidationException` without partial changes.
- Deleted or unpersisted products raise `ModelNotFoundException`.
- Focused tests: `ProductCategoryAssignmentTest.php` (18 tests, 52 assertions).

Remaining gaps:
- Category retrieval/deletion actions are not implemented.
- No publication lifecycle UI, API, Filament resource or Livewire component exists.
- Required first-release fields and variant support remain undecided.
- Names are not unique; duplicate names receive suffixed slugs.

### Inventory
**Status:** Stock foundation and append-only movement history complete and verified with focused tests.

Observed files include:
- `InventoryServiceProvider`
- `module.json` (depends on Core and Catalog)
- `Inventory` and `StockMovement` models
- `InventoryFactory` and `StockMovementFactory`
- `create_inventories_table.php` and `create_stock_movements_table.php` migrations
- `CreateInventory` and `AdjustStock` actions
- `CreatesInventory` and `AdjustsStock` contracts
- `CreateInventoryData` and `AdjustStockData`
- `StockMovementReadService` (read-only retrieval and reporting)

The `inventories` table represents a stock record per product and location:
- `id`
- `product_id` foreign key to Catalog `products` with cascading delete
- `location` (default `default`)
- `quantity` integer (default `0`)
- timestamps
- unique `(product_id, location)`

The `stock_movements` table is append-only:
- `id`
- `product_id` foreign key to Catalog `products` with cascading delete
- `location` (snapshot at adjustment time)
- `quantity_delta` signed integer
- `resulting_quantity` integer
- `reason` string
- nullable `reference` string
- `created_at` only; no `updated_at`
- indexes on `(product_id, location)` and `(product_id, created_at)`

Verified on 2026-11-09:
- The Inventory module is discovered and its provider is registered.
- `CreatesInventory` and `AdjustsStock` resolve to their actions.
- Inventory and stock movement factories resolve and persist records.
- Initial stock creation validates product existence, location, and non-negative quantity.
- A product may have multiple inventory records, one per location; duplicate product/location pairs are rejected.
- Stock adjustments increase and decrease quantity atomically inside a transaction with `lockForUpdate`.
- Adjustments that would produce negative stock, and zero adjustments, are rejected.
- Adjustments are isolated per location.
- Every successful adjustment writes a movement with the product, location, signed delta, resulting quantity, reason, optional reference and creation timestamp in the same transaction.
- Failed, rejected and zero adjustments write no movement.
- Movement persistence failure rolls back the stock update; outer transaction rollback reverts both.
- Movement records have no update or delete operations and no updated timestamp.
- Deleting a product cascades to its inventory records and stock movements.
- Movement history is retrievable through `StockMovementReadService` with pagination (default 20, newest first), product filtering, reason filtering and inclusive date-range filtering.
- Per-product movement history reuses the same paginated query.
- Daily summaries derive increase and decrease totals from recorded movements and accept optional product and date-range filters.
- Read operations never modify inventory or movement records.
- Focused tests: `InventoryFoundationTest.php` (16 tests, 46 assertions), `StockMovementTest.php` (14 tests, 59 assertions) and `StockMovementReadTest.php` (14 tests, 42 assertions).

Remaining gaps:
- Stock reservations, release, low-stock thresholds and location management actions are not implemented.
- Inventory assumes Catalog products without variants; variant-level inventory remains open.
- Reporting is daily-grouped only; no per-location or per-reason breakdown exists.
- No HTTP endpoints, Filament resources or UI exist for movement history or reporting.

### TestModule
**Status:** Present; purpose and intended lifecycle need verification.

## 4. Module loading

Observed in `src/app/Providers/ModuleServiceProvider.php`:
- `ModuleRepository` is registered as a singleton.
- `ModuleManager` is registered as a singleton.
- `$modules->load()` is called from the provider's `boot()` method.

Verified indirectly on 2026-11-09: the Catalog contract binding and migration loading were active during the focused Catalog tests.

Inspect the module infrastructure before changing loading behavior.

## 5. Git state at inspection

The repository was inspected on 2026-11-10 on `feature/inventory`.

- `develop` was merged into `feature/inventory` as `84dcb09` to unify the Catalog
  and Inventory histories.
- The product-category assignment implementation is uncommitted on top of that
  merge commit.
- The working tree contains the new assignment actions, contracts, tests and
  documentation updates.

**This is a historical snapshot, not a statement of the current Git state.** Always run `git status --short` before making changes.

## 6. Resolved environment warning

Docker Compose previously warned that `DB_USERNAME` and `DB_DATABASE` were unset.

Cause: the `db` healthcheck used `${DB_USERNAME}` and `${DB_DATABASE}`. Compose interpolates those from the host environment or a root `.env` file, not from a service's `env_file`. The repository has no root `.env`, so both values resolved to blank strings.

Fix: the healthcheck now reads the container's own variables with `pg_isready -U "$$POSTGRES_USER" -d "$$POSTGRES_DB"`. `docker compose config --quiet` produces no warnings and the `infino-db` container reaches the `healthy` state.

## 7. Test status

Baseline and post-change full-suite results were captured on 2026-11-09 and 2026-11-10 using:

```bash
docker compose run --rm --no-deps cli php artisan test --compact
```

- Baseline at the start of Category foundation: 40 passed, 3 failed (109 assertions).
- After completing the Category foundation: 57 passed, 3 failed (167 assertions).
- After completing the Inventory foundation: 87 passed, 3 failed (272 assertions).
- After stock movement history: 87 passed, 3 failed (272 assertions).
- After stock movement read/reporting: 101 passed, 3 failed (314 assertions).
- After Catalog product retrieval/update: 116 passed, 3 failed (354 assertions).
- After merging `feature/catalog` into `develop` (Catalog retrieval/update + publication lifecycle, Inventory foundation/history/read): **137 passed, 3 failed (400 assertions)**.
- After product-category assignment: **155 passed, 3 failed (452 assertions)**.
- Catalog focused suite: **80 passed (224 assertions)**.
- Inventory focused suite: 44 passed (147 assertions).
- `ProductCategoryAssignmentTest.php`: 18 passed (52 assertions).
- `ProductPublicationLifecycleTest.php`: 21 passed (46 assertions).
- `StockMovementReadTest.php`: 14 passed (42 assertions).
- PHPStan level 7 on `app/Modules/Catalog` and `app/Modules/Inventory`: no errors.
- Full-project PHPStan: 23 pre-existing errors outside Catalog and Inventory.

Remaining failures:
1. `Tests\Feature\Auth\AuthenticationTest` — two-factor users are redirected to `/dashboard` instead of `/two-factor-challenge`.
2. `Tests\Feature\Auth\TwoFactorChallengeTest` — same two-factor challenge redirect failure.
3. `Tests\Feature\PostCreatePageTest` — page returns 500 because `layouts/app/sidebar.blade.php` reads `name` from a null authenticated user.

Likely causes:
- The custom `/login` route in the Identity module authenticates directly and bypasses Fortify's two-factor challenge handling.
- The post-create page is rendered for a guest while using an authenticated application layout.

Never report tests as passing unless the relevant command was executed and its result observed.

## 8. Immediate priorities

1. Restore Fortify two-factor challenge handling in the Identity login flow.
2. Decide whether the post-create page requires authentication or a guest-capable layout.
3. Add category retrieval and deletion actions.
4. Expose the product lifecycle through an authorised delivery layer when a UI milestone begins.
5. Define required first-release product fields and variant direction before expanding the schema.
6. Expose Inventory movement history and reporting through authorised HTTP or Filament layers.
7. Extend Inventory with reservations and low-stock thresholds once their business rules are approved.
