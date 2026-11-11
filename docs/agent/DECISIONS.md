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
