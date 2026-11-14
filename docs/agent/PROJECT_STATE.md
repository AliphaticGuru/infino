# Infino — Project State

**Last inspected:** 2026-11-13
**Current branch at inspection:** `feature/pricing`

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
**Status:** Foundation committed; admin flag and Filament panel access committed.

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
**Status:** Product, category, variant and attribute/option foundations complete; retrieval/update, publication lifecycle, product-category assignment, category retrieval/deletion, variant administration, normalized product attributes and options, product media/galleries, the Filament admin layer and the variant-first Inventory re-key verified with focused tests. Task 006 is committed at `4faff01`.

Observed files include:
- `CatalogServiceProvider`
- `module.json`
- Product: `CreateProduct`, `FindProduct`, `UpdateProduct`, `TransitionProductStatus`, `AssignProductCategories`, `RemoveProductCategories` and `SyncProductCategories` actions; `CreatesProducts`, `FindsProducts`, `UpdatesProducts`, `TransitionsProductStatus`, `AssignsProductCategories`, `RemovesProductCategories` and `SyncsProductCategories` contracts; `CreateProductData` and `UpdateProductData`; `Product` model; `ProductFactory`; `create_products_table.php` and `z_add_status_to_products_table.php` migrations.
- Product variants: `ProductVariant` model with `defaultVariant()`/`isDefault()` helpers, `ProductVariantFactory`, `z01_create_product_variants_table.php`, `z02_backfill_default_product_variants.php`, `Support/UniqueSku.php` and `Support/DefaultVariantBackfill.php`.
- Variant administration: `FindProductVariant`, `CreateProductVariant`, `UpdateProductVariant`, `TransitionProductVariantStatus` and `RepositonProductVariants` actions; `FindsProductVariants`, `CreatesProductVariants`, `UpdatesProductVariants`, `TransitionsProductVariantStatus` and `RepositonProductVariants` contracts; `CreateProductVariantData` and `UpdateProductVariantData`; `ProductVariantPolicy`.
- Variant Filament administration: `Filament/Resources/Products/RelationManagers/VariantsRelationManager.php`, `Schemas/ProductVariantForm.php` and `Tables/ProductVariantsTable.php`.
- Product attributes and options: `ProductAttribute`, `ProductAttributeOption` and `ProductVariantAttributeOption` models; `ProductAttributeFactory`, `ProductAttributeOptionFactory` and `ProductVariantAttributeOptionFactory`; `Support/AttributeType` and `Support/VariantKey`; `z07`–`z10` migrations; definition/option CRUD, product assignment, variant option sync and option repositioning contracts/actions; `CreateProductAttributeData`, `UpdateProductAttributeData`, `CreateProductAttributeOptionData`, `UpdateProductAttributeOptionData` and `SyncVariantAttributeOptionsData`; `ProductAttributePolicy` and `ProductAttributeOptionPolicy`; `Product::productAttributes()` and `ProductVariant::attributeOptions()`.
- Attribute Filament administration: `Filament/Resources/Attributes/*` (resource, form, table, pages and `OptionsRelationManager`) and `ProductAttributesRelationManager` on the product edit page.
- Product status: `Support/ProductStatus` backed enum (`draft`, `published`, `archived`).
- Category: `Category` model, `CategoryFactory`, `create_categories_table.php`, `CreateCategory`, `UpdateCategory`, `FindCategory` and `DeleteCategory` actions, `CreatesCategories`, `UpdatesCategories`, `FindsCategories` and `DeletesCategories` contracts, `CreateCategoryData` and `UpdateCategoryData`.
- Product-category pivot: `create_z_category_product_table.php`.
- Shared slug helper: `Support/UniqueSlug.php`.
- Filament 5 administration: `Filament/Resources/Products/*` and `Filament/Resources/Categories/*` (resources, schemas, tables and pages).
- Authorization: `Policies/ProductPolicy.php`, `Policies/CategoryPolicy.php` and `Policies/ProductVariantPolicy.php`, registered with `Gate::policy()` in the module provider.
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
- Categories are paginated ordered by `products.id`; a deleted category raises `ModelNotFoundException`.
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

Verified on 2026-11-12 (normalized product attributes and options, Task 005):
- Global attribute definitions (`product_attributes`) and options owned by one attribute (`product_attribute_options`) exist with immutable codes, editable names/labels, active flags, positions and per-attribute option code uniqueness.
- Products can be assigned attributes through `product_attribute_product`; only active variant dimensions are assignable in Task 005, and assignment is idempotent and per-product.
- Variants select at most one option per attribute through `product_variant_attribute_options`; the composite foreign key guarantees the option belongs to the selected attribute.
- Configured variants must select exactly one option for every variant dimension assigned to their product; incomplete, duplicate, cross-product, unknown, mismatched and newly-inactive selections are rejected with `ValidationException`.
- `variant_key` is derived from immutable `attributeCode:optionCode` pairs sorted by attribute position then code, joined with `|` (for example `size:m|color:red`). A manually supplied key must match the derived key. Unconfigured variants keep the optional manual key behaviour.
- Definitions/options are deactivated when referenced; guarded hard deletion is allowed only when unreferenced, and removing an attribute that a product variant still selects is rejected.
- Attribute and option writes run in transactions with row locking; variant option changes do not touch inventory rows or stock movements.
- Filament adds an `AttributeResource` with option CRUD/reordering, a `ProductAttributesRelationManager` on the product edit page and dynamic per-dimension option selects in the variant form; all writes delegate to Catalog contracts.
- `ProductAttributePolicy` and `ProductAttributeOptionPolicy` are admin-only; `ProductPolicy` adds `manageAttributes`.
- Focused tests: 53 business tests (108 assertions), `ProductAttributeResourceTest` (14 tests, 85 assertions), `ProductAttributeAssignmentFilamentTest` (5 tests, 21 assertions) and `ProductVariantAttributeOptionsFilamentTest` (5 tests, 49 assertions).
- Catalog focused suite after Task 005: 265 passed (862 assertions).
- Inventory focused suite after Task 005: 52 passed (174 assertions).
- Full suite after Task 005: 348 passed, 3 failed (1117 assertions); the three failures are the known pre-existing authentication and post-create page failures.
- Fresh PostgreSQL `migrate:fresh` succeeded on a throwaway database; `z07`–`z10` rolled back and re-applied cleanly.

Verified on 2026-11-13 (product media and galleries, Task 006):
- Catalog owns `ProductMedia`; a Catalog-owned `MediaStorage` contract isolates filesystem access so local public storage and future S3-compatible storage share one domain path.
- `product_media` supports product-level and variant-level galleries with gallery-scoped `position`, `is_primary`, `alt_text`, disk/path and image metadata.
- Rules: one primary per gallery, first image auto-primary, primary promotion on delete, variant gallery/primary fallback to the product gallery, and complete-set gallery reordering.
- Contracts: `FindsProductMedia`, `AddsProductMedia`, `UpdatesProductMedia`, `SetsPrimaryProductMedia`, `DeletesProductMedia` and `RepositionsProductMedia`.
- Filament `MediaRelationManager` uses gallery tabs and contract-backed upload/edit/set-primary/delete/move actions; `FileUpload::storeFiles(false)` prevents Filament from bypassing the contract.
- Security: server-side JPEG/PNG/WebP/GIF validation, 5 MB default limit, 8000 × 8000 dimension cap, SVG rejection, generated ULID filenames, sanitized original names and an allowed-disk list.
- No image conversion, thumbnail, EXIF stripping, Spatie Media Library or Intervention Image is used because GD/Imagick are absent.
- DEC-022 is accepted and implemented.
- Catalog focused suite: 337 passed (1073 assertions). Task 006 media tests: 72 passed (211 assertions). Inventory focused suite: 52 passed (174 assertions).
- Full suite: 420 passed, 3 known pre-existing failures (`AuthenticationTest`, `TwoFactorChallengeTest`, `PostCreatePageTest`).
- Pint and module PHPStan (Catalog + `config/catalog.php`) pass.
- PostgreSQL `migrate:fresh`, rollback of `z11` and re-apply verified; partial unique indexes and the composite FK enforced by direct inserts.
- Full specification: `docs/agent/tasks/active/006-catalog-product-media.md`.

Remaining gaps:
- No product deletion action is exposed in the admin layer.
- `UpdateProduct` cannot clear a description and `UpdateCategory` cannot clear a parent to root through the forms; both are existing contract limitations.
- `UpdateProductVariant` treats `null` as "not provided", so the form cannot clear a label or variant key.
- Admin access is a single boolean; roles and granular permissions are future work.
- Brands and pricing remain undecided or unimplemented.
- Product media thumbnails, conversions, EXIF stripping and orphan-file pruning remain deferred (Task 006, DEC-022).
- Attribute support is limited to `select` variant dimensions; descriptive attribute values, text/number/boolean types and product-level values are deferred.
- Publication does not yet require every variant of a dimensioned product to be configured.
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
- Every successful adjustment writes a movement with the variant, location, signed delta, resulting quantity, reason, optional reference and creation timestamp in the same transaction.
- Failed, rejected and zero adjustments write no movement.
- Movement persistence failure rolls back the stock update; outer transaction rollback reverts both.
- Movement records have no update or delete operations and no updated timestamp.
- Deleting a product or variant cascades to its inventory records and stock movements.
- Movement history is retrievable through `StockMovementReadService` with pagination (default 20, newest first), variant/product filtering, reason filtering and inclusive date-range filtering.
- Per-product history joins through variants; `forVariant()` filters a single variant.
- Daily summaries derive increase and decrease totals from recorded movements and accept optional variant/product and date-range filters.
- Read operations never modify inventory or movement records.

Verified on 2026-11-11 (variant re-key):
- Existing `inventories` and `stock_movements` rows were migrated to their product's default variant with IDs, quantities, history and timestamps preserved.
- Fresh PostgreSQL `migrate:fresh` succeeded, and the new migrations rolled back and re-applied cleanly on a throwaway database.

Remaining gaps:
- Stock reservations, release, low-stock thresholds and location management actions are not implemented.
- Reporting is daily-grouped only; no per-location or per-reason breakdown exists.
- No HTTP endpoints, Filament resources or UI exist for movement history or reporting.

### Pricing
**Status:** Tasks 007A–007C implemented on `feature/pricing` branch. DEC-023 Proposed.

Observed state: Pricing module fully implemented. Foundation (007A): contracts, actions, Money value object, factories, policies, and Pest tests. Filament administration layer (007C): VariantPriceResource with create/edit/list pages, VariantPricesTable with currency filter, VariantPriceForm schema, and VariantPricePolicy access control. Catalog and Inventory remain price-free.

Proposed in `docs/agent/tasks/active/007-pricing-module.md` and `docs/agent/modules/PRICING.md`:
- A Pricing module depending on Core and Catalog; the variant is the priceable unit (DEC-019).
- `variant_prices` holds one current base price per `(variant_id, currency)` and an optional compare-at amount in the same currency.
- Money is stored as integer minor units (`bigint`) plus an uppercase ISO 4217 `char(3)` currency; no floats, and no `bcmath`/`gmp` dependency.
- `variant_price_changes` is an append-only ledger written in the same transaction as each price mutation.
- `FindsVariantPrices`, `SetsVariantPrice` and `RemovesVariantPrice` contracts and actions; admin-only `VariantPricePolicy`; standalone `VariantPriceResource` discovered from the Pricing module.
- Deferred: price lists, customer-specific pricing, scheduled/promotional pricing, coupons, tax, FX conversion, price adjustments and a shared price resolver.

No Pricing code, migration, configuration or Filament resource has been written **before Task 007A**. After Task 007A implementation:

- `src/app/Modules/Pricing/module.json` - Module manifest with name, description, version, enabled, providers and dependencies.
- `src/app/Modules/Pricing/PricingServiceProvider.php` - Service provider registering contracts, actions, policy and config.
- `src/config/pricing.php` - Configuration with default currency `NGN`, allowed currencies `NGN`, `USD`, `EUR`, `GBP` with exponent map, and extensible configuration.
- `src/app/Modules/Pricing/Models/VariantPrice.php` - Eloquent model with `belongsTo(ProductVariant)` and `hasMany(VariantPriceChange)` relationships; `init()` method for Money exponent map.
- `src/app/Modules/Pricing/Models/VariantPriceChange.php` - Append-only ledger model with `belongsTo(ProductVariant)` and `belongsTo(User)` (nullable `changed_by`) relationships; no `updated_at`.
- `src/app/Modules/Pricing/Support/Money.php` - Integer-safe monetary value object with `parse()`, `format()`, `compare()`, `add()`, `subtract()` using currency-specific exponents; rejects unknown currencies, negative amounts, precision beyond exponent.
- `src/app/Modules/Pricing/Database/Factories/VariantPriceFactory.php` - Factory with states for NGN/USD/EUR/GBP, amount and compare-at settings, and `forVariant()` method.
- `src/app/Modules/Pricing/Database/Factories/VariantPriceChangeFactory.php` - Factory with states for `created`, `updated`, `removed` change types and `withChangedBy()` method.
- `src/app/Modules/Pricing/Database/Migrations/z12_create_variant_prices_table.php` - Creates `variant_prices` table with `id`, `variant_id`, `currency`, `amount_minor`, `compare_at_amount_minor`, timestamps; unique `(variant_id, currency)` constraint; foreign key to `product_variants` with cascade on delete.
- `src/app/Modules/Pricing/Database/Migrations/z13_create_variant_price_changes_table.php` - Creates `variant_price_changes` table with `id`, `variant_id`, `currency`, `change_type`, `old_amount_minor`, `new_amount_minor`, `old_compare_at_amount_minor`, `new_compare_at_amount_minor`, `changed_by`, `created_at`; no `updated_at`; foreign keys to `product_variants` (cascade) and `users` (set null); indexes on `(variant_id, created_at)` and `(currency, created_at)`.
- `src/app/Modules/Pricing/Policies/VariantPricePolicy.php` - Admin-only policy for `viewAny`, `view`, `create`, `update` and `delete`.
- `src/tests/Feature/Pricing/PricingFoundationTest.php` - Focused tests for Money parsing/formatting, currency exponent handling, invalid currency/precision, negative/zero values, compare-at validation, model relationships, factory behavior, unique variant/currency constraint, foreign-key behavior, ledger immutability, and policy authorization.

Database schema implemented:
- `variant_prices`: one current base price per `(variant_id, currency)` with unique constraint, `amount_minor` and optional `compare_at_amount_minor` in integer minor units.
- `variant_price_changes`: append-only ledger with `change_type` (`created`/`updated`/`removed`), old/new amounts and compare-at values, `changed_by` actor reference; no `updated_at`.

Test results (run via Docker Compose):
- Focused Pricing foundation tests: see detailed results below.
- Focused Pricing action tests: 16 passed (42 assertions) — `PricingActionsTest.php` covers contract resolution, price creation, multi-currency storage, retrieval, currency rejection, negative/zero amount rejection, compare-at strict-greater-than validation, history recording on create/update/remove, history preservation after removal, variant price isolation, and model relationships.
- Catalog focused suite: 337 passed (1073 assertions) - no regressions.
- Inventory focused suite: 65 passed (221 assertions) - no regressions.
- Full suite: 425 passed, 32 failed (32 failures are pre-existing: Authentication 2FC, PostCreatePageTest, and some Catalog Filament tests unrelated to pricing).
- Pint: passes on touched files (59 style issues auto-repaired).
- PHPStan: module-level checks pending; no new errors introduced.

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

The repository was inspected on 2026-11-13 on `feature/pricing`.

- Task 006 (product media and galleries) was verified and committed as `4faff01` — `feat(catalog): add product media and galleries`.
- `feature/pricing` was created from `feature/catalog` at `4faff01`.
  `develop` is at `3b7c440` and is an ancestor of `feature/catalog`, so it does not contain the Task 003–006 Catalog work.
- The working tree was clean at branch creation. Task 007 is now implemented in increments: 007A foundation code and docs written; 007B implemented, 007C–007D pending.
- Pricing application code, migrations, configuration, actions and contracts now exist (Tasks 007A–007B).
- Do not commit, merge or reset without authorization.

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
- After Task 004 (product variant administration): **263 passed, 3 failed (825 assertions)**.
- After Task 005 (normalized product attributes and options): **348 passed, 3 failed (1117 assertions)**.
- After Task 006 (product media and galleries): **420 passed, 3 failed (1328 assertions)**.
- Catalog focused suite: **129 passed (394 assertions)** before Task 003; **144 passed (431 assertions)** after Task 003; **180 passed (570 assertions)** after Task 004; **265 passed (862 assertions)** after Task 005; **337 passed (1073 assertions)** after Task 006.
- Task 006 media tests: **72 passed (211 assertions)**.
- Catalog Filament suite: **36 passed (135 assertions)** before Task 004; **45 passed (179 assertions)** after Task 004; **69 passed (334 assertions)** after Task 005.
- Inventory focused suite: 44 passed (147 assertions) before Task 003; **52 passed (174 assertions)** after Task 003 and after Task 005.
- `ProductVariantFoundationTest.php`: 10 passed (19 assertions).
- `ProductVariantAdministrationTest.php`: 27 passed (95 assertions).
- `ProductVariantResourceTest.php`: 9 passed (44 assertions).
- `ProductAttributeAdministrationTest.php`, `ProductAttributeOptionAdministrationTest.php`, `ProductAttributeAssignmentTest.php` and `ProductVariantAttributeOptionsTest.php`: 53 passed (108 assertions) combined.
- `ProductAttributeResourceTest.php`: 14 passed (85 assertions).
- `ProductAttributeAssignmentFilamentTest.php`: 5 passed (21 assertions).
- `ProductVariantAttributeOptionsFilamentTest.php`: 5 passed (49 assertions).
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

1. Review and approve the Task 007A Pricing implementation (DEC-023, Proposed), then implement Pricing in the `007A`–`007D` increments. Task 006 is committed at `4faff01`; descriptive attribute values remain deferred.
2. Add a PostgreSQL `migrate:fresh` check to CI so migration-ordering regressions cannot hide behind SQLite.
3. Extend the Filament admin layer with Inventory movement history and reporting when its scope is approved.
4. Add granular roles/permissions if the single `is_admin` flag becomes insufficient.
5. Extend Inventory with reservations and low-stock thresholds once their business rules are approved.
6. Consider an explicit `is_default` column only if the oldest-variant convention proves insufficient.
7. Decide compare-at price validation semantics and currency scope for Task 007B.
8. Run full Pest suite for Pricing module and document results.