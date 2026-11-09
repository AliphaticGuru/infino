# Catalog Module Specification

**Status:** Initial specification; product foundation verified  
**Location:** `src/app/Modules/Catalog/`

## Responsibility

Catalog owns product information and product organization.

It should not own inventory quantities, cart state, order lifecycle or payment transactions.

## Current observed implementation

- `Actions/CreateProduct.php`
- `Contracts/CreatesProducts.php`
- `Data/CreateProductData.php`
- `Models/Product.php`
- `Database/Factories/ProductFactory.php`
- `Database/Migrations/create_products_table.php`
- `Providers/CatalogServiceProvider.php`
- `module.json`

The service provider binds `CreatesProducts` to `CreateProduct`.

The module manifest declares a dependency on Core.

## Current product data

The migration defines:
- `id`
- `name`
- `slug`
- nullable `description`
- `is_active`
- timestamps

The action currently:
- Accepts `CreateProductData`.
- Generates a collision-safe slug from the product name (`name`, `name-2`, `name-3`, ...).
- Creates an active product.
- Returns the created Product model.

Verified on 2026-11-09 with `src/tests/Feature/Catalog/ProductCreationTest.php`:
- Product factory records persist.
- Module migrations execute.
- The contract binding resolves.
- Product creation persists expected fields.
- Duplicate names receive unique slugs.

## Proposed future capabilities

These are not yet implemented or approved as final scope:
- Product categories.
- Brands.
- Collections.
- Attributes and attribute values.
- Variants.
- Product media.
- SEO metadata.
- Product publication lifecycle.
- Product search metadata.

## Open technical verification

1. ~~Is the module migration path registered with Laravel?~~ Verified.
2. ~~Does the migration run successfully?~~ Verified.
3. ~~Does the Product model resolve its custom factory namespace?~~ Verified; now explicit via `Product::newFactory()`.
4. Product creation is tested; product creation validation is not yet defined.
5. ~~How should duplicate slugs be handled?~~ Duplicate names receive numeric suffixes.
6. What product fields are required for the first release?
7. Are products variant-based from the beginning?

## Acceptance criteria for the initial product foundation

- Product migration is executed through the project's normal migration workflow.
- Product factory can create valid records.
- Product creation action persists the expected fields.
- Slug uniqueness behavior is defined and tested.
- Relevant tests pass.
- Catalog does not take ownership of stock, orders or payments.
