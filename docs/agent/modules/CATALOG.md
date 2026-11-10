# Catalog Module Specification

**Status:** Product and category foundations complete; retrieval, update, publication lifecycle, product-category assignment and category retrieval/deletion verified
**Location:** `src/app/Modules/Catalog/`

## Responsibility

Catalog owns product information and product organization.

It should not own inventory quantities, cart state, order lifecycle or payment transactions.

## Current observed implementation

Product:
- `Actions/CreateProduct.php`
- `Actions/FindProduct.php`
- `Actions/UpdateProduct.php`
- `Actions/TransitionProductStatus.php`
- `Actions/AssignProductCategories.php`
- `Actions/RemoveProductCategories.php`
- `Actions/SyncProductCategories.php`
- `Contracts/CreatesProducts.php`
- `Contracts/FindsProducts.php`
- `Contracts/UpdatesProducts.php`
- `Contracts/TransitionsProductStatus.php`
- `Contracts/AssignsProductCategories.php`
- `Contracts/RemovesProductCategories.php`
- `Contracts/SyncsProductCategories.php`
- `Data/CreateProductData.php`
- `Data/UpdateProductData.php`
- `Models/Product.php`
- `Support/ProductStatus.php`
- `Database/Factories/ProductFactory.php`
- `Database/Migrations/create_products_table.php`
- `Database/Migrations/z_add_status_to_products_table.php`

Category:
- `Models/Category.php`
- `Database/Factories/CategoryFactory.php`
- `Database/Migrations/create_categories_table.php`
- `Database/Migrations/create_z_category_product_table.php`
- `Actions/CreateCategory.php`
- `Actions/UpdateCategory.php`
- `Actions/FindCategory.php`
- `Actions/DeleteCategory.php`
- `Contracts/CreatesCategories.php`
- `Contracts/UpdatesCategories.php`
- `Contracts/FindsCategories.php`
- `Contracts/DeletesCategories.php`
- `Data/CreateCategoryData.php`
- `Data/UpdateCategoryData.php`
- `Support/UniqueSlug.php`

Inventory:
- `Models/Inventory.php`
- `Models/StockMovement.php`
- `Database/Factories/InventoryFactory.php`
- `Database/Factories/StockMovementFactory.php`
- `Database/Migrations/create_inventories_table.php`
- `Database/Migrations/create_stock_movements_table.php`
- `Actions/CreateInventory.php`
- `Actions/AdjustStock.php`
- `Contracts/CreatesInventory.php`
- `Contracts/AdjustsStock.php`
- `Data/CreateInventoryData.php`
- `Data/AdjustStockData.php`

The service provider binds product, category and inventory contracts to their actions.

The module manifest declares dependencies on Core and Catalog (Inventory).

## Current product data

The migration defines:
- `id`
- `name`
- `slug`
- nullable `description`
- `is_active`
- timestamps

The `z_add_status_to_products_table` migration adds:
- `status` string, non-null, default `draft`
- Existing rows are backfilled to `draft` by the column default.

`is_active` represents operational availability. `status` represents the
publication lifecycle; the two are independent.

The action currently:
- Accepts `CreateProductData`.
- Validates a required string name of at most 255 characters.
- Accepts an optional nullable string description.
- Generates a collision-safe slug from the product name via `Support\UniqueSlug` (`name`, `name-2`, `name-3`, ...).
- Falls back to the slug `product` when the name has no slug characters.
- Creates an active product with `draft` publication status.
- Returns the created Product model.

Verified on 2026-11-09 with `src/tests/Feature/Catalog/ProductCreationTest.php`:
- Products table schema and module migration execution.
- Module discovery, provider registration and contract binding.
- Product factory resolution and record persistence.
- Product creation, persistence and optional description handling.
- Duplicate names receive unique slugs.
- Validation rejects empty and over-length names.

## Product retrieval and update

Retrieval (`FindsProducts` / `FindProduct`):
- `find(int $id)` returns the product or throws `ModelNotFoundException`.
- `findBySlug(string $slug)` returns the product or throws `ModelNotFoundException`.

Update (`UpdatesProducts` / `UpdateProduct` / `UpdateProductData`):
- All fields are optional, so partial updates are supported.
- Omitted `name`, `description` and `isActive` keep their stored values.
- Supplied names are validated as required, string and at most 255 characters.
- A name change regenerates a collision-safe slug via `Support\UniqueSlug`,
  ignoring the product's own row; an unchanged name keeps the existing slug.
- Description and active status update independently of the name.

`Product` casts `is_active` to boolean and exposes typed property and relation
annotations for static analysis.

Verified on 2026-11-10 with `src/tests/Feature/Catalog/ProductRetrievalAndUpdateTest.php`:
- Contract resolution through the module provider.
- Retrieval by ID and by slug.
- `ModelNotFoundException` for unknown IDs and slugs.
- Full and partial updates, including no-op updates.
- Omitted fields preserved; other products unaffected.
- Rename slug regeneration, unchanged-name slug preservation and collision suffixes.
- Validation rejects empty and over-length names without modifying the product.

## Product publication lifecycle

Status is represented by the `Support\ProductStatus` backed enum:

- `Draft` (`draft`)
- `Published` (`published`)
- `Archived` (`archived`)

`TransitionsProductStatus` / `TransitionProductStatus` exposes:

- `publish(Product)`: draft → published.
- `unpublish(Product)`: published → draft.
- `archive(Product)`: draft or published → archived.
- `restore(Product)`: archived → draft.

Rules:

- Invalid transitions raise `ValidationException` and leave the product unchanged.
- Archived products cannot be published directly; they must be restored to
  draft first.
- Transitions run in a database transaction and resolve the product from the
  database, so deleted products raise `ModelNotFoundException`.
- Only `status` and `updated_at` change; name, slug, description and `is_active`
  are preserved.
- Publication status does not alter operational availability (`is_active`), and
  `UpdateProduct` does not alter publication status.

Verified on 2026-11-10 with `src/tests/Feature/Catalog/ProductPublicationLifecycleTest.php`:
- `status` column presence and draft default for existing/raw rows.
- Draft default on creation and factory states.
- Successful publish, unpublish, archive and restore.
- Rejection of all invalid transitions, including archived → published.
- `ModelNotFoundException` for a deleted product.
- Preservation of unrelated attributes across the full lifecycle.
- Independence of `is_active` from publication status.
- Product updates leaving status unchanged.

## Product-category assignment

The `category_product` pivot stores `category_id` and `product_id` with a
composite primary key and cascading deletes from both sides.

`AssignsProductCategories` / `AssignProductCategories`:
- Accepts a product and one or more category IDs.
- Validates every requested category exists.
- Attaches categories without removing existing assignments.
- Duplicate requests are idempotent; the pivot primary key prevents duplicates.

`RemovesProductCategories` / `RemoveProductCategories`:
- Accepts a product and one or more category IDs.
- Detaches the requested categories and preserves all others.
- IDs that are not assigned (including nonexistent categories) are ignored safely.

`SyncsProductCategories` / `SyncProductCategories`:
- Accepts a product and an array of category IDs.
- Validates every requested category before modifying assignments.
- Attaches missing categories and removes assignments not in the list.
- An empty array removes all category assignments.

Rules:
- All three operations run in a database transaction and resolve the product
  from the database, so deleted or unpersisted products raise
  `ModelNotFoundException`.
- Invalid or nonexistent category IDs raise `ValidationException` and leave
  assignments unchanged.
- Category assignment is independent of product publication status and
  `is_active`.

Verified on 2026-11-10 with `src/tests/Feature/Catalog/ProductCategoryAssignmentTest.php`:
- Contract resolution through the module provider.
- Single and multiple category assignment.
- Existing assignments preserved and duplicates prevented.
- Selected detachment with unrelated assignments preserved.
- Safe handling of unassigned and nonexistent category IDs on removal.
- Sync replacing assignments and sync with an empty array.
- Rejection of nonexistent and duplicate category IDs without partial changes.
- `ModelNotFoundException` for deleted and unpersisted products.

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

## Category retrieval and deletion

Retrieval (`FindsCategories` / `FindCategory`):
- `find(int $id)` returns the category or throws `ModelNotFoundException`.
- `paginate(int $perPage = 20, ?int $page = null)` returns a
  `LengthAwarePaginator` of categories ordered by `id`; the default page size is
  20, matching the Inventory read service convention.
- `products(Category $category, int $perPage = 20, ?int $page = null)` returns a
  `LengthAwarePaginator` of the category's products ordered by `products.id`.
- The paginated product query re-resolves the category from the database, so a
  deleted category raises `ModelNotFoundException` instead of returning an empty
  page.

Deletion (`DeletesCategories` / `DeleteCategory`):
- Accepts a `Category` and returns `void`.
- Runs in a database transaction and re-resolves the category from the database.
- A category with any assigned products raises `ValidationException` with the
  message `A category with assigned products cannot be deleted.`
- An unassigned category is deleted.
- Product-category relationships are never detached implicitly; a rejected
  deletion leaves the pivot rows intact.
- Deleting a parent category relies on the existing `parent_id` `nullOnDelete`
  behaviour, so children are promoted to root categories. No new migration was
  added.

Verified on 2026-11-10 with `src/tests/Feature/Catalog/CategoryRetrievalAndDeletionTest.php`:
- Contract resolution through the module provider.
- Retrieval by ID and `ModelNotFoundException` for unknown IDs.
- Pagination totals, default/custom page sizes, explicit page selection and
  `id` ordering.
- Category product pagination, empty product paginators and
  `ModelNotFoundException` for a deleted category.
- Successful deletion of an unassigned category.
- Rejection of deletion when products are assigned, with the relationship and
  product preserved.
- `ModelNotFoundException` when deleting an already-deleted category.
- Child promotion to root after parent deletion.

## Proposed future capabilities

These are not yet implemented or approved as final scope:
- Brands.
- Collections.
- Attributes and attribute values.
- Variants.
- Product media.
- SEO metadata.
- Product search metadata.

## Open technical verification

1. ~~Is the module migration path registered with Laravel?~~ Verified.
2. ~~Does the migration run successfully?~~ Verified.
3. ~~Does the Product model resolve its custom factory namespace?~~ Verified; now explicit via `Product::newFactory()`.
4. ~~Product creation validation?~~ Name is required, string and at most 255 characters; description is optional.
5. ~~How should duplicate slugs be handled?~~ Duplicate names receive numeric suffixes.
6. ~~Are categories and product-category relationships supported?~~ Verified.
7. ~~Can products be retrieved and updated?~~ Verified: find by ID/slug, partial updates and rename slug regeneration.
8. ~~What does the product publication lifecycle require beyond `is_active`?~~ Verified: separate `status` enum with guarded transitions (DEC-017).
9. ~~How are products assigned to categories?~~ Verified: assign, remove and sync actions with validation (2026-11-10).
10. ~~Can categories be retrieved and deleted safely?~~ Verified: find by ID, pagination, category products and guarded deletion (2026-11-10).
11. What product fields are required for the first release?
12. Are products variant-based from the beginning?

## Acceptance criteria for the initial Catalog foundation

- Product and category migrations execute through the project's normal migration workflow.
- Product and category factories can create valid records.
- Product and category actions persist the expected fields.
- Product publication status defaults to draft and transitions are validated and tested.
- Publication status is independent of `is_active` availability.
- Product-category assignment, removal and synchronisation are validated and tested.
- Category retrieval and deletion rules are validated and tested; assigned categories cannot be deleted.
- Slug uniqueness behavior is defined and tested.
- Parent-child and product-category relationships are defined and tested.
- Relevant Catalog tests pass.
- Catalog does not take ownership of stock, orders or payments.
