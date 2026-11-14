# Task 002 — Catalog Product Schema and Variant Architecture Assessment

**Status:** Assessment complete 2026-11-11 — approved and implemented by Task 003  
**Priority:** High  
**Implementation:** Variant-first foundation and Inventory re-key implemented in Task 003 (DEC-019)

## Objective

Assess the existing Catalog Product/Category and Inventory implementations and
recommend a first-release product schema, variant architecture, media approach
and incremental implementation plan.

This task is assessment-only. No application code, migrations or dependencies
were changed when it was produced.

**Implementation status (2026-11-11):** The architecture was approved and Phase 1
was implemented by Task 003 — `ProductVariant`, default-variant creation and
backfill, the variant-keyed Inventory re-key and the PostgreSQL migration-order
fix. See
`docs/agent/tasks/active/003-catalog-variant-foundation-and-inventory-rekey.md`
and DEC-019. Variant administration, attributes, media and pricing remain future
tasks.

## Constraints

- Preserve the modular monolith and module ownership.
- Keep business logic outside Filament and Livewire.
- Follow the existing Actions/Contracts/Data conventions.
- Avoid premature abstraction.
- Preserve existing functionality.
- Do not introduce dependencies without approval.
- Do not silently fix the outstanding contract, deletion or test issues.

## 1. Verified current implementation

### Catalog

- `products`: `id`, `name`, `slug` (unique), nullable `description`,
  `is_active`, `status` (`draft` default), timestamps.
- `categories`: `id`, nullable self-referencing `parent_id` (`nullOnDelete`),
  `name`, `slug` (unique), nullable `description`, `is_active`, timestamps.
- `category_product`: composite primary key, cascading deletes from both sides.
- Actions/contracts: product create/find/update/status transition and
  category assign/remove/sync; category create/find/update/delete.
- `ProductStatus`: `draft`, `published`, `archived`; transitions are guarded
  and tested.
- Filament 5 resources for products and categories are module-owned and route
  all writes through Catalog contracts.
- `ProductPolicy`/`CategoryPolicy` require Identity `is_admin`; product policy
  also covers `publish` and `manageCategories`.
- No variants, SKUs, prices, attributes, media, brands or SEO metadata exist.

### Inventory

- `inventories`: `id`, `product_id` FK (cascade), `location` (default
  `default`), `quantity` (default 0), timestamps,
  unique `(product_id, location)`.
- `stock_movements`: `id`, `product_id` FK (cascade), `location`,
  `quantity_delta`, `resulting_quantity`, `reason`, nullable `reference`,
  `created_at` only, indexes on `(product_id, location)` and
  `(product_id, created_at)`.
- `CreateInventory` and `AdjustStock` validate input; adjustments lock the row,
  prevent negative stock, and write an append-only movement in the same
  transaction.
- `StockMovementReadService` provides pagination, product/reason/date filters,
  per-product history and daily summaries.
- Stock is keyed to a product, not a purchasable variant.

### Tests and Git

- Branch `feature/catalog`, HEAD `636b0fc`, clean working tree before this
  assessment.
- Catalog + Inventory focused suite on 2026-11-11:
  **173 passed (541 assertions)**.
- Previous full suite: 204 passed, 3 pre-existing failures (622 assertions).
- Previous PHPStan on Catalog: clean; full-project PHPStan has pre-existing
  Identity errors.

## 2. Limitations and inconsistencies

### Product model

- No purchasable unit: no SKU, no variant, no price.
- Inventory is keyed to the product, so a future variant-based catalog would
  require a second stock path or a disruptive re-key.
- `is_active` and `status` are independent by design (DEC-017), but there is no
  explicit purchasability rule combining them with stock and variant state.
- No product deletion contract or admin action. Hard deletion currently
  cascades to inventory and stock movements.
- Names are not unique; duplicate names receive suffixed slugs.

### Contracts

- `UpdateProduct` treats `null` description as "not provided", so the admin form
  cannot clear a description.
- `UpdateCategory` treats `null` parent as "not provided", so the admin form
  cannot move a child category back to root.
- These are contract-shape limitations, not Filament bugs.

### Migration ordering

Verified on 2026-11-11:

- Module migrations are untimestamped and globally sorted by filename.
- `create_inventories_table` sorts before `create_products_table` even though it
  references `products`.
- SQLite accepts this because foreign-key targets are not validated at DDL
  time; a fresh PostgreSQL database would fail on `migrate`.
- The development database works only because migrations were applied in
  separate batches (`products` batch 2, `inventories` batch 4).
- This must be resolved before adding more cross-table migrations.

### Environment and documentation

- The `2026_11_10_000000_add_is_admin_to_users_table` migration is still
  **pending** on the development PostgreSQL database. Filament tests pass
  against SQLite in-memory.
- `docs/agent/HANDOFF.md` is stale: it records HEAD `0c5cc72` and uncommitted
  Filament work, while the work is committed as `636b0fc` and the tree is clean.
- `module.json` dependencies are declarative only; no cycle/order validation is
  implemented. `ModuleManifest::hasProvider()` ignores its argument (minor,
  unrelated).

## 3. Recommended first-release schema

### Design decision: variant-first

Every product owns at least one `ProductVariant`. Simple products have exactly
one default variant; variable products have several. SKU, inventory, pricing,
cart lines and order lines all reference the variant.

Rejected alternatives:

- **Optional variants with product-level fallback** (`variant_id ?? product_id`):
  creates two stock/price paths, conditional logic in every consumer and a
  painful later migration.
- **Polymorphic purchasable entity**: harder foreign keys, queries and
  authorization; unnecessary for the current scope.

Trade-off: an extra table and a default-variant invariant now, in exchange for
one uniform purchasable unit later.

### `products` (extend existing)

| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | existing |
| `name` | varchar(255) | existing |
| `slug` | varchar(255) unique | existing |
| `description` | text nullable | existing |
| `is_active` | boolean default true | existing; operational availability |
| `status` | varchar(20) default `draft` | existing; publication lifecycle |
| `created_at`, `updated_at` | timestamps | existing |

Deferred: `brand_id`, `published_at`, SEO fields, soft deletes. Do not add a
`type` column in the first release; simple vs variable can be derived from the
variant count. Add one only if the admin UX proves it necessary.

### `product_variants` (new)

| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `product_id` | FK `products` cascade | |
| `sku` | varchar(100) nullable, unique | required by validation for active/published variants |
| `name` | varchar(255) nullable | display label, e.g. "Red / M" |
| `variant_key` | varchar(255) nullable | option signature; unique per product |
| `is_active` | boolean default true | variant availability |
| `position` | integer default 0 | display order |
| `created_at`, `updated_at` | timestamps | |

Indexes: unique `sku`; unique `(product_id, variant_key)`; index
`(product_id, is_active)`; index `(product_id, position)`.

`variant_key` is a deterministic string such as `color:1|size:4` built from
sorted attribute/option pairs. It is the practical way to enforce one variant
per option combination without a multi-row unique constraint.

### `attributes` (new, Phase 3)

| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `code` | varchar(100) unique | stable machine name |
| `name` | varchar(255) | admin label |
| `type` | varchar(20) | `select`, `text`, `number`, `boolean` |
| `created_at`, `updated_at` | timestamps | |

### `attribute_options` (new, Phase 3)

| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `attribute_id` | FK `attributes` cascade | |
| `value` | varchar(100) | canonical value |
| `label` | varchar(255) nullable | display label |
| `position` | integer default 0 | |
| `created_at`, `updated_at` | timestamps | |

Unique `(attribute_id, value)`.

### `product_attribute_values` (new, Phase 3)

Descriptive attributes shared by all variants (material, care, country of
origin, etc.).

| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `product_id` | FK `products` cascade | |
| `attribute_id` | FK `attributes` restrict | |
| `value_option_id` | FK `attribute_options` restrict nullable | select types |
| `value_text` | text nullable | text types |
| `value_number` | decimal(18,4) nullable | number types |
| `value_boolean` | boolean nullable | boolean types |
| `created_at`, `updated_at` | timestamps | |

Unique `(product_id, attribute_id)`. Validate exactly one value column is set
and that it matches the attribute type.

### `variant_attribute_values` (new, Phase 3)

Variant-defining attributes (Color, Size, etc.).

| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `variant_id` | FK `product_variants` cascade | |
| `attribute_id` | FK `attributes` restrict | |
| `attribute_option_id` | FK `attribute_options` restrict | |
| `created_at`, `updated_at` | timestamps | |

Unique `(variant_id, attribute_id)`. First release restricts variant-defining
attributes to `select` type. Free-text variant dimensions can be added later
without changing the variant table.

### `product_media` (new, Phase 4)

| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `product_id` | FK `products` cascade | |
| `variant_id` | FK `product_variants` cascade nullable | variant-specific image |
| `disk` | varchar(50) default `public` | Laravel filesystem disk |
| `path` | varchar(2048) | storage path |
| `original_name` | varchar(255) nullable | |
| `mime_type` | varchar(100) nullable | |
| `size` | bigint nullable | bytes |
| `width`, `height` | integer nullable | image dimensions |
| `alt_text` | varchar(255) nullable | accessibility/SEO |
| `position` | integer default 0 | ordering |
| `is_primary` | boolean default false | primary product image |
| `created_at`, `updated_at` | timestamps | |

Indexes: `(product_id, position)`, `(variant_id, position)`. Enforce one primary
image per product in application code and, on PostgreSQL, with a partial unique
index `(product_id) WHERE is_primary = true` via raw SQL.

Storage recommendation: Laravel `public` disk (already configured) with
`storage:link`; Filament `FileUpload` writes the file, Catalog media actions own
metadata, ordering and deletion. No new dependency (no Spatie Media Library or
image-conversion package). Trade-off: no responsive conversions yet; add them
later with approval.

### `inventories` (re-key)

| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `variant_id` | FK `product_variants` cascade | replaces `product_id` |
| `location` | varchar(100) default `default` | |
| `quantity` | integer default 0 | |
| `created_at`, `updated_at` | timestamps | |

Unique `(variant_id, location)`.

### `stock_movements` (re-key)

| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `variant_id` | FK `product_variants` cascade | replaces `product_id` |
| `location` | varchar(100) | snapshot |
| `quantity_delta` | integer | signed |
| `resulting_quantity` | integer | |
| `reason` | varchar(255) | |
| `reference` | varchar(255) nullable | |
| `created_at` | timestamp nullable | append-only |

Indexes: `(variant_id, location)`, `(variant_id, created_at)`.

Recommendation: drop `product_id` from both Inventory tables. Keeping both
`product_id` and `variant_id` creates a dual source of truth. Product-level
reporting can join through `product_variants`.

### `variant_prices` (future Pricing module, Phase 5)

Do not add price columns to Catalog. The variant is the priceable unit, but
money belongs to a Pricing module that depends on Catalog.

| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `variant_id` | FK `product_variants` cascade | |
| `currency` | char(3) | ISO 4217 |
| `amount_minor` | bigint | integer minor units, never floats |
| `compare_at_amount_minor` | bigint nullable | |
| `starts_at`, `ends_at` | timestamps nullable | |
| `created_at`, `updated_at` | timestamps | |

Unique `(variant_id, currency)` for the first release; price lists and
scheduled prices can extend this later.

## 4. Recommended variant architecture

- Product = shared catalog information and organization.
- Variant = the only purchasable unit; owns SKU, active state, inventory,
  price references and optional media.
- A simple product has exactly one default variant created in the same
  transaction as the product.
- A variable product has multiple variants; each variant is a unique
  combination of variant-defining attribute options.
- Product publication and availability remain independent:
  `purchasable = product.status === published && product.is_active
  && variant.is_active` (plus stock/price checks in the consuming module).
- SKU is nullable at the database level to allow drafts, but required by
  validation once a variant is active or its product is published.
- Variants are deactivated, not hard-deleted, once referenced by inventory
  history, carts or orders. Hard deletion is restricted.

## 5. Proposed relationships

- `Product` hasMany `ProductVariant` (cascade delete).
- `Product` hasMany `ProductMedia`.
- `Product` hasMany `ProductAttributeValue`.
- `Product` belongsToMany `Category` through `category_product` (existing).
- `ProductVariant` belongsTo `Product`.
- `ProductVariant` hasMany `Inventory`.
- `ProductVariant` hasMany `StockMovement`.
- `ProductVariant` hasMany `VariantAttributeValue`.
- `ProductVariant` hasMany `ProductMedia` (variant-specific).
- `Attribute` hasMany `AttributeOption`.
- `Attribute` hasMany `ProductAttributeValue`.
- `Attribute` hasMany `VariantAttributeValue`.
- `AttributeOption` hasMany `VariantAttributeValue`.
- `Category` self-references through `parent_id` (existing).

## 6. Proposed migration sequence

Use zero-padded `z` prefixes so the order is unambiguous and all new tables run
after the existing `create_*` migrations.

0. **Prerequisite:** fix fresh PostgreSQL ordering for the Inventory foreign
   key and document a global module-migration naming convention.
1. `z01_create_product_variants_table`
2. `z02_backfill_default_product_variants` (one default variant per product)
3. `z03_add_variant_id_to_inventories_table` (nullable, backfill, index)
4. `z04_add_variant_id_to_stock_movements_table` (nullable, backfill, index)
5. `z05_finalize_inventory_variant_columns` (non-null, FK, unique, drop
   `product_id`)
6. `z06_create_attributes_table`
7. `z07_create_attribute_options_table`
8. `z08_create_product_attribute_values_table`
9. `z09_create_variant_attribute_values_table`
10. `z10_create_product_media_table`
11. Pricing module: `z11_create_variant_prices_table` (or a correctly ordered
    module-local migration).

Backfill rules:

- Default variant: `sku = 'SKU-'.$product->id`, `name = 'Default'`,
  `is_active = product.is_active`, `position = 0`, `variant_key = null`.
- Every existing inventory row and stock movement maps to its product's default
  variant.
- Backfills must be idempotent and run inside transactions.
- The development database currently has 2 products, 1 inventory record and 1
  movement, so the re-key is low-risk now; it grows more expensive after
  storefront/cart work begins.

## 7. Impact on Inventory, Pricing, Cart and Orders

### Inventory

- `CreateInventoryData` changes from `productId` to `variantId`.
- `Inventory` and `StockMovement` expose `variant()`; optional `product()`
  through the variant for reporting.
- `StockMovementReadService` filters by `variant_id`; add `forVariant()` and a
  `forProduct()` that joins variants.
- Inventory remains per variant and location. No dual stock path.
- Future reservations should be a separate table keyed by variant, location and
  reference; `AdjustStock` remains the only quantity mutation path.

### Pricing

- Variant is the priceable unit.
- Pricing owns `variant_prices`; Catalog must not depend on Pricing.
- Cart and Orders depend on both Catalog (variant identity) and Pricing (price).
- If the first release must be sellable, build the minimal Pricing module in the
  same release rather than putting price on Catalog.

### Cart

- Cart line: `variant_id`, quantity and a captured unit price/currency.
- Availability check combines product status, product/variant active state and
  stock. Do not rely on product-level stock.

### Orders

- Order line: `variant_id` plus immutable snapshots of SKU, product/variant
  name and unit price/currency.
- Historical orders must not depend on mutable product or price rows.
- Once orders exist, variant deletion must be blocked or replaced by
  deactivation/soft deletion.

### Media

- Product gallery = shared media plus variant-specific media.
- Storefront resolves variant-specific primary image first, then the product
  primary image.

## 8. Required changes to Filament administration

- Product form: keep name, description and active state; add media upload and
  descriptive attributes; add SKU to the default-variant flow.
- Add a `ProductVariantRelationManager` (or a dedicated variants page) with SKU,
  name, options, active state, position and stock summary; create/edit/delete
  actions must call Catalog contracts.
- Add a media manager (relation manager or page) with multiple image upload,
  reorder, primary selection, alt text and optional variant association.
- Add product attribute fields and variant option selectors.
- Add `manageVariants` and `manageMedia` abilities to `ProductPolicy` (or
  dedicated variant/media policies), registered in
  `CatalogServiceProvider::bootModule()`.
- Enforce publication prerequisites in `TransitionProductStatus`, not in
  Filament: at least one active variant with a SKU, and a price when Pricing is
  active.
- Do not add bulk delete actions. Variants and media should be deactivated or
  deleted through guarded contracts.
- Do not add a naive product delete action; use `archive` until the deletion
  policy is approved.
- The existing category parent-clearing limitation remains out of scope.

## 9. Testing strategy

- Pest feature tests with `RefreshDatabase` and SQLite in-memory, matching the
  existing suite.
- Factories and states: `ProductVariantFactory` (default, inactive, with
  options), `AttributeFactory`, `AttributeOptionFactory`,
  `ProductMediaFactory`.
- Product creation test: product and default variant are created atomically;
  rollback leaves neither.
- Variant tests: unique SKU, unique `(product_id, variant_key)`, active state,
  position, cascade delete.
- Attribute tests: definition/option validation, typed product values, select
  variant values, duplicate combination rejection.
- Media tests: attach/remove/reorder/primary, file deletion, `Storage::fake`.
- Inventory tests: variant-keyed stock, multiple locations, product-level
  history via join, movement filters, cascade delete.
- Backfill test: seed products/inventories/movements, run the backfill, assert
  default variants and re-keyed rows. Prefer extracting backfill logic into a
  testable action/command rather than testing raw migration internals.
- Filament tests: variant relation manager, media upload, authorization,
  publish guard and absence of bulk delete.
- Add a PostgreSQL CI check that runs `migrate:fresh`, because SQLite hides
  foreign-key ordering problems.
- Run Pint and PHPStan on touched modules.
- Keep the 3 pre-existing unrelated test failures and Identity PHPStan errors
  out of scope.

## 10. Incremental implementation plan

### Phase 0 — Migration ordering and PostgreSQL baseline (prerequisite)

- Fix the fresh PostgreSQL migration order for Inventory.
- Document the global migration naming convention.
- Add a CI check that runs `migrate:fresh` on PostgreSQL.
- No schema changes beyond the ordering fix.

### Phase 1 — Variant foundation and Inventory re-key (recommended first task)

- `product_variants` table, model and factory.
- Default-variant backfill for existing products.
- `CreateProduct` creates a default variant atomically; optional SKU in
  `CreateProductData`.
- Re-key Inventory models, actions, contracts, data, read service and tests to
  `variant_id`.
- Update CATALOG.md, PROJECT_STATE.md, WORK_LOG.md, HANDOFF.md and add a DEC for
  variant-first architecture.
- Acceptance: fresh migrations pass on SQLite and PostgreSQL; Catalog and
  Inventory tests pass; no dual stock path; existing product data is preserved.

### Phase 2 — Variant administration

- Filament variant relation manager, policies, SKU/activation validation and
  publish guard.
- Tests for admin flows and authorization.

### Phase 3 — Attributes and options

- Attribute/option tables and actions.
- Product and variant attribute values.
- Variant combination uniqueness via `variant_key`.
- Filament attribute management and variant generation.

### Phase 4 — Media

- `product_media` table, actions and storage integration.
- Filament upload, reorder, primary selection and variant association.
- File deletion and `Storage::fake` tests.

### Phase 5 — Pricing module

- New Pricing module depending on Catalog.
- `variant_prices`, contracts, Filament price management and tests.
- No price columns in Catalog.

### Phase 6 — Storefront and Cart integration

- Product listing/detail read models with variants, attributes, media and price.
- Cart and future Orders keyed to `variant_id` with price snapshots.

## 11. Outstanding issues (not addressed by this assessment)

1. `UpdateProduct` cannot clear a description because `null` means "not
   provided".
2. `UpdateCategory` cannot clear a parent back to root for the same reason.
3. No product deletion administration. A naive delete would cascade inventory
   and movement history; deletion policy must be decided first.
4. Three pre-existing unrelated test failures:
   `Tests\Feature\Auth\AuthenticationTest`,
   `Tests\Feature\Auth\TwoFactorChallengeTest`,
   `Tests\Feature\PostCreatePageTest`.
5. Pre-existing Identity PHPStan errors (`User` PasskeyUser contract and
   `IdentityServiceProvider` `LogoutUser`/`LogOutUser` case) and full-project
   PHPStan errors outside Catalog.
6. Fresh PostgreSQL migration ordering is unsafe for Inventory.
7. `HANDOFF.md` is stale (records `0c5cc72` and uncommitted work).
8. The `is_admin` migration is pending on the development database.
9. Module dependency metadata is not validated, and
   `ModuleManifest::hasProvider()` ignores its argument (minor).

## 12. Decisions requiring approval

1. Adopt variant-first architecture: every product has at least one variant;
   SKU, inventory, pricing, cart and order lines reference the variant.
2. Keep price out of Catalog and create a Pricing module for
   `variant_prices`.
3. Use the Laravel `public` disk plus Filament `FileUpload` for media; do not
   add Spatie Media Library or an image-conversion dependency.
4. Use global attribute definitions/options; restrict variant-defining
   attributes to `select` type in the first release.
5. Prefer archive/deactivation over hard deletion for products and variants
   once inventory/order history exists.
6. Fix module migration ordering before adding new cross-table migrations.

## Proposed first implementation task

**Task 003 — Catalog variant foundation and Inventory variant re-key**

Include the Phase 0 migration-ordering prerequisite. Deliver
`product_variants`, default-variant backfill, product creation integration,
Inventory re-key to `variant_id`, contract/data/model/service updates, tests,
and documentation. Do not add attributes, media, pricing or product deletion in
this task.

## Final report

### Files changed

- `docs/agent/tasks/active/002-catalog-product-schema-and-variant-assessment.md`
  (this assessment).

### Application changes

- None. No code, migrations, dependencies or tests were modified.

### Verification

- Catalog + Inventory focused suite: 173 passed (541 assertions) on 2026-11-11.
- Git inspected: branch `feature/catalog`, HEAD `636b0fc`, clean before the
  assessment.
- Migration order inspected: `create_inventories_table` sorts before
  `create_products_table`.
- Development data inspected: 2 products, 0 categories, 1 inventory record,
  1 stock movement.

### Outstanding issues

See section 11.

### Next recommended action

~~Approve or reject the decisions in section 12, then authorise Task 003.~~
Done: the decisions were approved and Phase 1 was implemented by Task 003.
Next: implement variant administration, then attributes/options, media and the
Pricing module.
