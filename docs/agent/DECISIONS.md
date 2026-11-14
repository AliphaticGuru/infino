# Infino — Architecture and Product Decisions

This document distinguishes established direction from decisions still awaiting confirmation.

## Confirmed direction

### DEC-001 — Modular monolith
**Status:** Confirmed

Infino is being built as a modular monolith rather than a microservices system.

### DEC-002 — Laravel application location
**Status:** Confirmed

The Laravel application resides in `src/`. Repository-level infrastructure and agent documentation remain outside it.

### DEC-003 — Core module foundation
**Status:** Implemented in repository; behavior needs verification

Shared module infrastructure is represented by the Core module, including `BaseModuleServiceProvider`, `BaseModel` and `Data`.

### DEC-004 — Module directory configuration
**Status:** Confirmed in repository

`src/config/modules.php` defines the module root and standard subdirectories.

### DEC-005 — Docker Compose development workflow
**Status:** Confirmed

Docker Compose is the standard way to run application commands in the current development environment.

### DEC-006 — Technology direction
**Status:** Confirmed

The current technology direction is Laravel 13, PHP 8.5, Livewire 4, Filament 5, PostgreSQL, Redis and Nginx.

PHP 8.5.7 has been verified in the CLI container. Recheck all versions when changing dependencies.

### DEC-007 — Agent memory is repository-based
**Status:** Confirmed

Shared project context is maintained in repository documentation so different agents can use the same source of truth.

### DEC-017 — Product publication lifecycle is separate from operational availability
**Status:** Confirmed
**Date:** 2026-11-10
**Branch:** `feature/catalog`

Context:
Products needed a publication lifecycle without overloading the existing
`is_active` flag, which already expresses operational availability. The Catalog
module also lacked a status representation and any guarded transition rules.

Decision:
- `is_active` remains the operational-availability flag. It does not express
  publication state and is not derived from it.
- Publication state is a separate `status` column represented by the backed
  `Catalog\Support\ProductStatus` enum with values `draft`, `published` and
  `archived`.
- New products default to `draft`. Existing rows are backfilled to `draft`,
  because publication is a new concept that cannot be inferred from `is_active`.
- Transitions are owned by the Catalog module through the
  `TransitionsProductStatus` contract and the `TransitionProductStatus` action.
- Allowed transitions: draft → published/archived, published → draft/archived,
  archived → draft only. Archived products cannot be published directly; they
  must be restored to draft first.
- Invalid transitions raise `ValidationException`; missing or deleted products
  raise `ModelNotFoundException`. Transitions run inside a database transaction.
- The enum lives in `Support/` because `config/modules.php` does not define an
  `Enums` module subdirectory and creating one was unnecessary.

Rationale:
Keeping publication state and availability independent prevents "published but
inactive" and "active but draft" from being conflated, and lets stock or
merchandising status change without mutating the publication record. A single
contract with named operations keeps the transition rules in one place rather
than scattered across models or controllers.

Consequences:
- Product schema gains one non-null `status` column with a `draft` default.
- Lifecycle changes affect only `status` and `updated_at`; unrelated attributes
  are preserved.
- Delivery layers (Filament, API, Livewire) must call the action rather than
  writing `status` directly.

Numbering note:
DEC-014, DEC-015 and DEC-016 are recorded on `feature/inventory`. This decision
uses DEC-017 to avoid duplicate IDs when the branches are integrated.

### DEC-018 — Filament admin access is gated by an explicit admin flag and module policies
**Status:** Confirmed
**Date:** 2026-11-11
**Branch:** `feature/catalog`

Context:
The Filament admin panel at `/admin` existed with authentication middleware but
no authorization foundation. There were no roles, permissions or policies, and
the Identity `User` model did not implement `FilamentUser`. Any authenticated
user could therefore reach the panel. Catalog product and category management
also needed an authorised delivery layer without moving business rules out of
the module.

Decision:
- Identity users gain an `is_admin` boolean (default `false`) as the smallest
  explicit admin foundation. `User` implements `FilamentUser` and
  `canAccessPanel()` returns `$this->is_admin`.
- Panel access is enforced by the panel's `Authenticate` middleware, independent
  of navigation visibility.
- Catalog owns `ProductPolicy` and `CategoryPolicy`; both require `is_admin` for
  CRUD abilities. `ProductPolicy` additionally covers `publish` and
  `manageCategories`.
- Policies are registered with `Gate::policy()` in
  `CatalogServiceProvider::bootModule()`.
- Filament resources live under `src/app/Modules/Catalog/Filament/` and are
  discovered by `AdminPanelProvider`. They call existing Catalog contracts for
  every write; no business rules are duplicated in Filament or Livewire.
- Custom lifecycle and category actions declare explicit `authorize()`
  callbacks. No bulk delete actions are registered, so bulk operations cannot
  bypass per-record policy checks.

Rationale:
An explicit boolean is the smallest change that makes admin access auditable and
testable without inventing a role system prematurely. Keeping policies in the
Catalog module preserves module ownership of product and category authorization.

Consequences:
- Admin access must be granted deliberately (seeder or direct database update).
- A future roles/permissions system can replace `is_admin` behind the same
  `canAccessPanel()` and policy boundaries.
- New Catalog Filament actions must call module contracts and declare
  authorization explicitly.

### DEC-020 — Default variant identity and variant lifecycle rules
**Status:** Confirmed and implemented
**Date:** 2026-11-11
**Branch:** `feature/catalog`

Context:
Task 003 introduced variants without an explicit default marker. Variant
administration needs a stable rule for which variant is the default, which
lifecycle operations are legal, and how ordering is kept consistent.

Decision:
- The default variant is the product's oldest variant, determined by lowest
  `product_variants.id`. No additional `is_default` column is introduced.
  Product creation and backfill create the default variant first, and new
  variants are appended, so the convention is deterministic and survives label
  edits and repositioning.
- `Product::defaultVariant()` exposes the oldest variant and
  `ProductVariant::isDefault()` compares against it.
- The default variant cannot be deactivated. A product therefore always has at
  least one active variant; operational availability remains controlled by the
  product's `is_active` and publication `status`.
- Any variant can be activated. Deactivating an already inactive variant, or the
  last active variant, raises `ValidationException`.
- Variants are not hard-deleted through the administration layer. Deactivation
  preserves inventory rows and append-only stock movement history.
- Variant positions are zero-based and normalized to `0..n-1` per product.
  Creation appends and normalizes. Repositioning requires the complete,
  duplicate-free set of that product's variant IDs inside a transaction with row
  locking, so missing, duplicate or cross-product IDs are rejected.
- Variant updates accept only SKU, label and `variant_key`; the owning product
  cannot be changed through the update contract.
- Filament delegates creation, editing, lifecycle changes and reordering to
  Catalog contracts/actions.

Rationale:
A deterministic default and a small set of guarded lifecycle transitions keep
inventory and future order references valid without adding a schema flag that
would need backfilling and concurrency protection. Rejecting deletion preserves
the audit trail already established for stock movements.

Consequences:
- If a future workflow needs to promote a different variant to default, the
  oldest-id convention must be replaced by an explicit marker in a separate
  decision.
- Variant administration cannot remove variants; it can only deactivate them.
- Position management is intentionally product-scoped.

### DEC-019 — Variant-first product architecture and variant-owned inventory
**Status:** Confirmed and implemented for the foundation
**Date:** 2026-11-11
**Branch:** `feature/catalog`

Context:
Products previously had no purchasable unit: no SKU, no variant and no price.
Inventory was keyed to `product_id`, so a future variant-based catalog would
have required either a second stock path or a disruptive re-key after real stock
history existed. The development database already held inventory and movement
rows that had to survive the change.

Decision:
- Every product owns at least one `ProductVariant`. A simple product has exactly
  one default variant; a variable product has several. The variant is the only
  purchasable unit.
- SKU, inventory, pricing, cart lines and order lines reference the variant, not
  the product.
- `product_variants` stores `product_id`, nullable globally unique `sku`,
  nullable `label`, nullable `variant_key`, `is_active`, `position` and
  timestamps. `variant_key` is unique per product and holds a deterministic
  option signature such as `color:1|size:4`.
- `sku` is nullable in the database to allow drafts but globally unique when
  present. `CreateProduct` accepts an optional SKU, rejects duplicates through
  validation, and otherwise generates a collision-safe `SKU-{productId}`.
- Product creation and default-variant creation run in one database transaction.
  Existing products are backfilled idempotently with a default variant.
- Inventory is re-keyed from `product_id` to `variant_id`. `inventories` is
  unique on `(variant_id, location)`; `stock_movements` keeps its append-only
  shape with variant-keyed indexes. Product-level reporting joins through
  `product_variants`; there is no dual stock path.
- Existing inventory and movement rows are mapped to their product's default
  variant without losing IDs, quantities, history or timestamps.
- Pricing stays outside Catalog in a future Pricing module. Media uses the
  Laravel `public` disk with Filament `FileUpload`. Attributes use normalized
  global definitions/options, with select-based variant dimensions. Products and
  variants are archived/deactivated rather than hard-deleted once business
  history exists.

Migration strategy:
- Module migrations are untimestamped and globally sorted by filename.
  `create_inventories_table` sorts before `create_products_table`, so its inline
  product foreign key was removed; the final variant foreign key is added by the
  re-key migrations after `product_variants` exists.
- The re-key adds nullable `variant_id`, backfills from the default variant,
  aborts if any row cannot be mapped, then rebuilds both tables with non-null
  variant foreign keys and the final indexes. Primary keys and row IDs are
  preserved. PostgreSQL sequences are reset after the rebuild.
- Down migrations rebuild the product-keyed shape from the variant join.

Rationale:
A single purchasable unit avoids conditional `variant_id ?? product_id` logic in
inventory, pricing, cart and orders. Paying the extra table and default-variant
invariant now is cheaper than migrating live stock and order history later.

Consequences:
- Product creation must always produce at least one variant; callers that create
  products outside `CreateProduct` must do the same.
- Inventory actions, data objects, factories and read services now speak
  `variant_id`. Product-level queries go through the variant relationship.
- Variant administration, attributes, media and pricing remain separate future
  tasks; this decision does not authorise them.
- Variant hard deletion cascades to inventory and movements; once orders exist,
  deletion must be replaced by deactivation/archiving.

### DEC-015 — Inventory stock movement history is append-only
**Status:** Confirmed for the Inventory foundation

Every successful stock adjustment records a StockMovement with the product,
location, signed quantity delta, resulting quantity, reason, optional reference
and creation timestamp. Movement records have no `updated_at` and are
append-only — no update or delete operations are supported through normal
application operations. Persistence of stock and movement happens in a single
database transaction with row-level locking. A rejected or zero adjustment
produces no movement.

Consequences:
- Movement history is immutable and auditable.
- Failed adjustments leave both stock and movement table unchanged.
- No update or delete operations are required; movements are never modified.
- Zero adjustments are rejected and produce no movement.

This is distinct from DEC-014 (stock scoped by product/location) in that it
provides the audit trail. DEC-014 and DEC-015 are complementary.

### DEC-016 — Stock movement reads and reporting are a read-only service
**Status:** Confirmed for the Inventory foundation

Stock movement retrieval and reporting live in
`Inventory\Services\StockMovementReadService`, not in Actions. The service is
read-only: it never creates, updates or deletes inventory or movement records,
and it performs no writes of any kind. Reporting summaries are derived from the
recorded movement history rather than from stored aggregates, so they cannot
drift from the audit trail.

Consequences:
- Pagination, product/reason/date filtering and daily summaries share one
  filtered query builder inside the service.
- Summaries distinguish positive and negative deltas from the movements table.
- No HTTP endpoints or UI are provided yet; the service is an internal module
  API for future Filament/API layers.
- Movement history remains append-only; reads do not acquire write locks.

## Initial domain-boundary guidance

These boundaries are architectural guidance to preserve during implementation. Refine them through explicit decisions when necessary.

- Identity owns identity and authentication concerns.
- Catalog owns product information.
- Inventory owns stock operations.
- Orders own order records and lifecycle.
- Payments own payment transactions and provider communication.
- Checkout coordinates the purchase workflow.
- Core owns shared technical foundations, not unrelated business features.

## Decisions awaiting confirmation

### DEC-008 — Multi-tenancy
**Status:** Open

Determine whether Infino serves one platform/store, multiple independent stores, multiple merchants, or another model. Do not implement tenant isolation until the model is defined.

### DEC-009 — Commerce model
**Status:** Open

Confirm whether the platform is a single-vendor store, multi-vendor marketplace, configurable commerce platform, or a combination.

### DEC-010 — Product types
**Status:** Open

Confirm support for physical products, digital products, services or combinations.

### DEC-011 — Frontend architecture
**Status:** Partially decided

Livewire 4 and Filament 5 are in the current direction. Inertia has been considered but is not confirmed as part of the application architecture.

### DEC-012 — Payment and shipping providers
**Status:** Open

Provider choices and integration priorities are not yet recorded.

### DEC-013 — Deployment architecture
**Status:** Open

Deployment options have been explored, but the final production architecture is not established in this decision record.

### DEC-021 — Normalized product attribute and option model
**Status:** Implemented and verified (Phase B complete, 2026-11-12)
**Date:** 2026-11-12
**Branch:** `feature/catalog`

Context:
Task 004 left `product_variants.variant_key` as a free-form, per-product unique
string with no normalized definition of what a variant dimension or option is.
Task 005 needs reusable global attribute definitions, owned options,
product-to-attribute assignments and unambiguous variant-to-option selections,
without breaking the variant-first architecture, variant SKU uniqueness, the
default-variant invariant, product-scoped variant positions or the variant-keyed
Inventory relationships.

Proposed entity model:

```
ProductAttribute 1---* ProductAttributeOption
ProductAttribute *---* Product          (product_attribute_product)
Product          1---* ProductVariant
ProductVariant   1---* ProductVariantAttributeOption *---1 ProductAttribute
                                                     *---1 ProductAttributeOption
```

The variant remains the canonical purchasable unit. Inventory continues to
reference `variant_id`; no Inventory table or action changes.

Proposed tables:

`product_attributes` (global definitions):
- `id`
- `code` string(100), globally unique, immutable machine identifier
- `name` string(255), editable display name
- `type` string(20), default `select`; only `select` is supported in Task 005
- `is_variant_dimension` boolean, default `false`
- `position` integer, default `0`
- `is_active` boolean, default `true`
- timestamps
- unique `code`; index `(is_active, position)`

`product_attribute_options` (options owned by one attribute):
- `id`
- `product_attribute_id` FK to `product_attributes`, cascade on delete
- `code` string(100), immutable machine identifier, unique per attribute
- `label` string(255), editable display label
- `position` integer, default `0`
- `is_active` boolean, default `true`
- timestamps
- unique `(product_attribute_id, code)`; unique `(id, product_attribute_id)`
  (needed as the target of the composite FK below); index
  `(product_attribute_id, position)`

`product_attribute_product` (which attributes apply to a product):
- `product_id` FK to `products`, cascade on delete
- `product_attribute_id` FK to `product_attributes`, cascade on delete
- composite primary key `(product_id, product_attribute_id)`
- index on `product_attribute_id`

`product_variant_attribute_options` (one selected option per attribute per
variant):
- `id`
- `product_variant_id` FK to `product_variants`, cascade on delete
- `product_attribute_id` FK to `product_attributes`, restrict on delete
- `product_attribute_option_id` unsigned bigint
- timestamps
- unique `(product_variant_id, product_attribute_id)`
- composite FK `(product_attribute_option_id, product_attribute_id)` references
  `product_attribute_options (id, product_attribute_id)` with restrict on
  delete, so an option can never be attached to a different attribute
- index on `product_attribute_option_id`

Relationship names:
- `ProductAttribute::options()` hasMany; `ProductAttribute::products()`
  belongsToMany.
- `ProductAttributeOption::attribute()` belongsTo;
  `ProductAttributeOption::variantSelections()` hasMany.
- `Product::productAttributes()` belongsToMany. The relation must **not** be
  named `attributes()` because Eloquent already uses the `$attributes` property.
- `ProductVariant::attributeOptions()` hasMany to the selection model.
- `ProductVariantAttributeOption` belongsTo the variant, attribute and option.

Data integrity rules:
- Attribute `code` is globally unique and immutable after creation. Option
  `code` is unique per attribute and immutable after creation. Labels/names are
  editable.
- Only active attributes can be newly assigned to a product; only active
  attributes and options can be newly selected by a variant. Task 005 product
  assignment is limited to attributes with `is_variant_dimension = true`;
  descriptive attributes and product-level values are deferred.
- A product-attribute assignment is unique per `(product_id,
  product_attribute_id)`.
- A variant may hold at most one option per attribute, enforced by the unique
  `(product_variant_id, product_attribute_id)` constraint.
- The composite FK guarantees that the selected option belongs to the selected
  attribute. Application validation additionally guarantees that the attribute
  is assigned to the variant's product and is a variant dimension.
- Variant option selection is complete: when a product has assigned variant
  dimensions, a configured variant must select exactly one active option for
  each dimension. A selection set containing an unknown, duplicate, inactive or
  cross-product attribute/option is rejected with `ValidationException`.
- Definitions and options are deactivated rather than hard-deleted once
  referenced. Hard deletion is allowed only when the attribute is not assigned
  to any product and the option is not selected by any variant; otherwise the
  operation is rejected.
- All multi-row writes run inside a database transaction with row locking.

Relationship with `variant_key`:
- `product_variants.variant_key` remains the authoritative per-product unique
  combination signature and its existing unique index is retained.
- For variants with normalized option selections, `variant_key` is derived
  deterministically as `attributeCode:optionCode` pairs sorted by attribute
  `position` then `code`, joined with `|` (for example
  `color:red|size:m`). Codes are immutable, so the key is stable.
- When option selections are supplied, a manually supplied `variant_key` must
  be absent or equal to the derived key; otherwise the operation is rejected.
- Variants without normalized selections keep the current behaviour: an
  optional manual `variant_key`, unique per product when present.
- Existing `variant_key` values and existing variant administration tests remain
  valid; no backfill is required.

Proposed contracts and actions (all in Catalog):
- Definitions: `FindsProductAttributes`, `CreatesProductAttributes`,
  `UpdatesProductAttributes`, `DeletesProductAttributes` with matching actions
  and `CreateProductAttributeData` / `UpdateProductAttributeData`.
- Options: `FindsProductAttributeOptions`, `CreatesProductAttributeOptions`,
  `UpdatesProductAttributeOptions`, `DeletesProductAttributeOptions` with
  matching actions and `CreateProductAttributeOptionData` /
  `UpdateProductAttributeOptionData`.
- Product assignment: `AssignsProductAttributes`,
  `RemovesProductAttributes`, `SyncsProductAttributes` with matching actions.
- Variant mapping: `SyncsVariantAttributeOptions` /
  `SyncVariantAttributeOptions` / `SyncVariantAttributeOptionsData`.
- Integration: `CreateProductVariantData` and `UpdateProductVariantData` gain an
  optional `attributeOptions` array. When supplied, the existing
  `CreateProductVariant` / `UpdateProductVariant` actions apply the same
  validation and key derivation inside their existing transaction, so variant
  creation/editing stays atomic and does not bypass variant administration.
- Support: `Support\AttributeType` (`select`) and `Support\VariantKey` for key
  derivation/normalization.
- Policies: `ProductAttributePolicy`, `ProductAttributeOptionPolicy`
  (admin-only; deletion additionally guarded by reference checks).
  `ProductPolicy` gains `manageAttributes` for the product assignment UI.

Proposed Filament administration:
- `AttributeResource` under `Filament/Resources/Attributes/` with list, create
  and edit pages for definitions.
- `OptionsRelationManager` on the attribute edit page for option CRUD and
  reordering.
- `ProductAttributesRelationManager` on the product edit page for
  attach/detach, delegating to the assignment contracts.
- `ProductVariantForm` gains an option-selection component for the product's
  assigned variant dimensions (initially a `Repeater` of attribute/option
  pairs, transformed to `[attribute_id => option_id]`; a dynamic per-dimension
  form can replace it later). The action, not Filament, enforces completeness
  and uniqueness.
- All writes route through Catalog contracts; Filament duplicates no business
  rules.

Migration and backward compatibility:
- New untimestamped Catalog migrations, named `z07_create_product_attributes_table`,
  `z08_create_product_attribute_options_table`,
  `z09_create_product_attribute_product_table` and
  `z10_create_product_variant_attribute_options_table`. The `z03`–`z06`
  prefixes are already used by Inventory migrations, so they must not be
  reused.
- No existing table is altered and no data backfill is required.
- Existing variants, manual `variant_key` values, SKUs, positions, default
  variants and Inventory rows are unaffected.
- PostgreSQL verification included a fresh `migrate:fresh`, rollback and
  re-apply on a throwaway database, plus the full test suite.

Resolved questions:
1. Confirmed: `variant_key` derivation uses immutable
   `attributeCode:optionCode` pairs, not option IDs.
2. Confirmed: only the `select` attribute type is in scope for Task 005;
   text/number/boolean are deferred.
3. Confirmed: product-level attribute *values* (for example Material = Cotton)
   are out of scope; Task 005 stores assignments and variant option selections
   only.
4. Confirmed: a variant of a dimensioned product must select one option per
   assigned variant dimension when its options are created or edited.
   Pre-existing variants without selections remain valid until edited.
   Requiring full configuration before publication remains a future decision.
5. Confirmed: definitions/options are deactivated rather than hard-deleted once
   referenced, with guarded hard deletion only when unreferenced.

### Implementation outcome (verified 2026-11-12)

All five tables, contracts, actions, data objects, policies, Filament
administration and tests described above were implemented on `feature/catalog`.
No deviations from the approved entity model or integrity rules were made.

- Migrations `z07`–`z10` applied, rolled back and re-applied cleanly on a
  throwaway PostgreSQL database.
- `variant_key` is derived as approved; existing manual keys and unconfigured
  variants remain valid.
- Inventory was not modified; variant option changes do not touch inventory rows
  or stock movements.
- Catalog focused suite: 265 passed (862 assertions). Inventory focused suite:
  52 passed (174 assertions). Full suite: 348 passed, 3 failed (1117
  assertions); the three failures are the known pre-existing authentication and
  post-create page failures.
- Pint and module PHPStan (Catalog and Inventory) pass.
- Filament administration: `AttributeResource` with `OptionsRelationManager`,
  `ProductAttributesRelationManager`, and per-dimension option selects in
  `ProductVariantForm`. All writes delegate to Catalog contracts.

### DEC-022 — Product media is Catalog-owned with a storage abstraction
**Status:** Accepted (implemented and verified 2026-11-13)
**Date:** 2026-11-12
**Branch:** `feature/catalog`
**Task:** `docs/agent/tasks/active/006-catalog-product-media.md`

Context:
Task 005 completed normalized product attributes and options. Product media and
galleries are the next Catalog capability in the Product Blueprint and roadmap.
The repository currently has no media package, no image-processing extension
(only `fileinfo`; GD and Imagick are absent), no `public/storage` link, and
`FILESYSTEM_DISK=local`. Product images are public catalog assets that must work
on local development storage now and S3-compatible storage later without
changing the domain model.

Options considered:

1. **Catalog-owned `ProductMedia` with a `MediaStorage` abstraction
   (recommended).** Product and variant galleries stay inside Catalog; all
   filesystem work is isolated behind one contract.
2. Separate Media module. Rejected for now: Catalog is the only consumer, and a
   new module would add manifests, dependencies, migrations and contracts before
   a second consumer exists. The storage contract keeps extraction cheap later.
3. Spatie Media Library. Rejected: new dependency, polymorphic media table and
   conversion pipeline are more than required; conversions need an image driver
   that is not installed.
4. Intervention Image or server-side thumbnails. Rejected: new dependency and a
   runtime change for GD/Imagick that has not been approved.
5. Database byte storage or external-URL-only media. Rejected: database bloat,
   backup growth, no ownership or link rot.

Decision:

- Catalog owns `ProductMedia` and product/variant galleries.
- A Catalog-owned `MediaStorage` contract isolates storage operations. The first
  implementation uses Laravel's `Storage` facade; it is extracted to Core or a
  Media module only when a second consumer exists.
- The default development disk is Laravel's `public` disk with
  `php artisan storage:link`. `FILESYSTEM_DISK` stays `local`; Catalog selects
  its media disk explicitly through `config/catalog.php`.
- Production may use the existing `s3` disk by changing `CATALOG_MEDIA_DISK`
  and the standard `AWS_*` variables. The `disk` is stored per row so existing
  media remains readable after a move.
- No image conversion, resizing, thumbnail generation or EXIF stripping in the
  first release. Images are served at original size. A future conversion step
  requires a separately approved dependency and runtime change.
- SVG and other script-capable formats are rejected. Allowed formats are JPEG,
  PNG, WebP and GIF, with a 5 MB default limit and an 8000 × 8000 dimension cap.

Schema `product_media`:

- `id`
- `product_id` FK to `products`, cascade
- `product_variant_id` FK to `product_variants`, cascade, nullable
- `disk` string(50), default `public`
- `path` string(1024), generated server-side
- `original_name` string(255), nullable, sanitized
- `mime_type` string(100), nullable
- `size` unsigned bigint, nullable
- `width`, `height` unsigned integer, nullable
- `alt_text` string(255), nullable
- `position` unsigned integer, default `0`
- `is_primary` boolean, default `false`
- timestamps
- indexes `(product_id, position)`, `(product_variant_id, position)` and
  `(product_id, is_primary)`
- composite FK `(product_variant_id, product_id)` to
  `product_variants (id, product_id)` with cascade, backed by an additive unique
  index on `product_variants (id, product_id)`
- PostgreSQL-only partial unique indexes enforce one primary per product
  gallery and one primary per variant gallery. SQLite tests rely on
  transactional application enforcement.

Gallery rules:

- Product-level media has `product_variant_id = null`; variant media references
  a variant owned by the same product.
- `position` is scoped to one gallery and normalized to `0..n-1`.
- At most one primary exists per gallery. The first image in an empty gallery
  becomes primary; setting a new primary clears the previous one; deleting the
  primary promotes the next image by `position` then `id`.
- Variant gallery reads fall back to the product gallery when the variant has no
  images. Variant primary resolution is variant primary, then product primary,
  then product first image, then `null`.
- Variant association is immutable after upload; moving an image requires delete
  and re-upload.

Contracts and actions (all in Catalog):

- `FindsProductMedia` / `FindProductMedia`
- `AddsProductMedia` / `AddProductMedia` / `AddProductMediaData`
- `UpdatesProductMedia` / `UpdateProductMedia` / `UpdateProductMediaData`
- `SetsPrimaryProductMedia` / `SetPrimaryProductMedia`
- `DeletesProductMedia` / `DeleteProductMedia`
- `RepositionsProductMedia` / `RepositionProductMedia` /
  `RepositionProductMediaData`
- `MediaStorage` / `FilesystemMediaStorage` / `StoredMediaData`

Transactional consistency:

- Upload stores the file first, then writes the row in a transaction. A failed
  database write deletes the stored file before rethrowing.
- Delete removes the row and promotes the next primary in a transaction, then
  deletes the file after commit. A failed file delete is logged and does not
  fail the request.
- Primary and reorder operations lock the affected gallery rows.
- Database cascades from product or variant deletion cannot clean up files; a
  future `catalog:prune-media` command will reconcile storage against the
  database. This limitation is documented.

Filament administration:

- `MediaRelationManager` on the product edit page with gallery tabs for the
  product and each variant.
- Upload action uses `FileUpload::storeFiles(false)` and calls
  `AddsProductMedia`; Filament never stores or creates records directly.
- Alt-text editing, set-primary, delete and move-up/move-down actions call the
  corresponding contracts. Built-in drag reordering is not used because it
  writes positions directly.
- `ImageColumn` reads the row's `disk`.
- `ProductPolicy::manageMedia` and `ProductMediaPolicy` restrict all media
  administration to admins.

Consequences and migration implications:

- New additive migration `z11_create_product_media_table.php`; no existing table
  is altered except for the additive unique index on
  `product_variants (id, product_id)`.
- No data backfill is required.
- Inventory, attributes/options, variant keys, SKUs, positions and default
  variants are unaffected.
- `php artisan storage:link` becomes a required local setup step.
- No Composer dependency is added.
- Implemented and verified on 2026-11-13: Catalog focused suite 337 passed
  (1073 assertions), Task 006 media tests 72 passed (211 assertions), Inventory
  focused suite 52 passed (174 assertions), Pint and module PHPStan clean, and
  PostgreSQL `migrate:fresh`/rollback/re-apply plus direct enforcement checks of
  the partial unique indexes and composite FK succeeded.
- Deferred follow-ups: thumbnails/conversions, EXIF stripping, a
  `catalog:prune-media` reconciliation command, and the production S3-compatible
  provider/public URL strategy.

Resolved questions:

1. Confirmed the 5 MB default limit and JPEG/PNG/WebP/GIF allowlist.
2. Confirmed product-page gallery tabs for variant media.
3. Confirmed variant-gallery fallback to the product gallery.
4. Confirmed delete-and-re-upload for moving media between galleries.
5. Confirmed that thumbnails, conversions and EXIF stripping are deferred.
6. Production S3-compatible provider and public URL strategy remain a deployment
   follow-up.

### DEC-023 — Pricing is a separate module with integer minor-unit money
**Status:** Accepted
**Date:** 2026-11-14
**Branch:** `feature/pricing`
Context:
DEC-019 makes the product variant the only purchasable unit and states that
pricing belongs to a future Pricing module, not to Catalog. Catalog, Inventory
and product media are implemented and must remain price-free. No price, currency
or monetary storage exists in the repository. The runtime has `intl`, `mbstring`
and `fileinfo` but not `bcmath` or `gmp`, so arbitrary-precision decimal
arithmetic is unavailable.

Decision (Accepted):
- Pricing is a dedicated module under `src/app/Modules/Pricing/` that depends on
  Core and Catalog and references `product_variants.id`. Catalog must not depend
  on Pricing and must not gain price columns or price relationships.
- The MVP stores one current base price per `(variant_id, currency)` in
  `variant_prices`, with an optional compare-at amount in the same currency.
- Money is stored as integer minor units in a `bigint` column plus an uppercase
  ISO 4217 `char(3)` currency. No floats, doubles or decimal casts are used for
  money. Currency exponents are configuration in `config/pricing.php`.
- Major-unit input is parsed with string operations and excess precision is
  rejected rather than rounded.
- Every price creation, update and removal writes one immutable row to
  `variant_price_changes` in the same transaction as the price mutation.
- Price lists, customer/group pricing, scheduled and promotional pricing,
  coupons, tax, FX conversion, price adjustments and a shared price resolver are
  deferred until a consumer and the required customer/tax decisions exist.
- Authorization is an admin-only `VariantPricePolicy`; no Catalog policy gains a
  pricing ability. Writes are contract-backed; Filament never writes models
  directly.
- Administration is a standalone `VariantPriceResource` discovered from
  `app/Modules/Pricing/Filament/Resources`, not a Catalog product-page tab.

Options considered (rejected):
- Price columns on `products` or `product_variants`: rejected; violates DEC-019
  and Catalog ownership, and makes multi-currency and history awkward.
- Prices inside Inventory or a future Cart module: rejected; those modules own
  stock and basket state, not money.
- `decimal(10,2)` columns: rejected; decimal casts become strings/floats in PHP
  and invite rounding errors, and `bcmath`/`gmp` are unavailable.
- A single `price` column with one store currency: rejected for the MVP because
  explicit per-currency rows are simpler to reason about and extend than a later
  multi-currency migration.
- Adding `starts_at`/`ends_at` now: nullable windows imply a resolver and overlap
  rules that the MVP does not implement.

Consequences and migration implications:
- New additive migrations `z12_create_variant_prices_table.php` and
  `z13_create_variant_price_changes_table.php`, sorted after Catalog `z11`.
- No existing table is altered and no data backfill is required.
- Catalog, Inventory, product media and variant-keyed stock are unaffected.
- `config/pricing.php`, `module.json` and a new `PricingServiceProvider` are
  required, plus resource discovery in `AdminPanelProvider`.
- The test suite runs on SQLite; PostgreSQL `CHECK` constraints are
  driver-guarded and verified separately on a throwaway PostgreSQL database.
- No Composer dependency is added.
- Once Orders exist, order lines must snapshot amount and currency rather than
  joining live price rows.

## Decision protocol

For each new decision, record:
- ID and title.
- Status.
- Date.
- Context and problem.
- Options considered.
- Decision and rationale.
- Consequences and migration implications.

Do not silently convert proposals into confirmed decisions.
