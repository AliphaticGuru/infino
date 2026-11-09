# Catalog Module Specification

**Status:** Product and category foundations complete and verified
**Location:** `src/app/Modules/Catalog/`

## Responsibility

Catalog owns product information and product organization.

It should not own inventory quantities, cart state, order lifecycle or payment transactions.

## Current observed implementation

Product:
- `Actions/CreateProduct.php`
- `Contracts/CreatesProducts.php`
- `Data/CreateProductData.php`
- `Models/Product.php`
- `Database/Factories/ProductFactory.php`
- `Database/Migrations/create_products_table.php`

Category:
- `Models/Category.php`
- `Database/Factories/CategoryFactory.php`
- `Database/Migrations/create_categories_table.php`
- `Database/Migrations/create_z_category_product_table.php`
- `Actions/CreateCategory.php`
- `Actions/UpdateCategory.php`
- `Contracts/CreatesCategories.php`
- `Contracts/UpdatesCategories.php`
- `Data/CreateCategoryData.php`
- `Data/UpdateCategoryData.php`
- `Support/UniqueSlug.php`

The service provider binds product and category contracts to their actions.

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
- Validates a required string name of at most 255 characters.
- Accepts an optional nullable string description.
- Generates a collision-safe slug from the product name (`name`, `name-2`, `name-3`, ...).
- Falls back to the slug `product` when the name has no slug characters.
- Creates an active product.
- Returns the created Product model.

Verified on 2026-11-09 with `src/tests/Feature/Catalog/ProductCreationTest.php`:
- Products table schema and module migration execution.
- Module discovery, provider registration and contract binding.
- Product factory resolution and record persistence.
- Product creation, persistence and optional description handling.
- Duplicate names receive unique slugs.
- Validation rejects empty and over-length names.

## Current category data

The `categories` migration defines:
- `id`
- nullable self-referencing `parent_id` with `nullOnDelete`
- `name`
- unique `slug`
- nullable `description`
- `is_active`
- timestamps

The `category_product` pivot defines:
- `category_id` and `product_id`
- composite primary key
- cascading deletes from both sides

Category behavior:
- `CreateCategory` validates name, description, parent existence and active state.
- `UpdateCategory` validates the same fields, reassigns parents and rejects self or descendant parents.
- Omitted optional update fields (`description`, `parentId`, `isActive`) preserve their stored values instead of being reset.
- Slugs are collision-safe (`name`, `name-2`, `name-3`, ...) and fall back to `category`.
- Renaming regenerates the slug; an unchanged name preserves the existing slug.
- `Category` exposes `parent()`, `children()` and `products()`.
- `Product` exposes `categories()`.

Verified on 2026-11-09 with `src/tests/Feature/Catalog/CategoryFoundationTest.php`:
- Categories and pivot schemas.
- Module contract bindings.
- Factory resolution and persistence.
- Creation, optional defaults, validation and slug collisions.
- Parent-child and descendant relationships.
- Product-category attach/detach behavior.
- Update, parent reassignment, cycle rejection and rename slug collisions.
- Parent deletion promotes children to root categories.

## Proposed future capabilities

These are not yet implemented or approved as final scope:
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
4. ~~Product creation validation?~~ Name is required, string and at most 255 characters; description is optional.
5. ~~How should duplicate slugs be handled?~~ Duplicate names receive numeric suffixes.
6. ~~Are categories and product-category relationships supported?~~ Verified.
7. What product fields are required for the first release?
8. Are products variant-based from the beginning?
9. Should category deletion be restricted while products are assigned?

## Acceptance criteria for the initial Catalog foundation

- Product and category migrations execute through the project's normal migration workflow.
- Product and category factories can create valid records.
- Product and category actions persist the expected fields.
- Slug uniqueness behavior is defined and tested.
- Parent-child and product-category relationships are defined and tested.
- Relevant Catalog tests pass.
- Catalog does not take ownership of stock, orders or payments.
