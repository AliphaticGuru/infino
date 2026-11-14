# Task 004 — Catalog Product Variant Administration

**Status:** Complete and verified 2026-11-11 — uncommitted  
**Priority:** High  
**Branch:** `feature/catalog`  
**Base commit:** `d44f6e8` — `feat(catalog): implemented catalog variant foundation & inventory variant re-key`  
**Decision:** DEC-020 in `docs/agent/DECISIONS.md`

## Objective

Build administration for the multiple variants introduced by Task 003:

1. Retrieve all variants of a product and individual variants.
2. Create additional variants for existing products.
3. Update variant SKU, label and `variant_key` safely.
4. Activate and deactivate variants without destroying history.
5. Reposition variants within a product.
6. Expose all of it through Filament while keeping business logic in Catalog.

The default-variant invariant, global SKU uniqueness, per-product variant-key
uniqueness and the existing inventory/stock-movement relationships must be
preserved.

## Constraints

- Stay on `feature/catalog`; inspect Git state before changing files.
- Do not commit, merge, reset or discard user work.
- Preserve the modular monolith and module ownership.
- Keep business logic in Catalog, not in Filament.
- Follow the existing Actions/Contracts/Data conventions.
- Do not implement attributes/options, media, pricing, Cart, Checkout, Orders or
  unrelated abstractions.
- Do not add dependencies and do not add a migration.

## 1. Default variant invariant

- The default variant is the product's oldest variant, i.e. the lowest
  `product_variants.id`. No `is_default` column is added.
- `Product::defaultVariant()` is a `HasOne` using `oldestOfMany()`.
- `ProductVariant::isDefault()` compares the variant against its product's
  default variant.
- The convention is deterministic because `CreateProduct` and the backfill
  create the default variant first and `CreateProductVariant` always appends.

## 2. Variant retrieval

`FindsProductVariants` / `FindProductVariant`:

| Method | Behaviour |
|---|---|
| `forProduct(Product)` | All variants ordered by `position`, then `id`. Re-reads the product and throws `ModelNotFoundException` if it no longer exists. |
| `find(int)` | A variant by primary key or `ModelNotFoundException`. |
| `findForProduct(Product, int)` | A variant scoped to the product or `ModelNotFoundException` for variants owned by another product. |

## 3. Variant creation

`CreatesProductVariants` / `CreateProductVariant` / `CreateProductVariantData`:

- Input: optional `sku`, optional `label`, optional `variantKey`, `isActive`.
- Validates global SKU uniqueness and `variant_key` uniqueness per product.
- Generates a collision-safe `SKU-{productId}-{nextPosition}` when no SKU is
  supplied.
- Runs in a `DB::transaction` with the product row and existing variants locked
  (`lockForUpdate`).
- Appends the variant at `position = count(existing)` and then normalizes all
  positions to `0..n-1`.
- If the product has no variants, the created variant becomes the default:
  label `Default`, position `0`, active.
- The owning product is always the product passed to the action; it cannot be
  supplied through the data object.

## 4. Variant updating

`UpdatesProductVariants` / `UpdateProductVariant` / `UpdateProductVariantData`:

- Accepts only `sku`, `label` and `variantKey`; the owning product cannot change.
- Omitted (`null`) fields keep their stored values.
- Validates SKU uniqueness and per-product `variant_key` uniqueness while
  ignoring the current record.
- Runs in a transaction with row locking.
- Does not touch inventory rows or stock movements.

## 5. Variant lifecycle

`TransitionsProductVariantStatus` / `TransitionProductVariantStatus`:

- `activate()` rejects an already-active variant.
- `deactivate()` rejects:
  - an already-inactive variant;
  - the default variant;
  - the last active variant of the product.
- Rejections raise `ValidationException`.
- Transitions run in a transaction with row locking.
- Variants are never hard-deleted through administration. Inventory rows and
  append-only stock movements keep referencing the variant.

## 6. Variant positioning

`RepositionsProductVariants` / `RepositionProductVariants`:

- Input: the complete, duplicate-free list of the product's variant IDs.
- Rejects duplicate IDs, missing IDs and IDs belonging to another product with
  `ValidationException`.
- Runs in a transaction with the product and variants locked.
- Assigns positions `0..n-1` in the supplied order.
- Never changes another product's variants.

## 7. Authorization

`ProductVariantPolicy`:

- Admins may `viewAny`, `view`, `create`, `update`, `activate`, `deactivate` and
  `reorder`.
- Non-admins are denied every ability.
- `delete` and `deleteAny` are denied for everyone because variants are
  deactivated rather than removed.

Registered with `Gate::policy(ProductVariant::class, ProductVariantPolicy::class)`
in `CatalogServiceProvider::bootModule()`.

## 8. Filament administration

- `VariantsRelationManager` is registered on `ProductResource::getRelations()`.
- `ProductVariantsTable` lists SKU, label, variant key, status, position and
  creation date.
- `CreateAction`, `EditAction`, `activate` and `deactivate` delegate to the
  Catalog contracts through `using()` / `action()` callbacks.
- Deactivation is hidden for the default variant and for variants without
  another active sibling.
- Drag reordering writes Filament's order, then `RepositionsProductVariants`
  normalizes positions.
- `ProductVariantForm` exposes SKU, label and variant key. SKU is required on
  create and uniqueness ignores the current record.
- `ProductForm` reads the default-variant SKU from `Product::defaultVariant()`.

## 9. Tests added

- `src/tests/Feature/Catalog/ProductVariantAdministrationTest.php` — 27 tests,
  95 assertions:
  contract resolution; retrieval and product scoping; creation; multiple
  variants; generated SKUs; SKU and variant-key uniqueness; transaction rollback;
  updates; inventory/movement preservation; activation/deactivation; default and
  last-active protection; repositioning; policy authorization.
- `src/tests/Feature/Catalog/Filament/ProductVariantResourceTest.php` — 9 tests,
  44 assertions:
  listing; creation; validation; editing; activation/deactivation; default
  deactivation protection; relation-manager visibility; HTTP access.

## 10. Verification results

| Suite | Result |
|---|---|
| `tests/Feature/Catalog` | 180 passed (570 assertions) |
| `tests/Feature/Inventory` | 52 passed (174 assertions) |
| `ProductVariantAdministrationTest.php` | 27 passed (95 assertions) |
| `ProductVariantResourceTest.php` | 9 passed (44 assertions) |
| Catalog Filament suite | 45 passed (179 assertions) |
| Full suite | 263 passed, 3 failed (825 assertions) |

Full-suite failures are pre-existing and unrelated:

- `Tests\Feature\Auth\AuthenticationTest`
- `Tests\Feature\Auth\TwoFactorChallengeTest`
- `Tests\Feature\PostCreatePageTest`

Static analysis:

- Pint on changed files: passed.
- PHPStan on `app/Modules/Catalog`: no errors.
- PHPStan on `app/Modules/Inventory`: no errors.
- Full-project PHPStan: 21 pre-existing errors outside Catalog/Inventory.
- `git diff --check`: clean.

## 11. Files changed

Created:
- `src/app/Modules/Catalog/Contracts/FindsProductVariants.php`
- `src/app/Modules/Catalog/Contracts/CreatesProductVariants.php`
- `src/app/Modules/Catalog/Contracts/UpdatesProductVariants.php`
- `src/app/Modules/Catalog/Contracts/TransitionsProductVariantStatus.php`
- `src/app/Modules/Catalog/Contracts/RepositionsProductVariants.php`
- `src/app/Modules/Catalog/Actions/FindProductVariant.php`
- `src/app/Modules/Catalog/Actions/CreateProductVariant.php`
- `src/app/Modules/Catalog/Actions/UpdateProductVariant.php`
- `src/app/Modules/Catalog/Actions/TransitionProductVariantStatus.php`
- `src/app/Modules/Catalog/Actions/RepositionProductVariants.php`
- `src/app/Modules/Catalog/Data/CreateProductVariantData.php`
- `src/app/Modules/Catalog/Data/UpdateProductVariantData.php`
- `src/app/Modules/Catalog/Policies/ProductVariantPolicy.php`
- `src/app/Modules/Catalog/Filament/Resources/Products/RelationManagers/VariantsRelationManager.php`
- `src/app/Modules/Catalog/Filament/Resources/Products/Schemas/ProductVariantForm.php`
- `src/app/Modules/Catalog/Filament/Resources/Products/Tables/ProductVariantsTable.php`
- `src/tests/Feature/Catalog/ProductVariantAdministrationTest.php`
- `src/tests/Feature/Catalog/Filament/ProductVariantResourceTest.php`
- `docs/agent/tasks/active/004-catalog-product-variant-administration.md`

Modified:
- `src/app/Modules/Catalog/Models/Product.php`
- `src/app/Modules/Catalog/Models/ProductVariant.php`
- `src/app/Modules/Catalog/Providers/CatalogServiceProvider.php`
- `src/app/Modules/Catalog/Filament/Resources/Products/ProductResource.php`
- `src/app/Modules/Catalog/Filament/Resources/Products/Schemas/ProductForm.php`
- `src/app/Modules/Catalog/README.md`
- `docs/agent/DECISIONS.md`
- `docs/agent/modules/CATALOG.md`
- `docs/agent/PROJECT_STATE.md`
- `docs/agent/ROADMAP.md`
- `docs/agent/WORK_LOG.md`
- `docs/agent/HANDOFF.md`

## 12. Outstanding issues

- `UpdateProductVariant` treats `null` as "not provided", so the form cannot clear
  a label or variant key.
- Variant hard deletion is not exposed; variants are deactivated instead.
- If a future workflow needs to promote another variant to default, the
  oldest-id convention must be replaced by an explicit marker.
- Attributes/options, media and pricing remain future phases.
- The 3 pre-existing full-suite failures remain.
- Full-project PHPStan still reports 21 pre-existing errors outside
  Catalog/Inventory.
- No CI job runs PostgreSQL `migrate:fresh`.

## 13. Next recommended action

Implement attributes/options on top of the variant foundation, then media and the
Pricing module. Add a PostgreSQL `migrate:fresh` CI check before the schema grows
further.
