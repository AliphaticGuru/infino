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
