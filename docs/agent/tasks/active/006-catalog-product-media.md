# Task 006 — Catalog Product Media

**Status:** Implemented and verified on 2026-11-13. DEC-022 is accepted.
**Priority:** High
**Branch:** `feature/catalog`
**Base commit:** `74dcd79` — `feat(catalog): implemented catalog product attributes & options foundation`
**Decision:** DEC-022 in `docs/agent/DECISIONS.md` (status: Accepted)

## Objective

Design a product media and gallery capability for the variant-first Catalog
architecture without breaking the existing product, variant, attribute or
Inventory relationships.

Scope under assessment:

- Product-level images and galleries.
- Variant-specific images.
- Image ordering and primary-image selection.
- Alternative text for accessibility and SEO.
- Local development storage and future S3-compatible storage.
- Transactional consistency, file cleanup and authorization.
- Filament 5 administration.
- Test requirements and acceptance criteria.

Out of scope for the first implementation:

- Pricing, Cart, Checkout, Orders and Payments.
- Image resizing, thumbnails, format conversion or CDN integration.
- Video, audio, PDF or other non-image media.
- Media for categories, brands or collections.
- A generic cross-module media library or digital asset manager.
- Private or signed media delivery.
- EXIF stripping and privacy scrubbing.
- Deduplication by content hash.
- Bulk uploads beyond a simple per-file loop.
- Drag-and-drop reordering (explicit move actions are proposed first).

## Phase A — Architecture assessment

### 1. Ownership recommendation

**Recommended: Catalog owns product media.**

Reasons:

- The Product Blueprint already assigns "Product media and galleries" to the
  Catalog capability.
- Catalog is the only current consumer. Creating a separate Media module now
  would violate the roadmap rule: "Do not create a module solely because it
  appears in the proposed module map."
- Media rows need product and variant ownership, product-scoped authorization
  and product-scoped ordering. Keeping them in Catalog avoids a cross-module
  join for the primary catalog read path.
- A separate module would add module manifests, dependency edges, migrations,
  contracts and tests before a second consumer exists.

To keep a future extraction cheap, filesystem work is isolated behind a
Catalog-owned `MediaStorage` contract. If Orders, a CMS or a marketing module
later needs media, the contract and storage implementation can move to Core or a
dedicated Media module without changing `ProductMedia` rows.

**Rejected alternatives:**

| Option | Reason rejected |
|---|---|
| Separate Media module now | Premature; one consumer; adds boundary and migration overhead without a current benefit. |
| Spatie Media Library | New dependency; polymorphic media table and conversion pipeline are more than required; image conversions need an image driver that is not installed. |
| Intervention Image | New dependency and requires GD or Imagick; neither extension is present in the PHP runtime. |
| Store image bytes in PostgreSQL | Bloats the database and backups; no CDN or range-serving path; poor fit for catalog galleries. |
| Store only external URLs | No ownership, no upload lifecycle, link rot and no authorization over the asset. |
| Private `local` disk plus a custom route | Product images are public catalog assets; a public disk and `storage:link` are simpler. S3-compatible storage remains available through the same abstraction. |

### 2. Entity model

```
Product 1---* ProductMedia
Product 1---* ProductVariant
ProductVariant 1---* ProductMedia        (nullable product_variant_id)
```

`ProductMedia` is always owned by a product. `product_variant_id` is nullable:

- `null` means a product-level gallery image.
- A variant ID means the image belongs to that variant's gallery.

A variant-specific image must belong to the same product as its media row. This
is enforced by a composite foreign key and by application validation.

### 3. Proposed database schema

New table `product_media`:

| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `product_id` | FK `products`, cascade | required owner |
| `product_variant_id` | FK `product_variants`, cascade, nullable | variant gallery owner |
| `disk` | string(50), default `public` | Laravel filesystem disk |
| `path` | string(1024) | storage-relative path; generated server-side |
| `original_name` | string(255), nullable | sanitized client filename |
| `mime_type` | string(100), nullable | detected MIME type |
| `size` | unsigned bigint, nullable | bytes |
| `width` | unsigned integer, nullable | pixels, best effort |
| `height` | unsigned integer, nullable | pixels, best effort |
| `alt_text` | string(255), nullable | accessibility/SEO text |
| `position` | unsigned integer, default `0` | ordering within one gallery |
| `is_primary` | boolean, default `false` | one primary per gallery |
| `created_at`, `updated_at` | timestamps | |

Indexes and constraints:

- `(product_id, position)` for product gallery reads.
- `(product_variant_id, position)` for variant gallery reads.
- `(product_id, is_primary)` to support primary lookup.
- Composite FK `(product_variant_id, product_id)` to
  `product_variants (id, product_id)`, cascade on delete.
- Add unique `(id, product_id)` on `product_variants` as the composite-FK
  target. This is an additive index; it does not change the variant relationship
  or Inventory.
- PostgreSQL-only partial unique indexes, created conditionally by driver:
  - `product_media (product_id) WHERE is_primary AND product_variant_id IS NULL`
  - `product_media (product_variant_id) WHERE is_primary AND product_variant_id IS NOT NULL`

The application enforces the single-primary invariant transactionally. The
partial indexes are a PostgreSQL safety net; SQLite tests cannot create them
portably, so tests assert the application rule and PostgreSQL verification
asserts the index.

Migration:

- `z11_create_product_media_table.php` in
  `src/app/Modules/Catalog/Database/Migrations/`.
- `z07`–`z10` are already used by Task 005. `z03`–`z06` belong to Inventory.
- The migration must be additive, must not alter existing data and must roll
  back cleanly. The `product_variants (id, product_id)` unique index is added in
  the same migration and removed on rollback.

`path` is not uniquely indexed: a btree index on a long multibyte path can
exceed PostgreSQL's index size limit. Uniqueness is guaranteed by generating a
ULID filename and never accepting a user-supplied path.

### 4. Gallery and primary-image semantics

Two independent galleries exist per product:

1. **Product gallery** — rows where `product_variant_id IS NULL`.
2. **Variant gallery** — rows where `product_variant_id = <variant>`.

Rules:

- `position` is scoped to one gallery, not to the whole product. Positions are
  normalized to `0..n-1` within the gallery.
- At most one row per gallery has `is_primary = true`.
- The first image added to an empty gallery becomes primary automatically.
- Setting a new primary clears the previous primary in the same gallery only.
- Deleting the primary promotes the next image in that gallery by `position`,
  then `id`. If the gallery becomes empty, no primary remains.
- Deleting a non-primary image does not change primary state.
- Reordering never changes primary state.
- Reordering one gallery never touches the other gallery.

Read resolution:

- `Product::media()` returns product-level media ordered by `position`, `id`.
- `ProductVariant::media()` returns variant-level media ordered by `position`,
  `id`.
- `Product::primaryMedia()` returns the product primary, or the first product
  image by position as a fallback.
- `ProductVariant::resolvedPrimaryMedia()` returns the variant primary, then the
  product primary, then the product's first image, then `null`.
- `ProductVariant::gallery()` returns the variant gallery when it has images;
  otherwise it falls back to the product gallery. This matches the Task 002
  recommendation: variant-specific primary first, then product primary.

### 5. Storage strategy

Default development configuration:

- Disk: `public` (`storage/app/public`), served at `APP_URL/storage`.
- `php artisan storage:link` is required once per environment. It is not
  currently linked in the repository.
- `FILESYSTEM_DISK` remains `local` because it is the application-wide private
  default. Catalog media explicitly selects its own disk; it does not change the
  global default.
- The Docker volume must persist `src/storage/app/public` across container
  recreation.

Production direction:

- The `s3` disk already exists in `src/config/filesystems.php`.
- The same `MediaStorage` contract writes to S3-compatible storage by changing
  `CATALOG_MEDIA_DISK` and the standard `AWS_*` variables.
- The `disk` column is stored per row, so existing local media remains readable
  after a future migration to S3.
- Product images are public catalog assets. S3 objects must use public
  visibility and a configured `AWS_URL` or public bucket URL. Signed/private
  delivery is out of scope.

Proposed configuration file `src/config/catalog.php`:

```php
return [
    'media' => [
        'disk' => env('CATALOG_MEDIA_DISK', 'public'),
        'allowed_disks' => ['public', 's3'],
        'directory' => 'catalog/products',
        'max_size_kb' => (int) env('CATALOG_MEDIA_MAX_SIZE_KB', 5120),
        'allowed_mime_types' => [
            'image/jpeg',
            'image/png',
            'image/webp',
            'image/gif',
        ],
        'allowed_extensions' => ['jpg', 'jpeg', 'png', 'webp', 'gif'],
        'max_width' => 8000,
        'max_height' => 8000,
    ],
];
```

The Catalog provider loads it with `mergeConfigFrom()`. The disk is never taken
from request input. If a future UI allows disk selection, it must be validated
against `allowed_disks`.

File naming and path:

- `catalog/products/{product_id}/{ulid}.{extension}`.
- The extension is derived from the validated MIME type, not from the original
  filename.
- The original filename is stored only in `original_name`, sanitized with
  `basename()` and truncated to 255 characters.
- No user input is used to build the path.

No image processing in the first release:

- The runtime has no GD or Imagick extension; only `fileinfo` is present.
- `getimagesize()` is part of core PHP and is available. Width and height are
  recorded best-effort and remain nullable.
- Images are served at original size. Filament's `ImageColumn` controls display
  size through CSS.
- Thumbnails and conversions require a separately approved dependency and a
  runtime change.

### 6. Domain contracts, data objects and actions

Storage abstraction:

- `Contracts\MediaStorage`
  - `store(UploadedFile $file, string $disk, string $directory): StoredMediaData`
  - `delete(string $disk, string $path): void`
  - `exists(string $disk, string $path): bool`
  - `url(string $disk, string $path): ?string`
- `Support\FilesystemMediaStorage` uses Laravel's `Storage` facade and
  `putFileAs()` for streaming writes.
- Bound in `CatalogServiceProvider::registerModule()`.
- Catalog-owned for now; extract to Core only when a second module needs it.

Data objects:

- `Data\StoredMediaData`: `disk`, `path`, `originalName`, `mimeType`, `size`,
  `width`, `height`.
- `Data\AddProductMediaData`: `UploadedFile $file`,
  `?int $productVariantId = null`, `?string $altText = null`,
  `bool $isPrimary = false`.
- `Data\UpdateProductMediaData`: `?string $altText` (required argument that may
  be `null`; `null` clears the value), `?bool $isPrimary = null` is **not**
  included because primary selection is a separate contract.
- `Data\RepositionProductMediaData`: `?int $productVariantId`, `array $mediaIds`.

Contracts and actions:

| Contract | Action | Responsibility |
|---|---|---|
| `FindsProductMedia` | `FindProductMedia` | Find one media row; list a product or variant gallery. |
| `AddsProductMedia` | `AddProductMedia` | Validate, store the file, create the row and apply primary rules. |
| `UpdatesProductMedia` | `UpdateProductMedia` | Update `alt_text`; `null` clears it. |
| `SetsPrimaryProductMedia` | `SetPrimaryProductMedia` | Set one row primary inside its gallery and clear the previous primary. |
| `DeletesProductMedia` | `DeleteProductMedia` | Delete the row, promote the next primary and delete the stored file after commit. |
| `RepositionsProductMedia` | `RepositionProductMedia` | Validate and normalize a complete gallery ordering. |

Detailed action rules:

`AddProductMedia`:

1. Resolve the product from the database; throw `ModelNotFoundException` if it
   does not exist.
2. If `productVariantId` is present, resolve the variant and require it to
   belong to the product. Reject cross-product variants with
   `ValidationException`.
3. Validate the upload server-side: image type allowlist, MIME, extension,
   maximum size and maximum dimensions. Client-side Filament rules are not
   trusted.
4. Normalize `alt_text`: trim, strip tags, empty string becomes `null`, maximum
   255 characters.
5. Store the file through `MediaStorage`.
6. In a transaction with the gallery locked, append the row at the end of the
   gallery. Set `is_primary = true` when requested or when the gallery is empty;
   clear any previous primary in that gallery.
7. If the database write fails, delete the just-stored file before rethrowing so
   the failure does not leave an orphan.

`UpdateProductMedia`:

- Locks the row, updates only `alt_text` and `updated_at`.
- Never changes ownership, variant association, disk, path or primary state.

`SetPrimaryProductMedia`:

- Locks the media row and its gallery rows.
- Verifies the row still exists and is in the expected gallery.
- Unsets `is_primary` for all rows in that gallery and sets the target.
- Idempotent when the target is already primary.

`DeleteProductMedia`:

- Locks the row, captures `disk` and `path`, deletes the row, and promotes the
  next image in the same gallery when the deleted row was primary.
- After commit, calls `MediaStorage::delete()` best-effort. A failed file delete
  is logged/reported and does not fail the request.
- A missing row throws `ModelNotFoundException`.
- Deleting a product or variant through a database cascade removes media rows
  but cannot run file cleanup. This is a documented limitation; a future
  `catalog:prune-media` command will reconcile storage against the database.

`RepositionProductMedia`:

- Accepts a product, an optional variant and the complete, duplicate-free ID set
  for exactly one gallery.
- Rejects unknown, duplicate, missing and cross-gallery IDs with
  `ValidationException`.
- Locks the gallery rows and assigns positions `0..n-1` in the supplied order.
- Leaves the other gallery and all non-media records untouched.

Variant association is immutable after upload in the first release. Moving an
image between galleries requires deleting and re-uploading it. This avoids
cross-gallery primary and ordering edge cases.

### 7. Filament administration

Recommended UI: one `MediaRelationManager` on the product edit page, with a
gallery selector.

- Tabs: `Product` plus one tab per product variant. A tab is the gallery
  selector; no separate filter is required.
- The selected tab scopes the table query to `product_variant_id IS NULL` or
  `product_variant_id = <variant>`.
- Columns: image preview (`ImageColumn` with the row's disk), alt text, variant
  label, primary icon, position, size and created date.
- Header action `Upload image`:
  - `FileUpload::make('file')` with `->image()`, accepted types, max size,
    `->storeFiles(false)` and `->imageEditor(false)`.
  - `Select` for the optional variant, limited to the product's variants.
  - `TextInput` for alt text.
  - `Toggle` for primary.
  - The action callback calls `AddsProductMedia`; Filament never writes the
    model directly.
- Row action `Edit alt text`: calls `UpdatesProductMedia`.
- Row action `Set primary`: calls `SetsPrimaryProductMedia`, hidden or disabled
  for the current primary.
- Row action `Delete`: a Filament `DeleteAction` whose `using()` callback calls
  `DeletesProductMedia`. It is not a direct model delete.
- Reordering: explicit `Move up` / `Move down` row actions that compute the new
  complete gallery order and call `RepositionsProductMedia`. This keeps every
  write behind a contract and is testable. Filament's built-in `reorderable()`
  writes positions directly and is therefore not used in the first release.
- Authorization: every action declares `->authorize('manageMedia')` or checks
  `ProductMediaPolicy`; the relation manager is visible only when the user can
  manage media.
- `FileUpload` must not store the file itself. `storeFiles(false)` ensures the
  contract controls storage and the database row.
- `ImageColumn::disk()` must read the row's `disk` value, not a global default.

No variant edit page exists today. Variant galleries are managed from the
product edit page tabs, which avoids adding a second administration surface.

### 8. Authorization

- `ProductPolicy::manageMedia(User $user, Product $product): bool` returns
  `$user->is_admin`.
- `ProductMediaPolicy`:
  - `viewAny`, `view`, `create`, `update`, `delete`, `deleteAny`, `setPrimary`
    and `reorder` require `$user->is_admin`.
  - Record-level checks additionally verify the media row belongs to a product
    the user may manage.
- Both policies are registered in `CatalogServiceProvider::bootModule()`.
- Domain actions do not call `Gate`; they are authorization-agnostic and rely on
  the Filament/HTTP layer, matching the existing Catalog contract pattern.
- Filament actions declare explicit authorization. Direct Livewire mounting is
  covered by policy checks and by the panel's admin access gate.

### 9. Security and validation requirements

Upload validation:

- Allowed MIME types: `image/jpeg`, `image/png`, `image/webp`, `image/gif`.
- Allowed extensions: `jpg`, `jpeg`, `png`, `webp`, `gif`.
- Reject SVG, HTML, PHP and other executable or script-capable formats.
- Maximum size: 5 MB by default, configurable.
- Maximum dimensions: 8000 × 8000 by default.
- Require a valid image payload (`image` rule / `getimagesize`), not only a
  client-supplied MIME type or extension.
- Never trust the original filename, extension or client MIME type.
- Generate the stored filename and extension server-side.
- Store outside the web root under `storage/app/public`; serve only through the
  `storage:link` symlink or an S3 public URL.
- Never accept an arbitrary disk or path from request input.
- Do not allow private disks in the first release; `allowed_disks` is an
  explicit allowlist.

Data validation:

- `alt_text` is a nullable string of at most 255 characters, trimmed and
  stripped of tags. Filament/Blade escape it on output.
- `product_variant_id` must reference a variant owned by the product.
- `position` and `is_primary` are never accepted directly from request input;
  they are derived by contracts.
- Gallery reordering requires the complete ID set for that gallery.

Operational security:

- Product images are public by design. Do not store private customer documents
  in `product_media`.
- EXIF metadata, including GPS coordinates, is not stripped in the first release
  because no image-processing extension is installed. Document this limitation
  and require a future conversion step before accepting untrusted photography at
  scale.
- File deletion is best-effort and logged. Orphan files are a storage-cost and
  privacy concern, not a data-integrity concern, because the database row is
  removed first.

### 10. Transactional consistency and failure handling

- Upload order: validate → store file → database transaction. If the database
  transaction fails, delete the stored file and rethrow.
- Delete order: database transaction (row + primary promotion) → after-commit
  file deletion. If file deletion fails, log and keep the successful database
  deletion.
- Primary and reorder operations lock the affected gallery rows with
  `lockForUpdate()`.
- The PostgreSQL partial unique indexes prevent two primaries per gallery even
  under a race that bypasses the application lock.
- No file operation runs inside a database transaction that can be rolled back
  after the file is already visible.
- Concurrent uploads to the same gallery are serialized by the gallery lock when
  positions or primary state are assigned.
- Deleting a product or variant through a cascade leaves files behind; the
  future prune command is the reconciliation mechanism.

### 11. Interactions with existing models and modules

- `Product` gains a `media()` relation plus `primaryMedia()` and `gallery()`
  helpers.
- `ProductVariant` gains a `media()` relation plus `primaryMedia()`,
  `resolvedPrimaryMedia()` and `gallery()` helpers.
- No column is added to `products` or `product_variants` except the additive
  unique index on `product_variants (id, product_id)` required by the composite
  FK.
- Inventory is untouched. Media does not reference inventory, stock or
  movements.
- Attributes and options are untouched. Media does not participate in
  `variant_key` derivation.
- Pricing remains out of scope and no price column is added to Catalog.
- Product deletion cascades media rows through the foreign key. Variant deletion
  cascades variant media rows. Neither path performs file cleanup.
- The existing `ProductPolicy` and Filament product resource gain a media
  relation manager; existing product actions and forms remain unchanged.

### 12. Test requirements

Business tests (Pest, SQLite in-memory with `Storage::fake('public')`):

- `ProductMediaFoundationTest`
  - Schema columns, indexes and foreign keys.
  - `Product::media()`, `ProductVariant::media()` and factory relationships.
  - Product deletion cascades media rows.
  - Variant deletion cascades variant media rows.
  - Composite FK rejects a variant from another product.
  - `MediaStorage` contract binding.
- `ProductMediaUploadTest`
  - Stores a valid image on the configured disk and persists all metadata.
  - Product-level and variant-level uploads.
  - First image becomes primary; explicit primary clears the previous one.
  - Alt text trimming, tag stripping and length validation.
  - Rejects unsupported MIME, extension, oversize file and oversized dimensions.
  - Rejects a variant from another product.
  - Rejects an unknown product.
  - Deletes the stored file when the database write fails.
  - Uses a real image fixture, not `UploadedFile::fake()->image()`, because that
    helper requires GD, which is not installed.
- `ProductMediaUpdateTest`
  - Updates alt text and clears it with `null`.
  - Does not change ownership, variant association, disk or path.
- `ProductMediaPrimaryTest`
  - Sets primary within the product gallery only.
  - Sets primary within a variant gallery only.
  - Is idempotent for an already-primary row.
  - Rejects cross-gallery and cross-product targets.
  - Deleting the primary promotes the next image by position then id.
- `ProductMediaDeletionTest`
  - Deletes the row and the stored file.
  - Promotes the next primary when needed.
  - Logs but does not fail when the file is already missing.
  - Throws `ModelNotFoundException` for a missing row.
- `ProductMediaRepositionTest`
  - Reorders a complete gallery and normalizes positions.
  - Rejects duplicate, missing, unknown and cross-gallery IDs.
  - Leaves the other gallery untouched.
- `ProductMediaResolutionTest`
  - Product primary and first-image fallback.
  - Variant primary, then product primary, then product first image, then null.
  - Variant gallery falls back to the product gallery when empty.
- `ProductMediaAuthorizationTest`
  - `manageMedia`, `ProductMediaPolicy` abilities, guest/non-admin denial.

Filament tests:

- `ProductMediaRelationManagerTest`
  - Lists only the selected gallery.
  - Upload action stores through the contract and persists the row.
  - Upload validation errors are surfaced.
  - Alt-text editing, set-primary, delete and move-up/move-down call the
    contracts.
  - Non-admin users cannot access the relation manager or its actions.
  - HTTP access to the product edit page remains admin-only.

PostgreSQL verification:

- `migrate:fresh` succeeds with `z11`.
- `z11` rolls back and re-applies cleanly.
- The partial unique indexes exist on PostgreSQL.
- A direct insert of a second primary in the same gallery is rejected by the
  database index.
- The composite FK rejects a cross-product variant media row.
- Existing product, variant, attribute, Inventory and movement rows are
  preserved.

Test fixtures:

- Check in a tiny valid PNG and JPEG under
  `src/tests/Fixtures/images/` or embed a base64 1×1 PNG.
- Build uploads with `UploadedFile::fake()->createWithContent()` or by
  constructing `UploadedFile` directly with an explicit MIME type.
- Do not use `UploadedFile::fake()->image()`; it depends on GD.

### 13. Acceptance criteria

- `product_media` exists with the proposed columns, indexes, composite FK and
  PostgreSQL partial unique indexes.
- Catalog owns the media capability and `MediaStorage` isolates filesystem
  access.
- Product-level and variant-level galleries are supported.
- At most one primary image exists per gallery; the first image is primary by
  default; deleting the primary promotes the next image.
- Variant gallery reads fall back to the product gallery as specified.
- Ordering is gallery-scoped, normalized and validated against the complete ID
  set.
- Uploads are validated server-side for type, MIME, size and dimensions.
- Stored paths and filenames are generated server-side; original names are
  sanitized; no user-controlled disk or path is accepted.
- File and database consistency is handled as specified for upload and delete
  failures.
- Filament administration provides upload, alt-text editing, set-primary,
  delete and move-up/move-down through Catalog contracts only.
- `ProductPolicy::manageMedia` and `ProductMediaPolicy` restrict all media
  administration to admins.
- No new Composer dependency, GD/Imagick requirement or Spatie/Intervention
  package is introduced.
- Existing product, variant, attribute, Inventory and movement tests remain
  green.
- Catalog focused tests, Pint and module PHPStan pass.
- PostgreSQL migration, rollback and re-apply are verified.
- Documentation and the decision are updated to record the implemented and
  verified state.

### 14. Risks and open questions

Risks:

1. Orphan files after product/variant cascade deletes or failed after-commit
   cleanup. Mitigation: documented limitation plus a future prune command.
2. EXIF/GPS metadata is retained in uploaded originals. Mitigation: document the
   privacy limitation; add a conversion step only after a dependency and runtime
   change are approved.
3. Local `public` disk requires `storage:link` and a persistent Docker volume.
   Mitigation: add the command to setup documentation and verify the link in the
   implementation task.
4. S3 public URL configuration varies by provider. Mitigation: keep the disk
   configurable and verify with the configured `AWS_URL`/bucket policy.
5. SQLite cannot enforce the partial unique indexes. Mitigation: application
   enforcement plus PostgreSQL verification.
6. Filament's built-in drag reorder bypasses contracts. Mitigation: use explicit
   move actions in the first release.
7. Large images consume bandwidth and storage. Mitigation: 5 MB limit and
   original-size serving; resizing is deferred.
8. Concurrent primary/reorder writes. Mitigation: row locks plus PostgreSQL
   partial unique indexes.

Resolved questions:

1. Confirmed the default maximum upload size (5 MB) and the allowed image
   formats (JPEG, PNG, WebP, GIF; SVG excluded).
2. Confirmed that variant-specific images are managed from product-page gallery
   tabs rather than a separate variant page.
3. Confirmed that variant galleries fall back to the product gallery when empty.
4. Confirmed that moving an image between galleries requires delete and re-upload.
5. Confirmed that thumbnails/conversions and EXIF stripping are deferred.
6. The production S3-compatible provider and public URL strategy remain a
   deployment follow-up.

## Phase B — Implementation (complete)

### Step 1 — Schema and storage foundation

- Add `src/config/catalog.php` and merge it in the Catalog provider.
- Add `z11_create_product_media_table.php` with the composite FK, indexes and
  driver-guarded PostgreSQL partial unique indexes.
- Add `ProductMedia` model, `ProductMediaFactory` and relationships on `Product`
  and `ProductVariant`.
- Add `MediaStorage`, `FilesystemMediaStorage` and `StoredMediaData`.
- Bind the storage contract.
- Add business tests for schema, storage and relationships.

### Step 2 — Domain actions

- Add find/add/update/set-primary/delete/reposition contracts, actions and data
  objects.
- Add `ProductMediaPolicy` and `ProductPolicy::manageMedia`.
- Add upload, primary, delete, reorder, resolution and authorization tests.
- Verify failure cleanup with a fake storage disk.

### Step 3 — Filament administration

- Add `MediaRelationManager` with gallery tabs, upload/edit/set-primary/delete/
  move actions and dynamic `ImageColumn` disk.
- Add Filament tests for listing, upload, validation, editing, primary, deletion,
  reordering and access control.
- Do not register bulk delete actions.

### Step 4 — Verification and documentation

- Run Catalog focused tests, Inventory focused tests and the full suite.
- Run Pint on changed files and PHPStan on Catalog.
- Verify PostgreSQL `migrate:fresh`, rollback/re-apply, partial indexes and
  composite FK.
- Verify `storage:link` locally and document the setup step.
- Update `PROJECT_STATE.md`, `WORK_LOG.md`, `HANDOFF.md`,
  `modules/CATALOG.md` and DEC-022.

## Commit message (pending authorization)

`feat(catalog): add product media and galleries`

Do not commit, merge or push without authorization.

## Verification status

Verified on 2026-11-13:

- Schema: `product_media` created with the expected columns, indexes, composite
  FK and PostgreSQL partial unique indexes.
- Foundation and relationships: `ProductMedia` model/factory, `Product::media()`,
  `Product::allMedia()`, `ProductVariant::media()`, `gallery()` and primary
  resolution tests pass.
- Domain actions: add, update, set-primary, delete, reposition and find actions
  pass, including upload validation, cleanup, gallery isolation, primary
  promotion and complete-set reordering.
- Authorization: `ProductPolicy::manageMedia` and `ProductMediaPolicy` are
  admin-only; guest and non-admin access denied.
- Filament: `MediaRelationManager` lists product and variant galleries, uploads,
  edits alt text, sets primary, deletes and moves media through contracts.
- Catalog focused suite: 337 passed (1073 assertions).
- Task 006 media tests: 72 passed (211 assertions) — 60 non-Filament (149
  assertions) and 12 Filament (62 assertions).
- Inventory focused suite: 52 passed (174 assertions).
- Full suite: 420 passed, 3 known pre-existing failures
  (`AuthenticationTest`, `TwoFactorChallengeTest`, `PostCreatePageTest`).
- Pint: 39 changed files pass.
- PHPStan (`app/Modules/Catalog` + `config/catalog.php`): no errors.
- PostgreSQL `migrate:fresh` succeeded; `z11` rolled back and re-applied cleanly.
  Direct inserts confirmed both partial unique indexes and the composite
  cross-product FK are enforced.
- `git diff --check`: clean.

Deferred follow-ups: thumbnails/conversions, EXIF stripping,
`catalog:prune-media`, local `storage:link` setup verification and the production
S3-compatible provider/public URL strategy.

The implementation is **not committed**; commit only with authorization.
