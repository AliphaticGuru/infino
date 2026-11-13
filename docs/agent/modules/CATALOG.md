# Catalog Module Specification

**Status:** Product, category, variant and attribute/option foundations complete; retrieval, update, publication lifecycle, product-category assignment, category retrieval/deletion, variant administration, normalized attributes/options, product media/galleries, variant-keyed Inventory and the Filament admin layer verified
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
- `Actions/FindProductVariant.php`
- `Actions/CreateProductVariant.php`
- `Actions/UpdateProductVariant.php`
- `Actions/TransitionProductVariantStatus.php`
- `Actions/RepositionProductVariants.php`
- `Contracts/FindsProductVariants.php`
- `Contracts/CreatesProductVariants.php`
- `Contracts/UpdatesProductVariants.php`
- `Contracts/TransitionsProductVariantStatus.php`
- `Contracts/RepositionsProductVariants.php`
- `Data/CreateProductVariantData.php`
- `Data/UpdateProductVariantData.php`
- `Models/ProductAttribute.php`
- `Models/ProductAttributeOption.php`
- `Models/ProductVariantAttributeOption.php`
- `Support/AttributeType.php`
- `Support/VariantKey.php`
- `Database/Factories/ProductAttributeFactory.php`
- `Database/Factories/ProductAttributeOptionFactory.php`
- `Database/Factories/ProductVariantAttributeOptionFactory.php`
- `Database/Migrations/z07_create_product_attributes_table.php`
- `Database/Migrations/z08_create_product_attribute_options_table.php`
- `Database/Migrations/z09_create_product_attribute_product_table.php`
- `Database/Migrations/z10_create_product_variant_attribute_options_table.php`
- Attribute/option/assignment/variant-option contracts, actions and data objects under `Actions/`, `Contracts/` and `Data/`.

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
- `Filament/Resources/Products/RelationManagers/VariantsRelationManager.php`
- `Filament/Resources/Products/RelationManagers/ProductAttributesRelationManager.php`
- `Filament/Resources/Products/Schemas/ProductVariantForm.php`
- `Filament/Resources/Products/Tables/ProductVariantsTable.php`
- `Filament/Resources/Attributes/AttributeResource.php`
- `Filament/Resources/Attributes/Schemas/AttributeForm.php`
- `Filament/Resources/Attributes/Tables/AttributesTable.php`
- `Filament/Resources/Attributes/Pages/ListAttributes.php`
- `Filament/Resources/Attributes/Pages/CreateAttribute.php`
- `Filament/Resources/Attributes/Pages/EditAttribute.php`
- `Filament/Resources/Attributes/RelationManagers/OptionsRelationManager.php`
- `Filament/Resources/Categories/CategoryResource.php`
- `Filament/Resources/Categories/Schemas/CategoryForm.php`
- `Filament/Resources/Categories/Tables/CategoriesTable.php`
- `Filament/Resources/Categories/Pages/ListCategories.php`
- `Filament/Resources/Categories/Pages/CreateCategory.php`
- `Filament/Resources/Categories/Pages/EditCategory.php`

Authorization:
- `Policies/ProductPolicy.php`
- `Policies/CategoryPolicy.php`
- `Policies/ProductVariantPolicy.php`
- `Policies/ProductAttributePolicy.php`
- `Policies/ProductAttributeOptionPolicy.php`

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

The variant foundation does not include attribute values, media or prices. Those
remain later phases; variant administration is covered in the next section.

Verified on 2026-11-11:
- `ProductVariantFoundationTest.php` (10 tests): schema, factory, product
  relationship, default variant from the product factory, `withoutVariant()`,
  unique SKU, unique variant key per product, multiple null variant keys,
  inactive state and product-delete cascade.
- `ProductCreationTest.php` additions: atomic default-variant creation, custom
  SKU, duplicate-SKU rejection, collision-safe generated SKU and product
  rollback when variant creation fails.

## Product variant administration

Variant administration is implemented behind Catalog contracts and actions. The
default variant is the product's oldest variant (lowest `id`); no extra
`is_default` column is used. `Product::defaultVariant()` exposes it and
`ProductVariant::isDefault()` compares against it (DEC-020).

Retrieval (`FindsProductVariants` / `FindProductVariant`):
- `forProduct(Product)` returns all variants ordered by `position` then `id`.
- `find(int)` returns a variant or throws `ModelNotFoundException`.
- `findForProduct(Product, int)` scopes retrieval to the product and throws
  `ModelNotFoundException` for variants owned by another product.

Creation (`CreatesProductVariants` / `CreateProductVariant` /
`CreateProductVariantData`):
- Accepts the product, optional SKU, optional label, optional `variant_key` and
  an active flag.
- Validates SKU global uniqueness and `variant_key` uniqueness per product.
- Generates a collision-safe `SKU-{productId}-{nextPosition}` when no SKU is
  supplied.
- Runs in a transaction with the product row and existing variants locked.
- Appends the variant, then normalizes all positions to `0..n-1`.
- The first variant of a variant-less product becomes the default (label
  `Default`, position `0`, active), preserving the default-variant invariant.

Update (`UpdatesProductVariants` / `UpdateProductVariant` /
`UpdateProductVariantData`):
- Accepts only SKU, label and `variant_key`; the owning product cannot be
  changed through this contract.
- Omitted fields keep their stored values.
- Validates SKU uniqueness and per-product `variant_key` uniqueness while
  ignoring the current record.
- Runs in a transaction and does not touch inventory or stock movements.

Lifecycle (`TransitionsProductVariantStatus` / `TransitionProductVariantStatus`):
- `activate` rejects an already-active variant.
- `deactivate` rejects an already-inactive variant, the default variant and the
  last active variant.
- Transitions run in a transaction with row locking and preserve inventory rows
  and append-only stock movements.
- Variants are never hard-deleted through administration; deletion is denied by
  policy.

Positioning (`RepositionsProductVariants` / `RepositionProductVariants`):
- Accepts the complete, duplicate-free set of the product's variant IDs.
- Rejects duplicate, missing and cross-product IDs with `ValidationException`.
- Runs in a transaction with the product and variants locked, then assigns
  positions `0..n-1` in the supplied order.
- Never changes another product's variants.

Verified on 2026-11-11:
- `ProductVariantAdministrationTest.php` (27 tests, 95 assertions): contract
  resolution, retrieval and scoping, creation, multiple variants, SKU and
  variant-key uniqueness, generated SKUs, rollback, updates, inventory/movement
  preservation, activation/deactivation, default and last-active protection,
  positioning and policy authorization.
- `ProductVariantResourceTest.php` (9 tests, 44 assertions): listing, creation,
  validation, editing, activation/deactivation, default deactivation protection,
  relation-manager visibility and HTTP access.

## Product attributes and options

Normalized attribute definitions and options sit on top of the variant-first
architecture (DEC-021). The variant remains the canonical purchasable unit, and
Inventory is untouched.

Tables:
- `product_attributes`: `id`, immutable globally unique `code`, editable
  `name`, `type` (`select` only in Task 005), `is_variant_dimension`,
  `position`, `is_active`, timestamps.
- `product_attribute_options`: `id`, `product_attribute_id` (cascade), immutable
  per-attribute `code`, editable `label`, `position`, `is_active`, timestamps;
  unique `(product_attribute_id, code)` and unique `(id, product_attribute_id)`.
- `product_attribute_product`: `product_id`, `product_attribute_id`, composite
  primary key.
- `product_variant_attribute_options`: `id`, `product_variant_id` (cascade),
  `product_attribute_id` (restrict), `product_attribute_option_id`, timestamps;
  unique `(product_variant_id, product_attribute_id)` and composite FK
  `(product_attribute_option_id, product_attribute_id)` to
  `product_attribute_options (id, product_attribute_id)`.

Models and relationships:
- `ProductAttribute::options()` and `ProductAttribute::products()`.
- `ProductAttributeOption::attribute()` and
  `ProductAttributeOption::variantSelections()`.
- `Product::productAttributes()` (deliberately not `attributes()`).
- `ProductVariant::attributeOptions()`.
- `ProductVariantAttributeOption` belongs to the variant, attribute and option.

Contracts and actions:
- Definitions: `FindsProductAttributes` / `FindProductAttribute`,
  `CreatesProductAttributes` / `CreateProductAttribute`,
  `UpdatesProductAttributes` / `UpdateProductAttribute`,
  `DeletesProductAttributes` / `DeleteProductAttribute`.
- Options: `FindsProductAttributeOptions` / `FindProductAttributeOption`,
  `CreatesProductAttributeOptions` / `CreateProductAttributeOption`,
  `UpdatesProductAttributeOptions` / `UpdateProductAttributeOption`,
  `DeletesProductAttributeOptions` / `DeleteProductAttributeOption`,
  `RepositionsProductAttributeOptions` /
  `RepositionProductAttributeOptions`.
- Assignment: `AssignsProductAttributes` / `AssignProductAttributes`,
  `RemovesProductAttributes` / `RemoveProductAttributes`,
  `SyncsProductAttributes` / `SyncProductAttributes`.
- Variant mapping: `SyncsVariantAttributeOptions` /
  `SyncVariantAttributeOptions` / `SyncVariantAttributeOptionsData`.

Rules:
- Attribute codes are globally unique and immutable; option codes are unique per
  attribute and immutable. Names and labels are editable.
- Only active variant dimensions can be newly assigned to a product; inactive
  and descriptive attributes are rejected.
- Assigning the same attribute twice is idempotent; assignments are isolated per
  product.
- A variant holds at most one option per attribute. The composite FK prevents an
  option from being attached to a different attribute.
- Configured variants must select exactly one option for every variant dimension
  assigned to their product. Incomplete, duplicate, unknown, cross-product,
  mismatched and newly-inactive selections raise `ValidationException`.
- An existing selection may remain after its attribute or option is deactivated.
- `variant_key` is derived from immutable `attributeCode:optionCode` pairs
  sorted by attribute `position` then `code`, joined with `|` (for example
  `size:m|color:red`). A manually supplied key must be absent or equal to the
  derived key. Unconfigured variants keep the optional manual key behaviour.
- Duplicate option combinations are rejected per product through the existing
  per-product `variant_key` uniqueness.
- Definitions/options are deactivated rather than hard-deleted once referenced;
  guarded hard deletion is allowed only when unreferenced. Removing an attribute
  that a product variant still selects is rejected.
- All multi-row writes run in transactions with row locking. Variant option
  changes do not touch inventory rows or stock movements.

Variant integration:
- `CreateProductVariantData` and `UpdateProductVariantData` accept an optional
  `attributeOptions` map of `attribute_id => option_id`.
- `CreateProductVariant` and `UpdateProductVariant` apply the same validation and
  key derivation inside their existing transactions, so variant
  creation/editing remains atomic.
- `null` means "leave selections unchanged" on update; an empty array clears
  selections and the derived key for a product without dimensions.

Authorization:
- `ProductAttributePolicy` and `ProductAttributeOptionPolicy` are admin-only;
  deletion is additionally guarded by reference checks in the actions.
- `ProductPolicy::manageAttributes` exposes product assignment to admins.

Verified on 2026-11-12:
- `ProductAttributeFoundationTest.php`, `ProductAttributeAdministrationTest.php`,
  `ProductAttributeOptionAdministrationTest.php`,
  `ProductAttributeAssignmentTest.php` and
  `ProductVariantAttributeOptionsTest.php`: 53 passed (108 assertions) across
  the business suites (including the 6 foundation tests).
- `ProductAttributeResourceTest.php`: 14 passed (85 assertions).
- `ProductAttributeAssignmentFilamentTest.php`: 5 passed (21 assertions).
- `ProductVariantAttributeOptionsFilamentTest.php`: 5 passed (49 assertions).
- Catalog focused suite: 265 passed (862 assertions).
- Inventory focused suite: 52 passed (174 assertions).
- PostgreSQL `migrate:fresh`, rollback of `z07`–`z10` and re-apply: verified.

## Product media

Product media is implemented in Task 006 and recorded as DEC-022. The
implementation lives in `src/app/Modules/Catalog/` and is verified by the media
foundation, upload, update, primary, deletion, reposition, resolution,
authorization and Filament relation-manager tests.

Ownership:
- Catalog owns `ProductMedia` and product/variant galleries.
- A Catalog-owned `MediaStorage` contract isolates filesystem access. It can
  later move to Core or a separate Media module if another consumer appears.
- Spatie Media Library, Intervention Image and server-side image processing are
  not used because no image driver is installed and no dependency is approved.

`product_media` table:
- `id`
- `product_id` FK to `products`, cascade
- nullable `product_variant_id` FK to `product_variants`, cascade
- `disk`, `path`, `original_name`, `mime_type`, `size`, `width`, `height`
- nullable `alt_text`
- gallery-scoped `position`
- `is_primary`
- timestamps
- indexes `(product_id, position)`, `(product_variant_id, position)` and
  `(product_id, is_primary)`
- composite FK `(product_variant_id, product_id)` to
  `product_variants (id, product_id)` so a variant image cannot belong to
  another product
- PostgreSQL-only partial unique indexes for one primary per product gallery and
  one primary per variant gallery

Gallery rules:
- `product_variant_id = null` is product-level media; a variant ID is that
  variant's gallery.
- `position` is scoped to one gallery and normalized to `0..n-1`.
- At most one primary per gallery; the first image becomes primary; setting a
  new primary clears the old one; deleting the primary promotes the next image
  by position then ID.
- Variant gallery and primary resolution fall back to the product gallery when
  the variant has no images.
- Variant association is immutable after upload; moving an image requires delete
  and re-upload.

Contracts and actions:
- `FindsProductMedia` / `FindProductMedia`
- `AddsProductMedia` / `AddProductMedia` / `AddProductMediaData`
- `UpdatesProductMedia` / `UpdateProductMedia` / `UpdateProductMediaData`
- `SetsPrimaryProductMedia` / `SetPrimaryProductMedia`
- `DeletesProductMedia` / `DeleteProductMedia`
- `RepositionsProductMedia` / `RepositionProductMedia` /
  `RepositionProductMediaData`
- `MediaStorage` / `FilesystemMediaStorage` / `StoredMediaData`

Storage:
- Development: Laravel `public` disk plus `php artisan storage:link`; the
  application-wide `FILESYSTEM_DISK` stays `local`.
- Production: the existing `s3` disk through `CATALOG_MEDIA_DISK` and standard
  `AWS_*` variables. The disk is stored per row.
- Paths are generated as `catalog/products/{product_id}/{ulid}.{extension}`.
- No thumbnails, conversions or EXIF stripping in the first release.

Filament administration:
- `MediaRelationManager` on the product edit page with product and per-variant
  gallery tabs.
- Upload uses `FileUpload::storeFiles(false)` and calls `AddsProductMedia`.
- Alt-text edit, set-primary, delete and move-up/move-down actions call the
  corresponding contracts; built-in drag reordering is not used.
- `ImageColumn` reads the row's disk.
- `ProductPolicy::manageMedia` and `ProductMediaPolicy` are admin-only.

Validation and security:
- JPEG, PNG, WebP and GIF only; SVG and executable formats are rejected.
- 5 MB default size limit and 8000 × 8000 default dimension cap.
- Server-side MIME/content validation is authoritative.
- Generated filenames, sanitized original names and an allowed-disk list.
- File/database consistency: upload deletes the file if the row write fails;
  delete removes the row first and deletes the file after commit best-effort.
- Cascade deletes can orphan files; a future `catalog:prune-media` command is
  required. EXIF/GPS metadata is retained until an approved conversion step
  exists.

Verification on 2026-11-13:
- Catalog focused suite: 337 passed (1073 assertions).
- Task 006 media tests: 60 non-Filament tests (149 assertions) and 12 Filament
  tests (62 assertions).
- Inventory focused suite: 52 passed (174 assertions).
- Full suite: 420 passed, 3 known pre-existing failures
  (`AuthenticationTest`, `TwoFactorChallengeTest`, `PostCreatePageTest`).
- Pint: 39 changed files pass.
- PHPStan (Catalog + `config/catalog.php`): no errors.
- PostgreSQL `migrate:fresh`, rollback of `z11` and re-apply verified; both
  partial unique indexes and the composite FK enforced by direct inserts.
- `git diff --check`: clean.

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
  variant SKU read-only.
- The `VariantsRelationManager` on the edit page lists variants with SKU, label,
  variant key, status, position and creation date. Creation, editing,
  activation/deactivation and drag reordering delegate to
  `CreatesProductVariants`, `UpdatesProductVariants`,
  `TransitionsProductVariantStatus` and `RepositionsProductVariants`.
- Variant creation/editing forms expose SKU, label and `variant_key`; lifecycle
  is controlled by explicit actions rather than a form toggle.
- Deactivation is hidden for the default variant and for variants without another
  active sibling.
- The variant policy denies deletion, so the relation manager exposes no delete
  action.
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
- `ProductVariantResourceTest.php` (9 tests, 44 assertions): variant listing,
  creation, validation, editing, activation/deactivation, default protection,
  repositioning, relation-manager visibility and HTTP access.
- Catalog Filament suite after Task 004: 45 passed (179 assertions).
- Catalog focused suite after Task 004: 180 passed (570 assertions).

Known limitations:
- `UpdateProduct` treats a `null` description as "not provided", so the edit form
  cannot clear a description.
- `UpdateCategory` treats a `null` parent as "not provided", so clearing a parent
  back to root is not supported through the form.
- `UpdateProductVariant` treats a `null` label or variant key as "not provided",
  so the form cannot clear them.
- Admin access is a single boolean; granular roles and permissions are future
  work.
- Variant hard deletion is not exposed; variants are deactivated instead.
- Attribute support is limited to `select` variant dimensions; descriptive
  values, text/number/boolean types and product-level values are deferred.
- Publication does not yet require every variant of a dimensioned product to be
  configured.
- Media is implemented with gallery-scoped ordering, primary images, alt text,
  validation and Filament administration; thumbnails, conversions, EXIF
  stripping and orphan-file pruning are deferred. Pricing is not implemented.
- Inventory is variant-keyed, but no Inventory administration or reporting UI
  exists.

## Proposed future capabilities

These are not yet implemented or approved as final scope:
- Brands.
- Collections.
- Attribute definitions and options are implemented for `select` variant
  dimensions; descriptive product values, other attribute types and product-level
  values remain future scope.
- Product media thumbnails, conversions, EXIF stripping and orphan-file pruning.
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
12. ~~What product fields are required for the first release?~~ Partially resolved: the variant-first foundation is implemented (DEC-019); brands, media and SEO remain future scope.
13. ~~Are products variant-based from the beginning?~~ Yes. Every product owns at least one variant, and the variant is the canonical purchasable unit (DEC-019).
14. ~~Can variants be administered safely?~~ Verified: retrieval, creation, update, activation/deactivation, default and last-active protection, product-scoped repositioning and Filament relation-manager administration (DEC-020, 2026-11-11).
15. ~~How are attributes, options and variant option selections modelled?~~ Verified: normalized global definitions, owned options, product assignments, variant selections, derived variant keys, guarded deletion and Filament administration (DEC-021, 2026-11-12).
16. ~~How should product media and galleries be owned and stored?~~ Verified: Catalog-owned `ProductMedia`, `MediaStorage` abstraction, product/variant galleries with gallery-scoped ordering and primary images, public-disk development storage and S3-compatible direction, and Filament relation-manager administration (Task 006, DEC-022 implemented, 2026-11-13).

## Acceptance criteria for the initial Catalog foundation

- Product and category migrations execute through the project's normal migration workflow.
- Product and category factories can create valid records.
- Every product owns at least one variant, and product creation plus default-variant creation are atomic.
- Additional variants can be created, updated, activated, deactivated and repositioned through Catalog contracts.
- The default variant and the last active variant cannot be deactivated.
- Variant administration preserves inventory and stock movement relationships.
- Variant SKUs are globally unique when present, and variant keys are unique per product.
- Existing products are backfilled with default variants idempotently.
- Inventory and stock movements are keyed to variants without a dual stock path.
- Product and category actions persist the expected fields.
- Product publication status defaults to draft and transitions are validated and tested.
- Publication status is independent of `is_active` availability.
- Product-category assignment, removal and synchronisation are validated and tested.
- Category retrieval and deletion rules are validated and tested; assigned categories cannot be deleted.
- Attribute definitions and options are normalized, uniquely coded, guarded against unsafe deletion and covered by tests.
- Product attribute assignment and variant option selection enforce active-state, ownership, completeness and composite integrity.
- Configured variants derive deterministic per-product `variant_key` values; unconfigured legacy variants remain valid.
- Attribute option changes preserve inventory and stock movement records.
- The Filament administration layer is authorised, reuses module contracts and is covered by tests.
- Slug uniqueness behavior is defined and tested.
- Parent-child and product-category relationships are defined and tested.
- Relevant Catalog tests pass.
- Catalog does not take ownership of stock, orders or payments.
