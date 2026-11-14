# Pricing Module Specification

**Status:** Implemented — 007A (foundation: contracts, actions, Money, tests, policies) 007B (not separately tracked) 007C (Filament administration layer)
**Location:** `src/app/Modules/Pricing/` (does not exist yet)
**Dependencies:** Core, Catalog
**Decision:** DEC-023 (Proposed)
**Task:** `docs/agent/tasks/active/007-pricing-module.md`

## Purpose

Pricing owns the selling price of Catalog product variants, the currency each
price is expressed in, and an append-only record of price changes.

The variant is the priceable unit (DEC-019). Prices are never stored against
products.

## Responsibilities

- Store one current base price per `(variant, currency)`.
- Store an optional compare-at (list/"was") amount in the same currency.
- Validate monetary input exactly, using integer minor units.
- Record every price creation, update and removal in an append-only ledger.
- Provide contract-backed reads and writes for other modules and the admin UI.
- Expose admin-only Filament administration.

## Non-responsibilities

Pricing does **not** own or implement:

- Product, variant, SKU, option or media data — Catalog.
- Stock quantities, availability or reservations — Inventory.
- Cart lines, checkout, order totals or order snapshots — future Cart/Order.
- Price lists, customer-specific or group pricing.
- Scheduled, sale or promotional pricing.
- Coupons, vouchers or discount rules.
- Tax calculation, tax-inclusive pricing or jurisdiction rules.
- Currency conversion or exchange rates.
- Cost, margin or supplier pricing.
- Storefront display logic.

## Boundaries

```
Catalog (owns products and variants)
   ^
   | variant_id
Pricing (owns prices and price history)

Inventory (owns stock) ---> Catalog
Cart/Order (future) ---> Pricing + Catalog + Inventory
```

Rules:

- Pricing may reference `product_variants`; Catalog must not reference Pricing.
- Catalog models do not gain price relationships.
- Inventory and Pricing are independent; availability does not change price.
- Future Cart/Order consume Pricing through read contracts and must snapshot
  amounts on order lines rather than joining live prices.
- No module may read or write `variant_prices` directly outside Pricing.

## Data model (proposed)

### `variant_prices`

| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `variant_id` | bigint FK `product_variants` | cascade on delete |
| `currency` | char(3) | uppercase ISO 4217 |
| `amount_minor` | bigint | integer minor units, non-negative |
| `compare_at_amount_minor` | bigint nullable | same currency, non-negative |
| `created_at`, `updated_at` | timestamp | |

Unique `(variant_id, currency)`. Index on `(currency)`.

PostgreSQL-only checks (driver-guarded):

- `amount_minor >= 0`
- `compare_at_amount_minor IS NULL OR compare_at_amount_minor >= 0`

### `variant_price_changes`

Append-only ledger; no `updated_at`.

| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `variant_id` | bigint FK `product_variants` | cascade on delete |
| `currency` | char(3) | |
| `change_type` | varchar(20) | `created`, `updated`, `removed` |
| `old_amount_minor` | bigint nullable | |
| `new_amount_minor` | bigint nullable | |
| `old_compare_at_amount_minor` | bigint nullable | |
| `new_compare_at_amount_minor` | bigint nullable | |
| `changed_by` | bigint FK `users` nullable | null on delete |
| `created_at` | timestamp | |

Indexes: `(variant_id, created_at)`, `(currency, created_at)`.

## Money rules

- All amounts are stored as signed 64-bit integer minor units.
- Currency exponents come from `config/pricing.php`; they are not hard-coded.
- `bcmath` and `gmp` are unavailable in the runtime, so no arbitrary-precision
  library is used and none may be added without approval.
- Major-unit input is parsed as a decimal string, never via float arithmetic.
- Excess precision is rejected, not rounded silently.
- Unsupported currencies are rejected.
- Formatting uses integer division and string padding.

## Configuration

`src/config/pricing.php`:

- `default_currency`
- `allowed_currencies` (code => exponent map)

The exponent map is the single source of truth for parsing and formatting.

## Contracts and actions (proposed)

| Contract | Action | Purpose |
|---|---|---|
| `FindsVariantPrices` | `FindVariantPrice` | Read prices for a variant or a specific `(variant, currency)` pair. |
| `SetsVariantPrice` | `SetVariantPrice` | Create or update the current price and append a ledger row. |
| `RemovesVariantPrice` | `RemoveVariantPrice` | Append a removal ledger row and delete the current price. |

`SetVariantPriceData` carries `variantId`, `currency`, `amountMinor` and an
optional `compareAtAmountMinor`. Actions return Eloquent models and throw
`ValidationException` for invalid input.

All writes run in a transaction. `SetVariantPrice` locks the variant row;
`RemoveVariantPrice` locks the price row. The ledger row is written in the same
transaction as the price mutation.

## Invariants

- At most one price row per `(variant_id, currency)`.
- Amounts are non-negative integers in minor units.
- `compare_at_amount_minor >= amount_minor` when present.
- Currency is a supported, uppercase ISO 4217 code.
- Every successful create, update and removal has exactly one ledger row.
- Ledger rows are never updated or deleted through the application.
- A price row never moves to another variant or currency after creation.

## Authorization

- `VariantPricePolicy` is admin-only for `viewAny`, `view`, `create`, `update`
  and `delete`.
- Bulk deletion is not exposed.
- No ability is added to Catalog policies.
- Actions do not call `Gate`; authorization is enforced at the Filament or
  future controller boundary, matching the Catalog and Inventory convention.
- Future non-admin read paths must use read-only contracts.

## Filament administration

- Standalone `VariantPriceResource` in the `Pricing` navigation group.
- List, create and edit pages; single audited delete; no bulk delete.
- Variant and currency are locked after creation.
- Amounts are entered as major-unit decimal strings and stored as minor units.
- All writes delegate to Pricing contracts.
- `AdminPanelProvider` discovers `app/Modules/Pricing/Filament/Resources`.
- Optional read-only ledger relation manager on the edit page.

## Migration strategy

- `z12_create_variant_prices_table.php`
- `z13_create_variant_price_changes_table.php`

Both are additive, sort after Catalog `z11`, and require no backfill or changes
to existing tables. Rollback drops the ledger first, then prices.

## Deferred capabilities

- Price lists and customer/group pricing.
- Scheduled and promotional pricing.
- Coupons, vouchers and discount rules.
- Tiered and quantity-based pricing.
- Price adjustments and surcharges.
- Tax calculation and tax-inclusive pricing.
- Currency conversion and exchange rates.
- Cost, margin and supplier pricing.
- A shared price resolver for Cart/Checkout.
- Bulk import/export.
- Storefront pricing endpoints and display components.
- A dedicated audit module or third-party auditing package.

## Testing

- Unit tests for `Support\Money` (exponents 0/2/3, precision, no float drift).
- Foundation tests for schema, constraints, casts and factories.
- Management tests for create/update/remove, validation, transactions and
  ledger consistency.
- Authorization tests for the admin-only policy.
- Filament tests for list/create/edit/delete and contract-backed writes.
- PostgreSQL `migrate:fresh`, rollback/re-apply and constraint checks on a
  throwaway database.

## Open questions

1. Default storefront currency and multi-currency scope.
2. Compare-at inclusion and its validation rule.
3. Whether price rows may be removed or only zeroed.
4. Tax-inclusive vs tax-exclusive storage.
5. Whether the MVP allows several currencies per variant.
6. Whether a ledger UI is required for MVP.
7. Actor capture for CLI/seed changes.
8. Future non-admin price visibility.
