# Infino — Project State

**Last inspected:** 2026-11-11
**Current branch at inspection:** `feature/catalog`
**Latest commit at inspection:** `636b0fc` — `feat(catalog): implemented catalog filament administration`

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
**Status:** Foundation committed; admin flag and Filament panel access added in the working tree.

Observed capabilities/files include:
- User model.
- Registration, authentication and logout actions.
- Contracts and data objects.
- User factory and migrations.
- Identity service provider.
- Fortify integration and feature tests.
- `is_admin` boolean migration, `FilamentUser` implementation and `canAccessPanel()` on `User` (2026-11-11).

The current working tree includes changes to the Identity User model, factory, seeder and a new admin-flag migration. Do not assume the current tests pass.

### Catalog
**Status:** Product, category and variant foundations complete; retrieval/update, publication lifecycle, product-category assignment, category retrieval/deletion, the first Filament admin layer and the variant-first Inventory re-key verified with focused tests.

Observed files include:
- `CatalogServiceProvider`
- `module.json`
- Product: `CreateProduct`, `FindProduct`, `UpdateProduct`, `TransitionProductStatus`, `AssignProductCategories`, `RemoveProductCategories` and `SyncProductCategories` actions; `CreatesProducts`, `FindsProducts`, `UpdatesProducts`, `TransitionsProductStatus`, `AssignsProductCategories`, `RemovesProductCategories` and `SyncsProductCategories` contracts; `CreateProductData` and `UpdateProductData`; `Product` model; `ProductFactory`; `create_products_table.php` and `z_add_status_to_products_table.php` migrations.
- Product variants: `ProductVariant` model, `ProductVariantFactory`, `z01_create_product_variants_table.php`, `z02_backfill_default_product_variants.php`, `Support/UniqueSku.php` and `Support/DefaultVariantBackfill.php`.
- Product status: `Support/ProductStatus` backed enum (`draft`, `published`, `archived`).
- Category: `Category` model, `CategoryFactory`, `create_categories_table.php`, `CreateCategory`, `UpdateCategory`, `FindCategory` and `DeleteCategory` actions, `CreatesCategories`, `UpdatesCategories`, `FindsCategories` and `DeletesCategories` contracts, `CreateCategoryData` and `UpdateCategoryData`.
- Product-category pivot: `create_z_category_product_table.php`.
- Shared slug helper: `Support/UniqueSlug.php`.
- Filament 5 administration: `Filament/Resources/Products/*` and `Filament/Resources/Categories/*` (resources, schemas, tables and pages).
- Authorization: `Policies/ProductPolicy.php` and `Policies/CategoryPolicy.php`, registered with `Gate::policy()` in the module provider.
- Panel resource discovery: `src/app/Providers/Filament/AdminPanelProvider.php`.

The product implementation represents `name`, `slug`, `description`, `is_active` and `status`. Categories add `parent_id`, `name`, `slug`, `description` and `is_active`. Variants add `product_id`, nullable unique `sku`, nullable `label`, nullable `variant_key`, `is_active`, `position` and timestamps.

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

Verified on 2026-11-10 (category retrieval and deletion):
- Categories can be retrieved by ID; unknown IDs raise `ModelNotFoundException`.
- Categories are paginated ordered by `id`, defaulting to 20 per page with optional explicit page selection.
- A category's products are paginated ordered by `products.id`; a deleted category raises `ModelNotFoundException`.
- An unassigned category can be deleted.
- A category with assigned products raises `ValidationException` with a meaningful message and is not deleted.
- Rejected deletion preserves the product-category pivot rows and the product.
- Deleting a parent category promotes children to root via the existing `nullOnDelete` behaviour.
- Focused tests: `CategoryRetrievalAndDeletionTest.php` (13 tests, 35 assertions).

Verified on 2026-11-11 (Filament administration layer):
- The admin panel is reachable at `/admin`; guests are redirected to `/login`.
- Identity users carry an `is_admin` boolean; `User` implements `FilamentUser` and `canAccessPanel()` returns the flag.
- Non-admin authenticated users receive 403 at the panel and cannot mount Catalog resource pages directly.
- `ProductPolicy` and `CategoryPolicy` require `is_admin`; `ProductPolicy` also covers `publish` and `manageCategories`.
- Product listing supports search, status/category filters, sorting and pagination.
- Product creation and editing call `CreatesProducts` and `UpdatesProducts`.
- Product publish, unpublish, archive and restore call `TransitionsProductStatus` and are explicitly authorised.
- Product category assignment, removal and replacement call `SyncsProductCategories` and are explicitly authorised.
- Category listing supports search, active/parent filters, sorting and pagination, and shows parent, product count and child count.
- Category creation and editing call `CreatesCategories` and `UpdatesCategories`.
- Category deletion calls `DeletesCategories`; categories with products are rejected without implicit detachment.
- No bulk delete actions are registered, so bulk operations cannot bypass authorization.
- Focused tests: `ProductResourceTest.php` (13 tests, 51 assertions), `CategoryResourceTest.php` (11 tests, 42 assertions) and `CatalogAdminAuthorizationTest.php` (12 tests, 42 assertions).
- Catalog Filament suite: 36 tests (135 assertions).

Verified on 2026-11-11 (variant foundation and Inventory re-key):
- Every product owns at least one `ProductVariant`; the variant is the canonical purchasable unit.
- `product_variants` stores `product_id`, nullable globally unique `sku`, nullable `label`, nullable `variant_key`, `is_active`, `position` and timestamps, with unique `(product_id, variant_key)` and supporting indexes.
- `CreateProduct` creates the product and a default variant atomically; optional SKU input is validated for uniqueness and a collision-safe `SKU-{productId}` is generated when omitted.
- Existing products are backfilled idempotently with a default variant; duplicate defaults are prevented.
- `ProductFactory` creates a default variant by default and exposes `withoutVariant()`.
- Inventory and stock movements are keyed to `variant_id`; product-level reporting joins through `product_variants`.
- Existing inventory and movement rows were mapped to their product's default variant with IDs, quantities, history and timestamps preserved.
- Focused tests: `ProductVariantFoundationTest.php` (10 tests, 19 assertions), `ProductCreationTest.php` (14 tests, 46 assertions), `InventoryFoundationTest.php` (18 tests, 51 assertions), `StockMovementTest.php` (15 tests, 62 assertions) and `StockMovementReadTest.php` (19 tests, 61 assertions).
- Catalog focused suite: 144 passed (431 assertions). Inventory focused suite: 52 passed (174 assertions).
- Fresh PostgreSQL `migrate:fresh` and rollback/re-apply of the new migrations verified on a throwaway database.

Remaining gaps:
- No product deletion action is exposed in the admin layer.
- `UpdateProduct` cannot clear a description and `UpdateCategory` cannot clear a parent to root through the forms; both are existing contract limitations.
- Admin access is a single boolean; roles and granular permissions are future work.
- Required first-release fields and variant support remain undecided.
- Names are not unique; duplicate names receive suffixed slugs.

### Inventory
**Status:** Variant-keyed stock foundation and append-only movement history complete and verified with focused tests.

Observed files include:
- `InventoryServiceProvider`
- `module.json` (depends on Core and Catalog)
- `Inventory` and `StockMovement` models
- `InventoryFactory` and `StockMovementFactory`
- `create_inventories_table.php` and `create_stock_movements_table.php` migrations
- `z03_add_variant_id_to_inventories_table.php`, `z04_add_variant_id_to_stock_movements_table.php`, `z05_finalize_inventories_table.php` and `z06_finalize_stock_movements_table.php` migrations
- `CreateInventory` and `AdjustStock` actions
- `CreatesInventory` and `AdjustsStock` contracts
- `CreateInventoryData` and `AdjustStockData`
- `StockMovementReadService` (read-only retrieval and reporting)

The `inventories` table represents a stock record per product variant and location:
- `id`
- `variant_id` foreign key to Catalog `product_variants` with cascading delete
- `location` (default `default`)
- `quantity` integer (default `0`)
- timestamps
- unique `(variant_id, location)`

The `stock_movements` table is append-only:
- `id`
- `variant_id` foreign key to Catalog `product_variants` with cascading delete
- `location` (snapshot at adjustment time)
- `quantity_delta` signed integer
- `resulting_quantity` integer
- `reason` string
- nullable `reference` string
- `created_at` only; no `updated_at`
- indexes on `(variant_id, location)` and `(variant_id, created_at)`

Verified on 2026-11-09:
- The Inventory module is discovered and its provider is registered.
- `CreatesInventory` and `AdjustsStock` resolve to their actions.
- Inventory and stock movement factories resolve and persist records.
- Initial stock creation validates variant existence, location, and non-negative quantity.
- A variant may have multiple inventory records, one per location; duplicate variant/location pairs are rejected.
- Stock adjustments increase and decrease quantity atomically inside a transaction with `lockForUpdate`.
- Adjustments that would produce negative stock, and zero adjustments, are rejected.
- Adjustments are isolated per location and per variant of the same product.
- Every successful adjustment writes a movement with the variant, location, signed delta, resulting quantity, reason, optional reference and creation timestamp in the same transaction.
- Failed, rejected and zero adjustments write no movement.
- Movement persistence failure rolls back the stock update; outer transaction rollback reverts both.
- Movement records have no update or delete operations and no updated timestamp.
- Deleting a product or variant cascades to its inventory records and stock movements.
- Movement history is retrievable through `StockMovementReadService` with pagination (default 20, newest first), variant/product filtering, reason filtering and inclusive date-range filtering.
- Per-product history joins through variants; `forVariant()` filters a single variant.
- Daily summaries derive increase and decrease totals from recorded movements and accept optional variant/product and date-range filters.
- Read operations never modify inventory or movement records.
- Focused tests: `InventoryFoundationTest.php` (18 tests, 51 assertions), `StockMovementTest.php` (15 tests, 62 assertions) and `StockMovementReadTest.php` (19 tests, 61 assertions).

Verified on 2026-11-11 (variant re-key):
- Existing `inventories` and `stock_movements` rows were migrated to their product's default variant with IDs, quantities, history and timestamps preserved.
- The development database preserved 2 products, 2 default variants, 1 inventory record and 1 movement through the migration.
- Fresh PostgreSQL `migrate:fresh` succeeded, and the new migrations rolled back and re-applied cleanly on a throwaway database.

Remaining gaps:
- Stock reservations, release, low-stock thresholds and location management actions are not implemented.
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

The repository was inspected on 2026-11-11 on `feature/catalog`.

- `feature/catalog` HEAD is `636b0fc` (`feat(catalog): implemented catalog filament administration`).
- The Filament administration layer, Catalog policies and Identity admin flag are committed at that HEAD.
- The working tree contains the uncommitted Task 003 variant foundation and Inventory re-key: new migrations, models, factories, support classes, tests and documentation updates.
- The `2026_11_10_000000_add_is_admin_to_users_table` migration and the new Catalog/Inventory migrations have been applied to the development PostgreSQL database.
- Nothing from Task 003 has been committed.

**This is a historical snapshot, not a statement of the current Git state.** Always run `git status --short` before making changes.

### Working-tree integrity note (2026-11-11)

Four tracked files (`app/Foundation/Modules/ModuleManager.php`,
`ModuleManifest.php`, `ModuleRepository.php` and
`app/Console/Commands/MakeModuleCommand.php`) were observed to be NUL-byte
corrupted on disk while Git's stat cache still reported them unchanged. They
were restored byte-for-byte from `HEAD`; no user work was discarded. The
application booted and the full suite ran normally afterwards. Re-verify tracked
files with `git hash-object` against the index if unexpected class-not-found
errors appear.

## 6. Resolved environment warning

Docker Compose previously warned that `DB_USERNAME` and `DB_DATABASE` were unset.

Cause: the `db` healthcheck used `${DB_USERNAME}` and `${DB_DATABASE}`. Compose interpolates those from the host environment or a root `.env` file, not from a service's `env_file`. The repository has no root `.env`, so both values resolved to blank strings.

Fix: the healthcheck now reads the container's own variables with `pg_isready -U "$$POSTGRES_USER" -d "$$POSTGRES_DB"`. `docker compose config --quiet` produces no warnings and the `infino-db` container reaches the `healthy` state.

## 7. Test status

Baseline and post-change full-suite results were captured on 2026-11-09, 2026-11-10 and 2026-11-11 using:

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
- After category retrieval and deletion: **168 passed, 3 failed (487 assertions)**.
- After the Catalog Filament administration layer: **204 passed, 3 failed (622 assertions)**.
- After Task 003 (variant foundation + Inventory variant re-key): **227 passed, 3 failed (686 assertions)**.
- Catalog focused suite: **129 passed (394 assertions)** before Task 003; **144 passed (431 assertions)** after Task 003.
- Catalog Filament suite: **36 passed (135 assertions)**.
- Inventory focused suite: 44 passed (147 assertions) before Task 003; **52 passed (174 assertions)** after Task 003.
- `ProductVariantFoundationTest.php`: 10 passed (19 assertions).
- `ProductCreationTest.php`: 14 passed (46 assertions).
- `InventoryFoundationTest.php`: 18 passed (51 assertions).
- `StockMovementTest.php`: 15 passed (62 assertions).
- `StockMovementReadTest.php`: 14 passed (42 assertions) before Task 003; 19 passed (61 assertions) after Task 003.
- `CategoryRetrievalAndDeletionTest.php`: 13 passed (35 assertions).
- `ProductCategoryAssignmentTest.php`: 18 passed (52 assertions).
- `ProductPublicationLifecycleTest.php`: 21 passed (46 assertions).
- `ProductResourceTest.php`: 13 passed (51 assertions).
- `CategoryResourceTest.php`: 11 passed (42 assertions).
- `CatalogAdminAuthorizationTest.php`: 12 passed (42 assertions).
- PHPStan level 7 on `app/Modules/Catalog` and `app/Modules/Inventory`: no errors.
- PHPStan level 7 on `app/Modules/Identity`: 3 pre-existing errors (Passkey contract and `LogoutUser`/`LogOutUser` case).
- Full-project PHPStan: 21 pre-existing errors outside Catalog and Inventory (Livewire, Core, Foundation, Identity and config).

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
3. Build variant administration (create/edit/deactivate variants) on top of the variant foundation.
4. Add a PostgreSQL `migrate:fresh` check to CI so migration-ordering regressions cannot hide behind SQLite.
5. Implement attributes/options, then media, then the Pricing module, in that order.
6. Extend the Filament admin layer with Inventory movement history and reporting when its scope is approved.
7. Add granular roles/permissions if the single `is_admin` flag becomes insufficient.
8. Extend Inventory with reservations and low-stock thresholds once their business rules are approved.
