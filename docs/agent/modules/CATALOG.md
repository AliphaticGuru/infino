# Catalog Module Specification

**Status:** Product, category, variant foundation and variant-keyed Inventory complete; retrieval, update, publication lifecycle, product-category assignment, category retrieval/deletion and the first Filament admin layer verified
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
- `Models/ProductVariant.php`
- `Support/ProductStatus.php`
- `Support/UniqueSku.php`
- `Support/DefaultVariantBackfill.php`
- `Database/Factories/ProductFactory.php`
- `Database/Factories/ProductVariantFactory.php`
- `Database/Migrations/create_products_table.php`
- `Database/Migrations/z_add_status_to_products_table.php`
- `Database/Migrations/z01_create_product_variants_table.php`
- `Database/Migrations/z02_backfill_default_product_variants.php`

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

Filament administration (Filament 5, module-owned):
- `Filament/Resources/Products/ProductResource.php`
- `Filament/Resources/Products/Schemas/ProductForm.php`
- `Filament/Resources/Products/Tables/ProductsTable.php`
- `Filament/Resources/Products/Pages/ListProducts.php`
- `Filament/Resources/Products/Pages/CreateProduct.php`
- `Filament/Resources/Products/Pages/EditProduct.php`
- `Filament/Resources/Categories/CategoryResource.php`
- `Filament/Resources/Categories/Schemas/CategoryForm.php`
- `Filament/Resources/Categories/Tables/CategoriesTable.php`
- `Filament/Resources/Categories/Pages/ListCategories.php`
- `Filament/Resources/Categories/Pages/CreateCategory.php`
- `Filament/Resources/Categories/Pages/EditCategory.php`

Authorization:
- `Policies/ProductPolicy.php`
- `Policies/CategoryPolicy.php`

Inventory (owned by the Inventory module, listed here only for the product boundary):
- `App\Modules\Inventory\Models\Inventory` and `StockMovement`
- `App\Modules\Inventory\Database\Migrations\z03_add_variant_id_to_inventories_table.php`
- `App\Modules\Inventory\Database\Migrations\z04_add_variant_id_to_stock_movements_table.php`
- `App\Modules\Inventory\Database\Migrations\z05_finalize_inventories_table.php`
- `App\Modules\Inventory\Database\Migrations\z06_finalize_stock_movements_table.php`
- `App\Modules\Inventory\Actions\CreateInventory` and `AdjustStock`
- `App\Modules\Inventory\Contracts\CreatesInventory` and `AdjustsStock`
- `App\Modules\Inventory\Data\CreateInventoryData` and `AdjustStockData`

The service provider binds product, category and variant contracts to their
actions. Inventory contracts are bound by the Inventory module.

The module manifest declares a dependency on Core. Inventory declares
dependencies on Core and Catalog.

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
- Accepts an optional `sku` for the default variant and rejects duplicate SKUs.
- Generates a collision-safe slug from the product name via `Support\UniqueSlug` (`name`, `name-2`, `name-3`, ...).
- Falls back to the slug `product` when the name has no slug characters.
- Creates an active product with `draft` publication status and, in the same
  transaction, one default `ProductVariant`.
- Generates `SKU-{productId}` (collision-safe) when no SKU is supplied.
- Returns the created Product model.

Verified on 2026-11-09 with `src/tests/Feature/Catalog/ProductCreationTest.php`:
- Products table schema and module migration execution.
- Module discovery, provider registration and contract binding.
- Product factory resolution and record persistence.
- Product creation, persistence and optional description handling.
- Duplicate names receive unique slugs.
- Validation rejects empty and over-length names.

## Product variants

The variant is the canonical purchasable unit. Every product owns at least one
`ProductVariant`; a simple product has one default variant, and a variable
product can have several.

`product_variants` defines:
- `id`
- `product_id` foreign key to `products` with cascading delete
- nullable `sku`, globally unique
- nullable `label` (display name, for example `Red / M`)
- nullable `variant_key`, unique per product
- `is_active` boolean, default `true`
- `position` integer, default `0`
- timestamps
- indexes on `(product_id, is_active)` and `(product_id, position)`

Rules:
- SKU is nullable in the database to allow drafts, but unique when present.
  `CreateProduct` rejects duplicate SKUs through validation and generates a
  collision-safe `SKU-{productId}` when none is supplied.
- `variant_key` is a deterministic option signature such as `color:1|size:4`
  and is unique per product. Multiple `null` keys are allowed.
- `Product::variants()` is the owning relationship. Deleting a product cascades
  to its variants, inventory records and stock movements.
- Existing products are backfilled idempotently with one default variant by
  `Support\DefaultVariantBackfill` (SKU `SKU-{productId}`, label `Default`,
  `variant_key = null`, `position = 0`, `is_active` copied from the product).
- `ProductFactory` creates a default variant by default and exposes
  `withoutVariant()` for tests that need a product without one.

The variant foundation deliberately does not include variant administration,
attribute values, media or prices. Those remain later phases.

Verified on 2026-11-11:
- `ProductVariantFoundationTest.php` (10 tests): schema, factory, product
  relationship, default variant from the product factory, `withoutVariant()`,
  unique SKU, unique variant key per product, multiple null variant keys,
  inactive state and product-delete cascade.
- `ProductCreationTest.php` additions: atomic default-variant creation, custom
  SKU, duplicate-SKU rejection, collision-safe generated SKU and product
  rollback when variant creation fails.

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

## Filament administration layer

The first authorised Filament 5 administration layer lives inside the Catalog
module and is discovered by `AdminPanelProvider` from
`app/Modules/Catalog/Filament/Resources`.

Access control:
- Identity users carry an `is_admin` boolean (default `false`).
- `User` implements `FilamentUser`; `canAccessPanel()` returns `is_admin`.
- `ProductPolicy` and `CategoryPolicy` require `is_admin` for CRUD abilities.
- `ProductPolicy` adds `publish` and `manageCategories`.
- Policies are registered with `Gate::policy()` in
  `CatalogServiceProvider::bootModule()`.

Product resource:
- Lists name, slug, publication status, categories, variant count, variant SKUs
  and creation date with search, status/category filters, sorting and pagination.
- Creation calls `CreatesProducts` with `CreateProductData`; the form exposes the
  fields the contract supports (name, description and an optional default-variant
  SKU). New products are active and draft, and receive a default variant.
- Editing calls `UpdatesProducts` with `UpdateProductData` and shows the default
  variant SKU read-only. Variant administration is not implemented in this task.
- Header actions call `TransitionsProductStatus` for publish, unpublish, archive
  and restore, and are explicitly authorised with `publish`.
- A `manageCategories` action calls `SyncsProductCategories` and is explicitly
  authorised with `manageCategories`. It covers assign, remove and replace in
  one contract-backed flow.

Category resource:
- Lists name, slug, parent, active state, product count, child count and creation
  date with search, active/parent filters, sorting and pagination.
- Creation and editing call `CreatesCategories` and `UpdatesCategories`.
- Deletion uses a Filament `DeleteAction` whose `using()` callback calls
  `DeletesCategories`. Categories with assigned products are rejected by the
  contract and remain intact; no implicit detachment occurs.
- The parent selector excludes the record itself; descendant-cycle rejection is
  enforced by `UpdateCategory`.

Security and boundaries:
- All writes route through Catalog contracts; Filament and Livewire contain no
  duplicated business rules and never manipulate the pivot directly.
- Custom actions declare explicit `authorize()` callbacks.
- No bulk delete actions are registered, so bulk operations cannot bypass
  per-record policy checks.
- Panel access is enforced by the panel's `Authenticate` middleware and
  `canAccessPanel()`, independently of navigation visibility.

Verified on 2026-11-11:
- `ProductResourceTest.php` (13 tests, 51 assertions): listing, search, status
  filter, creation, validation, editing, publish/unpublish/archive/restore,
  category assign/remove/replace and HTTP access.
- `CategoryResourceTest.php` (11 tests, 42 assertions): listing, search, parent
  visibility, creation, validation, editing, parent assignment, self-parent
  rejection, deletion, deletion rejection with products and HTTP access.
- `CatalogAdminAuthorizationTest.php` (12 tests, 42 assertions): guest redirect,
  non-admin 403, admin access, `canAccessPanel()`, policy abilities for both
  resources, direct Livewire mount denial and absence of bulk actions.
- Catalog Filament suite: 36 passed (135 assertions).
- Catalog focused suite: 129 passed (394 assertions).

Known limitations:
- `UpdateProduct` treats a `null` description as "not provided", so the edit form
  cannot clear a description.
- `UpdateCategory` treats a `null` parent as "not provided", so clearing a parent
  back to root is not supported through the form.
- Admin access is a single boolean; granular roles and permissions are future
  work.
- Variant administration (create/edit/deactivate variants, options, positions)
  is not implemented; only the default variant is managed through product
  creation.
- Attributes, media and pricing are not implemented.
- Inventory is variant-keyed, but no Inventory administration or reporting UI
  exists.

## Proposed future capabilities

These are not yet implemented or approved as final scope:
- Brands.
- Collections.
- Attribute definitions, options and product/variant values.
- Variant administration for multiple variants (the variant table, default
  variant and model foundation are implemented).
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
11. ~~Is there an authorised administration layer?~~ Verified: Filament 5 product and category resources with an explicit admin flag, module policies and contract-backed writes (2026-11-11).
12. ~~What product fields are required for the first release?~~ Partially resolved: the variant-first foundation is implemented (DEC-019); brands, attributes, media and SEO remain future scope.
13. ~~Are products variant-based from the beginning?~~ Yes. Every product owns at least one variant, and the variant is the canonical purchasable unit (DEC-019).

## Acceptance criteria for the initial Catalog foundation

- Product and category migrations execute through the project's normal migration workflow.
- Product and category factories can create valid records.
- Every product owns at least one variant, and product creation plus default-variant creation are atomic.
- Variant SKUs are globally unique when present, and variant keys are unique per product.
- Existing products are backfilled with default variants idempotently.
- Inventory and stock movements are keyed to variants without a dual stock path.
- Product and category actions persist the expected fields.
- Product publication status defaults to draft and transitions are validated and tested.
- Publication status is independent of `is_active` availability.
- Product-category assignment, removal and synchronisation are validated and tested.
- Category retrieval and deletion rules are validated and tested; assigned categories cannot be deleted.
- The Filament administration layer is authorised, reuses module contracts and is covered by tests.
- Slug uniqueness behavior is defined and tested.
- Parent-child and product-category relationships are defined and tested.
- Relevant Catalog tests pass.
- Catalog does not take ownership of stock, orders or payments.
