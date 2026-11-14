# Task 007 — Pricing Module

- Status: 007A–007C implemented; 007D pending
- 007C: Pricing Filament administration layer with variant price create/edit/list, currency filtering, policy enforcement, and history preservation
- Priority: High
- Depends on: [006-product-media-and-galleries.md](006-product-media-and-galleries.md)
- Related: [003-catalog-module.md](003-catalog-module.md), [004-inventory-module.md](004-inventory-module.md), [005-catalog-attribute-system.md](005-catalog-product-attributes-and-options.md)

## Objective

Design the first Pricing module slice so products and variants can carry current
sellable prices without embedding money logic in Catalog, Inventory or future
Cart/Order code.

This task is architecture-first. It defines the ownership boundary, storage
model, money handling, lifecycle/history rules and implementation phases. It
does not implement migrations, models, actions, UI, checkout pricing or discount
logic.

## Current Baseline

- Branch: `feature/pricing`.
- Task 006 (`product_media`) is implemented and committed as `4faff01`.
- Catalog owns products, variants, categories, attributes, options and media.
- Inventory owns per-variant stock rows and append-only stock movements keyed by
  `product_variant_id`.
- **Task 007A foundation implemented**: module scaffold, config, migrations,
  models, Money value object, factories, policy, and Pest foundation tests.
- No `Pricing` module code, migration, configuration or Filament resource existed
  before Task 007A. After Task 007A implementation:
  - `src/app/Modules/Pricing/module.json` — Module manifest
  - `src/app/Modules/Pricing/Providers/PricingServiceProvider.php` — Service
    provider registering contracts, actions, policy and config
  - `src/config/pricing.php` — Configuration with default currency `NGN`, allowed
    currencies `NGN`, `USD`, `EUR`, `GBP`, and exponent map
  - `src/app/Modules/Pricing/Models/VariantPrice.php` — Eloquent model with
    `belongsTo(ProductVariant)`, `hasMany(VariantPriceChange)` relationships
  - `src/app/Modules/Pricing/Models/VariantPriceChange.php` — Append-only ledger
    model with `belongsTo(ProductVariant)`, `belongsTo(User)` (nullable
    `changed_by`); no `updated_at`
  - `src/app/Modules/Pricing/Support/Money.php` — Integer-safe monetary value
    object with `parse()`, `format()`, `compare()`, `add()`, `subtract()` using
    currency-specific exponents; rejects unknown currencies, negative amounts,
    precision beyond exponent, invalid numeric input; allows zero-value prices
  - `src/app/Modules/Pricing/Database/Factories/VariantPriceFactory.php` and
    `VariantPriceChangeFactory.php` — Factory states for each currency and change
    type
  - `src/app/Modules/Pricing/Database/Migrations/z12_create_variant_prices_table.php`
    and `z13_create_variant_price_changes_table.php` — Additive migrations
    creating `variant_prices` and `variant_price_changes` tables with proper
    constraints, foreign keys, and indexes
  - `src/app/Modules/Pricing/Policies/VariantPricePolicy.php` — Admin-only
    policy using existing `$user->is_admin` convention
  - `src/tests/Feature/Pricing/PricingFoundationTest.php` — Focused Pest tests
    covering Money parse/format, currency exponent handling, invalid currency and
    precision rejection, negative and zero values, compare-at validation, model
    relationships, factory behavior, unique variant/currency constraint,
    foreign-key behavior, ledger immutability expectations, and policy
    authorization
- The runtime has no BCMath/GMP extension and no installed money library;
  money handling uses integer minor units with explicit currency scale.
- Pricing depends on Core and Catalog only. Catalog and Inventory must not
  depend on Pricing.

## Scope

### In scope (007A implemented)

- Variant-level base price as the canonical purchasable price.
- Explicit currency storage (`ISO 4217`, 3 letters).
- Integer minor-unit amounts only (`amount_minor`, not floats/decimals in code).
- Optional compare-at/list amount in the same currency.
- Append-only price change history for auditability.
- Catalog/Pricing boundary and contract surface.
- Migration, rollback and verification strategy for the first implementation.

### Out of scope (deferred to 007B–007D)

- Cart, checkout and order pricing snapshots.
- Promotions, coupons, discount rules and campaign engines.
- Tax classes, VAT/GST calculation and tax-inclusive/exclusive display rules.
- Customer-group, channel, market or region-specific price lists.
- Tiered, bulk, subscription or recurring pricing.
- Currency conversion, exchange rates and multi-currency checkout.
- Cost price, margin, supplier pricing and purchase orders.
- Shipping and payment fees.
- Marketplace/merchant-specific pricing.
- Search/index integration for price facets.
- Historical price restoration UI.
- Set/Remove/Find pricing actions or contracts (Task 007B).
- Filament resources or pages (Task 007C).
- Catalog price fields or relationships that introduce reverse module
  dependencies.
- Inventory modifications.
- Cart, Checkout, Order, tax, promotions, discounts, or storefront logic.
- Currency conversion or exchange-rate services.
- Price schedules or customer-specific price lists.

## Architecture And Ownership

Pricing is a dedicated module: `App\Modules\Pricing`.

Ownership rules:

- **Catalog** owns product and variant identity. It must not store price
  columns, price relations or price formatting logic.
- **Inventory** owns stock quantity and movement history. It must not depend on
  Pricing.
- **Pricing** owns current prices and price history for a variant/currency.
- **Future Cart/Order modules** will consume Pricing through read contracts and
  must copy the resolved price into their own immutable line snapshots at
  purchase time. Orders must never recalculate from mutable catalog prices.
- **Core** may host generic money formatting helpers only if multiple modules
  need them; business rules and persistence stay in Pricing.

Dependency direction:

```
Core <- Catalog <- Inventory
Core <- Catalog <- Pricing
Pricing -X-> Catalog/Inventory internals
Inventory -X-> Pricing
```

Pricing depends on Catalog only for the variant foreign key and read-only
variant lookup. It must not edit products or variants.

## Proposed Domain Model

### 1. `variant_prices` (current state)

Purpose: one current sellable price per variant and currency.

| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `variant_id` | FK `product_variants` | cascade on delete |
| `currency` | char(3) | ISO 4217 uppercase, e.g. `USD` |
| `amount_minor` | bigint unsigned | canonical price in minor units |
| `compare_at_amount_minor` | bigint unsigned nullable | must be > `amount_minor` when present |
| `created_at`, `updated_at` | timestamps | |

Constraints and indexes:

- Unique `(variant_id, currency)` for the MVP. Multi-price lists are a future
  explicit extension, not an overloaded row.
- Index `(currency, variant_id)` for currency-scoped reads.
- PostgreSQL partial/check constraints where supported:
  `amount_minor >= 0` and
  `(compare_at_amount_minor is null or compare_at_amount_minor >= amount_minor)`.

Rationale: Cart/Order needs one deterministic "current" price per currency.
Keeping current state separate from history avoids scanning or sorting history
rows during normal reads.

### 2. `variant_price_changes` (append-only history)

Purpose: immutable audit trail for base price changes and removals.

| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `variant_id` | bigint FK `product_variants` | cascade on delete |
| `currency` | char(3) | |
| `type` | string | `created`, `updated`, `removed` |
| `old_amount_minor` | bigint nullable | null for `created` |
| `new_amount_minor` | bigint nullable | null for `removed` |
| `old_compare_at_amount_minor` | bigint nullable | |
| `new_compare_at_amount_minor` | bigint nullable | |
| `changed_by` | FK `users` nullable, null on user delete | actor snapshot |
| `created_at` | timestamp | immutable event time |

No `updated_at` column: rows are never updated.

This is intentionally simpler than Inventory's reason/notes-before/after
movement ledger: price changes do not represent stock flow and do not need
before/after quantity snapshots. It is still sufficient for audit and future
"price changed" displays.

## Money representation

- Store amounts as integer minor units (`amount_minor`). Example: USD `12.34` is
  `1234`; JPY `1200` is `1200`.
- Use a configured currency exponent map instead of assuming 2 decimal places.
  The default allowlist should include common exponents (0, 2, 3) and reject
  unknown currencies until explicitly configured.
- Parse and format through a small `Money` support/value object or helper inside
  the Pricing module. Accept only digit strings with an optional fractional
  separator up to the currency exponent; reject float input.
- Database columns are the source of truth. Never persist formatted strings.

## Read/write model

- Writes go through actions and contracts (Task 007B):
  - `SetsVariantPrice` creates or updates the current price for a variant and
    currency, records a `created` or `updated` history row, and returns the
    `VariantPrice`.
  - `RemovesVariantPrice` records a `removed` history row and deletes the
    current price row.
  - `FindsVariantPrices` reads current prices by variant/product/currency for
    admin and future storefront consumers.
- Actions run in transactions and lock the affected variant/price row for the
  write decision. Multi-currency updates for one variant are separate calls in
  MVP unless a batch contract becomes necessary.
- Read models are simple: a variant has many current prices, and a variant has
  many price changes. Future Cart/Order code should snapshot amount, currency and
  source price id/change metadata at purchase time; it must not recalculate from
  mutable current prices.

## Filament administration plan

- Register a `VariantPriceResource` under a `Pricing` navigation group.
- Do not add a Prices relation manager to `ProductResource` in this task. Product
  pages already host variants, attributes and media; cross-module coupling is a
  better fit after the Pricing contracts stabilize.
- Resource fields: product/variant selector, currency, amount, compare-at amount.
- Amount inputs should accept major-unit decimal strings and convert through the
  money helper before calling the action.
- Editing changes only mutable price data and must create a history entry.
- Deleting is allowed only if the policy permits and is recorded as a `removed`
  history event before removal.
- Listing should show variant SKU/product, currency, amount, compare-at amount and
  updated timestamp. A read-only history relation manager is optional follow-up
  work.

## Risks and trade-offs

- **No scheduled promotions:** prices are current-state only; campaigns need a
  later design.
- **No tax semantics:** tax-inclusive/exclusive behavior must be decided before
  checkout totals.
- **No multi-price selection rules:** multiple currencies are stored, but market
  selection is deferred.
- **Ledger depends on variant existence:** variant hard-delete cascades history
  away; this matches current inventory behavior but may need revisit if legal
  audit requirements grow.
- **Catalog write compatibility:** if direct Product/Variant writes must
  atomically set a price later, Pricing should expose an application contract
  rather than letting Catalog write pricing tables.
- **SQLite test parity:** PostgreSQL CHECK constraints cannot be fully asserted
  under SQLite; keep validation authoritative in actions and add a PostgreSQL
  smoke check in CI later if needed.

## Implementation sequence

1. **007A — Foundation:** module skeleton, config, money helper, migrations,
   models, factories, policy, provider bindings and schema/model tests. **DONE**.
2. **007B — Domain actions:** DTOs, contracts, create/update/remove actions,
   ledger recording, validation and concurrency-sensitive tests.
3. **007C — Filament admin:** resource/pages/form/table using contracts only,
   admin authorization tests.
4. **007D — Hardening:** PostgreSQL constraint verification, documentation
   updates, and follow-up issue creation for price lists/promotions if needed.

## Acceptance criteria for Task 007

- Pricing has a documented boundary, schema and action contract before code is
  merged.
- Each variant can have at most one current price per currency, stored in minor
  units.
- Price create/update/remove operations are auditable through an append-only
  history.
- Catalog and Inventory remain free of pricing business logic.
- Authorization, validation, migration rollback and negative-path tests cover
  the MVP behavior.
- `docs/agent/PROJECT_STATE.md`, `docs/agent/ROADMAP.md`,
  `docs/agent/DECISIONS.md` and `docs/agent/HANDOFF.md` are updated after each
  completed implementation slice.

## Open questions

- Which currencies are enabled for the first store launch? (NGN, USD, EUR, GBP
  implemented in 007A; config extensible)
- Should compare-at price be part of the MVP or deferred? (included in 007A,
  must be strictly greater than base price)
- Is deleting a price allowed, or should prices only be superseded by updates?
  (allowed in 007A, recorded as `removed` ledger event)
- Which actor/source fields are required for price history in admin and import
  flows? (changed_by nullable FK to users, set null on delete)
- When a variant has no price, should storefront/cart APIs treat it as hidden,
  unavailable, or explicitly unpurchasable? (Cart/Storefront decision, deferred)

## Documentation status

- `docs/agent/PROJECT_STATE.md` — updated with Task 007A implementation facts
- `docs/agent/ROADMAP.md` — Task 007A marked implemented
- `docs/agent/HANDOFF.md` — updated with implementation details and next step
- `docs/agent/DECISIONS.md` — DEC-023 remains Proposed; no changes needed until
  007B completion
- `docs/agent/modules/PRICING.md` — module specification already describes the
  proposed boundary; no changes needed for 007A
- `docs/agent/tasks/active/007-pricing-module.md` — this file, updated to reflect
  007A implementation status