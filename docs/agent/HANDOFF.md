### Pricing Module — Task 007A Implementation Status

**Completed (Task 007A):**
- `src/app/Modules/Pricing/module.json` — Module manifest with name, description, version, enabled flag, providers, and dependencies on Core and Catalog.
- `src/app/Modules/Pricing/Providers/PricingServiceProvider.php` — Service provider registering `FindsVariantPrices`, `SetsVariantPrice`, `RemovesVariantPrice` contracts; `VariantPricePolicy`; and pricing config merging.
- `src/config/pricing.php` — Configuration with `default_currency: NGN`, `allowed_currencies: [NGN, USD, EUR, GBP]`, and `exponents` map (2 decimal places each). Extensible via config changes; no code changes required to add new currencies.
- `src/app/Modules/Pricing/Models/VariantPrice.php` — Eloquent model with `belongsTo(ProductVariant)`, `hasMany(VariantPriceChange)` relationships; `casts()` for `variant_id`, `currency`, `amount_minor`, `compare_at_amount_minor`; `newFactory()` returning `VariantPriceFactory`; `init()`-ready for Money exponent map.
- `src/app/Modules/Pricing/Models/VariantPriceChange.php` — Append-only ledger model with `belongsTo(ProductVariant)`, `belongsTo(User)` via nullable `changed_by`; `casts()` for all fields; no `updated_at`; `newFactory()` returning `VariantPriceChangeFactory`.
- `src/app/Modules/Pricing/Support/Money.php` — Integer-safe monetary value object with `parse()`, `format()`, `compare()`, `add()`, `subtract()` using currency-specific exponents from config. Rejects: unknown currencies, negative amounts, precision beyond exponent, invalid numeric input. Allows zero-value prices.
- `src/app/Modules/Pricing/Database/Factories/VariantPriceFactory.php` — Factory with states: `ngn()`, `usd()`, `eur()`, `gbp()`, `withAmount()`, `withCompareAt()`, `forVariant()`.
- `src/app/Modules/Pricing/Database/Factories/VariantPriceChangeFactory.php` — Factory with states: `created()`, `updated()`, `removed()`, `withChangedBy()`.
- `src/app/Modules/Pricing/Database/Migrations/z12_create_variant_prices_table.php` — Creates `variant_prices` table: `id`, `variant_id`, `currency` (char(3)), `amount_minor` (unsigned bigint), `compare_at_amount_minor` (nullable unsigned bigint), timestamps. Foreign key to `product_variants` with cascade on delete. Unique constraint on `(variant_id, currency)`.
- `src/app/Modules/Pricing/Database/Migrations/z13_create_variant_price_changes_table.php` — Creates `variant_price_changes` table: `id`, `variant_id`, `currency`, `change_type` (string 20), `old_amount_minor`, `new_amount_minor`, `old_compare_at_amount_minor`, `new_compare_at_amount_minor`, `changed_by` (nullable bigint), `created_at` (useCurrent). No `updated_at`. Foreign key to `product_variants` cascade, to `users` set null. Indexes on `(variant_id, created_at)` and `(currency, created_at)`.
- `src/app/Modules/Pricing/Policies/VariantPricePolicy.php` — Admin-only policy: `viewAny`, `view`, `create`, `update`, `delete` all return `$user->is_admin`; `deleteAny` same.
- `src/tests/Feature/Pricing/PricingFoundationTest.php` — Focused Pest tests covering: Money parse/format for NGN/USD, unknown currency rejection, negative amount rejection, precision beyond exponent rejection, valid USD/NGN parsing, format zero/non-zero, compare, model relationships (variant price belongs to variant, has many changes), change types (created/updated/removed), unique variant/currency constraint, foreign-key behavior, ledger immutability (no updated_at), policy authorization.

**Infrastructure verified:**
- Migrations `z12` and `z13` run successfully via `docker compose run --rm --no-deps cli php artisan migrate --force`.
- All existing module suites remain green (Catalog 337 passed, Inventory 52 passed, full suite 420 passed with 3 pre-existing failures).
- Pint passes on touched files.
- Module loading: Pricing discovered through `ModuleRepository::all()` and its provider registered by `ModuleManager::load()`.

**Completed (Task 007B):**
- `src/app/Modules/Pricing/Contracts/FindsVariantPrices.php` — Contract for reading variant prices with `find()`, `forVariant()`, and `forCurrency()` methods.
- `src/app/Modules/Pricing/Contracts/SetsVariantPrice.php` — Contract for creating/updating a variant's price with `handle()` method validating currency, amounts, and compare-at strict greater-than.
- `src/app/Modules/Pricing/Contracts/RemovesVariantPrice.php` — Contract for removing a variant's current price with `handle()` method recording history before deletion.
- `src/app/Modules/Pricing/Actions/FindVariantPrices.php` — Action retrieving current prices without modifying state.
- `src/app/Modules/Pricing/Actions/SetVariantPrice.php` — Action creating or updating a variant's price with currency validation, amount validation, compare-at strict greater-than enforcement, variant row locking, upsert with unique (variant_id, currency) constraint, and history event recording in the same transaction.
- `src/app/Modules/Pricing/Actions/RemoveVariantPrice.php` — Action removing a variant's current price with variant/price row locking, history event recording before deletion, and preservation of historical records.
- `src/app/Modules/Pricing/Data/SetVariantPriceData.php` — Data object for setting a variant price.
- `src/app/Modules/Pricing/Providers/PricingServiceProvider.php` — Updated with all three contract-to-action bindings registered.
- `src/tests/Feature/Pricing/PricingActionsTest.php` — 16 Pest tests covering contract resolution, price creation, multi-currency storage, retrieval, currency rejection, negative/zero amount rejection, compare-at strict-greater-than validation, history recording on create/update/remove, history preservation after removal, variant price isolation, and model relationships.

**Completed (Task 007C):**
- `src/app/Modules/Pricing/Filament/Resources/VariantPriceResource.php` — Resource with form, table, pages (create/edit/list), infolet, and policy registration.
- `src/app/Modules/Pricing/Filament/Resources/VariantPrice/Tables/VariantPricesTable.php` — Table with currency filter, amount formatting via Money, compare-at column, history icon, and admin-only action buttons (edit/delete gated by policy).
- `src/app/Modules/Pricing/Filament/Resources/VariantPrice/Pages/ListVariantPrices.php` — List page with header create action and currency-filterable table.
- `src/app/Modules/Pricing/Filament/Resources/VariantPrice/Pages/CreateVariantPrice.php` — Create page with VariantPriceForm schema.
- `src/app/Modules/Pricing/Filament/Resources/VariantPrice/Pages/EditVariantPrice.php` — Edit page with VariantPriceForm schema.
- `src/app/Modules/Pricing/Filament/Resources/VariantPrice/Schemas/VariantPriceForm.php` — Form schema with variant select, currency select, amount_minor, compare_at_amount_minor, and changed_by fields.
- `src/app/Providers/Filament/AdminPanelProvider.php` — Added `->discoverResources(in: app_path('Modules/Pricing/Filament/Resources'), for: 'App\Modules\Pricing\Filament\Resources')`.

**Outstanding:**
- Update DEC-023 status from Proposed to Accepted after 007C/D completion cycle.

**Documentation updated:**
- `docs/agent/PROJECT_STATE.md` — Pricing section updated with Tasks 007A–007C details.
- `docs/agent/ROADMAP.md` — Task 007C implemented; next steps noted.
- `docs/agent/tasks/active/007-pricing-module.md` — Status updated to 007A–007C implemented.
- `docs/agent/modules/PRICING.md` — Status updated to Implemented.
- `docs/agent/HANDOFF.md` — This update.

**Documentation updated:**
- `docs/agent/PROJECT_STATE.md` — Pricing section updated with Task 007A and 007B details, test results, and implementation facts.
- `docs/agent/ROADMAP.md` — Task 007A marked implemented; Task 007B implemented; DEC-023 Proposed; next steps noted.
- `docs/agent/DECISIONS.md` — DEC-023 remains Proposed; no changes needed until 007C.

**Next recommended action:** Review and approve Task 007B (pricing actions/contracts), then proceed to Task 007C (Filament administration).
