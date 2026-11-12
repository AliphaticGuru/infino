# Task 005 — Catalog Product Attributes & Options Foundation

**Status:** Phase B complete and verified on 2026-11-12. DEC-021 implemented.
**Priority:** High
**Branch:** `feature/catalog`
**Base commit:** `cfe11b6` — `feat(catalog): implemented catalog product variant Administration`
**Decision:** DEC-021 in `docs/agent/DECISIONS.md` (status: Implemented)

## Objective

Design and implement a normalized, extensible product attribute and option
system on top of the variant-first Catalog architecture, while preserving:

- `ProductVariant` as the canonical purchasable unit.
- Inventory references to `variant_id`.
- Variant SKU uniqueness and the default-variant invariant.
- Product-scoped variant positioning.
- Unambiguous variant option combinations.
- Existing variant administration and tests.

## Phase A — Architecture proposal

### Recommended entity structure

```
ProductAttribute 1---* ProductAttributeOption
ProductAttribute *---* Product          (product_attribute_product)
Product          1---* ProductVariant
ProductVariant   1---* ProductVariantAttributeOption *---1 ProductAttribute
                                                     *---1 ProductAttributeOption
```

### Proposed tables

`product_attributes`:
- `id`
- `code` string(100), globally unique, immutable
- `name` string(255), editable
- `type` string(20), default `select` (only `select` in Task 005)
- `is_variant_dimension` boolean, default `false`
- `position` integer, default `0`
- `is_active` boolean, default `true`
- timestamps
- unique `code`; index `(is_active, position)`

`product_attribute_options`:
- `id`
- `product_attribute_id` FK, cascade
- `code` string(100), immutable, unique per attribute
- `label` string(255), editable
- `position` integer, default `0`
- `is_active` boolean, default `true`
- timestamps
- unique `(product_attribute_id, code)`
- unique `(id, product_attribute_id)` (composite-FK target)
- index `(product_attribute_id, position)`

`product_attribute_product`:
- `product_id` FK, cascade
- `product_attribute_id` FK, cascade
- composite primary key `(product_id, product_attribute_id)`
- index on `product_attribute_id`

`product_variant_attribute_options`:
- `id`
- `product_variant_id` FK, cascade
- `product_attribute_id` FK, restrict
- `product_attribute_option_id` unsigned bigint
- timestamps
- unique `(product_variant_id, product_attribute_id)`
- composite FK `(product_attribute_option_id, product_attribute_id)` to
  `product_attribute_options (id, product_attribute_id)`, restrict
- index on `product_attribute_option_id`

### Relationship definitions

- `ProductAttribute::options()` hasMany.
- `ProductAttribute::products()` belongsToMany.
- `ProductAttributeOption::attribute()` belongsTo.
- `ProductAttributeOption::variantSelections()` hasMany.
- `Product::productAttributes()` belongsToMany. Do not name it `attributes()`
  because Eloquent uses the `$attributes` property.
- `ProductVariant::attributeOptions()` hasMany to the selection model.
- `ProductVariantAttributeOption` belongsTo variant, attribute and option.

### Data integrity rules

- Attribute `code` globally unique and immutable; option `code` unique per
  attribute and immutable. Names/labels are editable.
- Only active attributes can be newly assigned; only active attributes/options
  can be newly selected.
- Product-attribute assignment is unique per `(product_id, attribute_id)`.
- A variant holds at most one option per attribute.
- The composite FK prevents an option being attached to a different attribute.
- Application validation prevents cross-product attributes and non-dimension
  attributes from being selected.
- Variant selections are complete: one active option per assigned variant
  dimension. Duplicate, unknown, inactive or cross-product selections are
  rejected with `ValidationException`.
- Definitions/options are deactivated rather than hard-deleted once referenced;
  guarded hard deletion only when unreferenced.
- All multi-row writes run transactionally with row locking.

### Relationship with `variant_key`

- Keep `product_variants.variant_key` and its per-product unique index.
- Derive it for configured variants as immutable
  `attributeCode:optionCode` pairs sorted by attribute `position` then `code`,
  joined with `|` (for example `color:red|size:m`).
- A manually supplied key must be absent or equal to the derived key.
- Variants without normalized selections keep the current optional manual
  `variant_key` behaviour.
- No backfill is required; existing values and tests remain valid.

### Proposed contracts and actions

Definitions:
- `FindsProductAttributes` / `FindProductAttribute`
- `CreatesProductAttributes` / `CreateProductAttribute`
- `UpdatesProductAttributes` / `UpdateProductAttribute`
- `DeletesProductAttributes` / `DeleteProductAttribute`
- `CreateProductAttributeData`, `UpdateProductAttributeData`

Options:
- `FindsProductAttributeOptions` / `FindProductAttributeOption`
- `CreatesProductAttributeOptions` / `CreateProductAttributeOption`
- `UpdatesProductAttributeOptions` / `UpdateProductAttributeOption`
- `DeletesProductAttributeOptions` / `DeleteProductAttributeOption`
- `CreateProductAttributeOptionData`, `UpdateProductAttributeOptionData`

Product assignment:
- `AssignsProductAttributes` / `AssignProductAttributes`
- `RemovesProductAttributes` / `RemoveProductAttributes`
- `SyncsProductAttributes` / `SyncProductAttributes`

Variant mapping:
- `SyncsVariantAttributeOptions` / `SyncVariantAttributeOptions` /
  `SyncVariantAttributeOptionsData`

Integration:
- `CreateProductVariantData` and `UpdateProductVariantData` gain an optional
  `attributeOptions` array. The existing variant actions apply the same
  validation and key derivation inside their existing transaction, preserving
  atomicity and not bypassing variant administration.

Support and authorization:
- `Support\AttributeType` (`select`), `Support\VariantKey`.
- `ProductAttributePolicy`, `ProductAttributeOptionPolicy`.
- `ProductPolicy::manageAttributes`.

### Proposed Filament structure

- `AttributeResource` (`Filament/Resources/Attributes/`) with list, create and
  edit pages.
- `OptionsRelationManager` on the attribute edit page.
- `ProductAttributesRelationManager` on the product edit page (attach/detach).
- `ProductVariantForm` option-selection component for the product's assigned
  variant dimensions (initially a `Repeater` transformed to
  `[attribute_id => option_id]`).
- All writes delegate to Catalog contracts.

### Migration plan

New untimestamped Catalog migrations:

- `z07_create_product_attributes_table.php`
- `z08_create_product_attribute_options_table.php`
- `z09_create_product_attribute_product_table.php`
- `z10_create_product_variant_attribute_options_table.php`

The `z03`–`z06` prefixes are already used by Inventory and must not be reused.
No existing table is altered and no data backfill is required.

### Required tests

- Attribute definition CRUD, code uniqueness, validation, lifecycle and policy.
- Option CRUD, code uniqueness per attribute, ownership, lifecycle and policy.
- Product-attribute assignment: assign/remove/sync, active/existing validation,
  idempotency, cross-product isolation.
- Variant option mapping: valid selection, complete-set enforcement, duplicate
  attribute rejection, option/attribute mismatch rejection, inactive option
  rejection, cross-product rejection, derived `variant_key`, duplicate
  combination rejection, transaction rollback.
- Backward compatibility: variants without options and manual `variant_key`.
- Inventory integrity: option changes do not touch inventory or movements.
- Filament: attribute resource, options relation manager, product attributes
  relation manager, variant options form and authorization.
- Regression: Catalog focused suite, Inventory focused suite, full Pest suite.
- Pint, module PHPStan, PostgreSQL `migrate:fresh`, rollback/re-apply and
  `git diff --check`.

## Resolved questions

1. `variant_key` uses immutable `attributeCode:optionCode` pairs, not option IDs.
2. Only the `select` attribute type is in scope for Task 005.
3. Product-level attribute values are out of scope; Task 005 stores assignments
   and variant option selections only.
4. Configured variants must select one option per assigned variant dimension;
   pre-existing unconfigured variants remain valid until edited. Publication
   enforcement remains a future decision.
5. Definitions/options are deactivated rather than hard-deleted once referenced,
   with guarded hard deletion only when unreferenced.

## Phase B — Implementation (complete)

Implemented on `feature/catalog` without deviating from the approved model:

- Migrations `z07_create_product_attributes_table`,
  `z08_create_product_attribute_options_table`,
  `z09_create_product_attribute_product_table` and
  `z10_create_product_variant_attribute_options_table`.
- Models `ProductAttribute`, `ProductAttributeOption`,
  `ProductVariantAttributeOption`; relationships
  `Product::productAttributes()`, `ProductVariant::attributeOptions()`.
- Factories `ProductAttributeFactory`, `ProductAttributeOptionFactory`,
  `ProductVariantAttributeOptionFactory`.
- Support enums/helpers `Support\AttributeType`, `Support\VariantKey`.
- Contracts, data objects and actions for attribute CRUD, option CRUD,
  product assignment/removal/sync, variant option sync and option repositioning.
- `CreateProductVariantData` / `UpdateProductVariantData` gained optional
  `attributeOptions`; `CreateProductVariant` / `UpdateProductVariant` apply the
  same validation and key derivation inside their existing transactions.
- `ProductAttributePolicy`, `ProductAttributeOptionPolicy` and
  `ProductPolicy::manageAttributes`; all contracts and policies registered in
  `CatalogServiceProvider`.
- Filament `AttributeResource` with `OptionsRelationManager`,
  `ProductAttributesRelationManager` on the product edit page, and dynamic
  per-dimension option selects in `ProductVariantForm` /
  `ProductVariantsTable`.

### Verification (actual results)

- New focused business tests: 53 passed (108 assertions).
- `ProductAttributeResourceTest`: 14 passed (85 assertions).
- `ProductAttributeAssignmentFilamentTest`: 5 passed (21 assertions).
- `ProductVariantAttributeOptionsFilamentTest`: 5 passed (49 assertions).
- Catalog focused suite: 265 passed (862 assertions).
- Inventory focused suite: 52 passed (174 assertions).
- Full Pest suite: 348 passed, 3 failed (1117 assertions); the three failures are
  the known pre-existing `AuthenticationTest`, `TwoFactorChallengeTest` and
  `PostCreatePageTest`.
- Pint: pass. Module PHPStan (Catalog, Inventory): no errors.
- PostgreSQL throwaway database: `migrate:fresh` succeeded; `z07`–`z10` rolled
  back and re-applied cleanly.
- `git diff --check`: clean.

### Backward compatibility

- Existing variants, manual `variant_key` values, SKUs, positions, default
  variants and Inventory rows are unaffected.
- No backfill was required.
- Unconfigured variants of dimensioned products remain valid until edited.
