# Infino — Project State

**Last inspected:** 2026-11-10
**Current branch at inspection:** `feature/catalog`
**Latest commit at inspection:** `a349034` — `feat(catalog): implemented catalog product retrieval & update`

This document records observed repository state. Reinspect the code before relying on it.

## 1. Verified environment

| Component | Verified version / state |
|---|---|
| PHP CLI in Docker | 8.5.7 (verified 2026-11-09) |
| Laravel | 13.22.0 (verified 2026-11-09) |
| Livewire | 4.3.3 |
| Filament | 5.7.4 |
| Pest | 4.7.5 |
| Database direction | PostgreSQL |
| Cache direction | Redis |
| Web server direction | Nginx |
| Local orchestration | Docker Compose |

Livewire, Filament and Pest versions were reported by earlier project inspection and should be rechecked when upgrading dependencies.

## 2. Repository structure

- Repository root: infrastructure, Docker configuration and shared agent documentation.
- `src/`: Laravel application.
- `src/app/Modules/`: business modules.
- `src/config/modules.php`: module path and scaffold directory definitions.
- `docs/agent/`: shared project memory for AI agents and developers.

The Compose file is `compose.yaml`.

## 3. Existing modules

### Core
**Status:** Present; implementation needs continued verification.

Observed files include:
- `Core/Providers/BaseModuleServiceProvider.php`
- `Core/Providers/CoreServiceProvider.php`
- `Core/Models/BaseModel.php`
- `Core/Data/Data.php`
- `Core/module.json`

Core is declared as a dependency of Identity and Catalog.

### Identity
**Status:** Foundation committed; current working-tree changes need verification.

Observed capabilities/files include:
- User model.
- Registration, authentication and logout actions.
- Contracts and data objects.
- User factory and migrations.
- Identity service provider.
- Fortify integration and feature tests.

The current working tree includes changes to the Identity User model, Fortify actions, validation concerns and authentication-related tests.

Do not assume the current tests pass.

### Catalog
<<<<<<< develop
**Status:** Product and category foundations complete and verified with focused tests.
=======
**Status:** Product and category foundations complete; product retrieval/update and publication lifecycle verified with focused tests.
>>>>>>> local

Observed files include:
- `CatalogServiceProvider`
- `module.json`
<<<<<<< develop
- Product: `CreateProduct` action, `CreatesProducts` contract, `CreateProductData`, `Product` model, `ProductFactory`, `create_products_table.php` migration.
=======
- Product: `CreateProduct`, `FindProduct`, `UpdateProduct` and `TransitionProductStatus` actions; `CreatesProducts`, `FindsProducts`, `UpdatesProducts` and `TransitionsProductStatus` contracts; `CreateProductData` and `UpdateProductData`; `Product` model; `ProductFactory`; `create_products_table.php` and `z_add_status_to_products_table.php` migrations.
- Product status: `Support/ProductStatus` backed enum (`draft`, `published`, `archived`).
>>>>>>> local
- Category: `Category` model, `CategoryFactory`, `create_categories_table.php`, `CreateCategory` and `UpdateCategory` actions, `CreatesCategories` and `UpdatesCategories` contracts, `CreateCategoryData` and `UpdateCategoryData`.
- Product-category pivot: `create_z_category_product_table.php`.
- Shared slug helper: `Support/UniqueSlug.php`.

The product implementation represents `name`, `slug`, `description`, `is_active` and `status`. Categories add `parent_id`, `name`, `slug`, `description` and `is_active`.

Verified on 2026-11-09:
- Catalog migrations are discovered through `BaseModuleServiceProvider::loadModuleMigrations()` and execute under `RefreshDatabase` and PostgreSQL.
- Product and category factory resolution is explicit through `newFactory()`.
- Product and category contracts resolve through the module provider.
- Product and category creation persist expected fields.
- Validation requires a non-empty string name of at most 255 characters; descriptions are optional and nullable.
- Generated slugs are collision-safe (`name`, `name-2`, `name-3`, ...) with `product`/`category` fallbacks.
- Categories support nullable self-referencing parents with `nullOnDelete` promotion to root.
- Products and categories expose a many-to-many relationship through `category_product`.
- Category updates cover rename slug generation, parent reassignment and rejection of self/descendant parents.
- Focused tests: `ProductCreationTest.php` (9 tests, 28 assertions) and `CategoryFoundationTest.php` (17 tests, 58 assertions).

Verified on 2026-11-10 (publication lifecycle):
- Products carry a `status` backed enum with `draft`, `published` and `archived`.
- New products default to draft; existing rows were backfilled to draft by the migration.
- Draft products can be published or archived; published products can be unpublished or archived.
- Archived products can only return to draft through `restore`, then be published.
- Invalid transitions raise `ValidationException`; deleted products raise `ModelNotFoundException`.
- Lifecycle transitions preserve name, slug, description and `is_active`.
- `is_active` remains independent of publication status.
- `UpdateProduct` does not change the publication status.
- Focused tests: `ProductPublicationLifecycleTest.php` (21 tests, 46 assertions).

Remaining gaps:
<<<<<<< develop
- Product update, retrieval and publication lifecycle behavior are not implemented.
=======
>>>>>>> local
- Category retrieval/deletion actions and product-category assignment actions are not implemented.
- No publication lifecycle UI, API, Filament resource or Livewire component exists.
- Required first-release fields and variant support remain undecided.
- Names are not unique; duplicate names receive suffixed slugs.

### TestModule
**Status:** Present; purpose and intended lifecycle need verification.

## 4. Module loading

Observed in `src/app/Providers/ModuleServiceProvider.php`:
- `ModuleRepository` is registered as a singleton.
- `ModuleManager` is registered as a singleton.
- `$modules->load()` is called from the provider's `boot()` method.

Verified indirectly on 2026-11-09: the Catalog contract binding and migration loading were active during the focused Catalog tests.

Inspect the module infrastructure before changing loading behavior.

## 5. Git state at inspection

<<<<<<< develop
The working tree was not clean.

Tracked changes included:
- `compose.yaml` healthcheck fix.
- Catalog `CreateProduct` slug handling and `Product` factory resolution.
- Identity User model, factory and login route changes.
- Root `src/routes/web.php` changes.

Untracked files included:
- Root `AGENTS.md`, `CLAUDE.md`, and `docs/`.
- `src/app/Livewire/Pages/` and the post-create Blade view.
- `src/tests/Feature/Catalog/`.

The branch was `feature/catalog`. The latest commit was `7cdb745`.
=======
The repository was inspected on 2026-11-10.

- Current branch: `feature/catalog`.
- Latest commit: `a349034` — `feat(catalog): implemented catalog product retrieval & update`.
- The working tree contained the uncommitted product publication lifecycle implementation.

The Inventory commits (`2e71c91`, `24dca64`, `4532690`) are on `feature/inventory` and are not part of this branch. Inventory module code and tests are therefore absent here.
>>>>>>> local

**This is a historical snapshot, not a statement of the current Git state.** Always run `git status --short` before making changes.

## 6. Resolved environment warning

Docker Compose previously warned that `DB_USERNAME` and `DB_DATABASE` were unset.

Cause: the `db` healthcheck used `${DB_USERNAME}` and `${DB_DATABASE}`. Compose interpolates those from the host environment or a root `.env` file, not from a service's `env_file`. The repository has no root `.env`, so both values resolved to blank strings.

Fix: the healthcheck now reads the container's own variables with `pg_isready -U "$$POSTGRES_USER" -d "$$POSTGRES_DB"`. `docker compose config --quiet` produces no warnings and the `infino-db` container reaches the `healthy` state.

## 7. Test status

Baseline and post-change full-suite results were captured on 2026-11-09 using:

```bash
docker compose run --rm --no-deps cli php artisan test --compact
```

- Baseline at the start of Category foundation: 40 passed, 3 failed (109 assertions).
- After completing the Category foundation: 57 passed, 3 failed (167 assertions).
<<<<<<< develop
- Catalog focused suite: 26 passed, 86 assertions.
=======
- After Catalog product retrieval/update: 62 passed, 3 failed (172 assertions) — superseded by the lifecycle run below.
- After Catalog product publication lifecycle: 93 passed, 3 failed (253 assertions).
- Catalog focused suite: 62 passed, 172 assertions.
- `ProductPublicationLifecycleTest.php`: 21 passed, 46 assertions.
- PHPStan level 7 on `app/Modules/Catalog`: no errors.
- PostgreSQL: `z_add_status_to_products_table` applied; `status` is non-null with default `draft`; existing rows were backfilled to `draft`.

Results from `feature/inventory` (Inventory module is not present on this branch):
- Inventory foundation, movement history and movement read/reporting were verified there.
- Full suite on that branch reached 101 passed, 3 failed (314 assertions) before Catalog retrieval/update.
>>>>>>> local

Remaining failures:
1. `Tests\Feature\Auth\AuthenticationTest` — two-factor users are redirected to `/dashboard` instead of `/two-factor-challenge`.
2. `Tests\Feature\Auth\TwoFactorChallengeTest` — same two-factor challenge redirect failure.
3. `Tests\Feature\PostCreatePageTest` — page returns 500 because `layouts/app/sidebar.blade.php` reads `name` from a null authenticated user.

Likely causes:
- The custom `/login` route in the Identity module authenticates directly and bypasses Fortify's two-factor challenge handling.
- The post-create page is rendered for a guest while using an authenticated application layout.

Never report tests as passing unless the relevant command was executed and its result observed.

## 8. Immediate priorities

1. Restore Fortify two-factor challenge handling in the Identity login flow.
2. Decide whether the post-create page requires authentication or a guest-capable layout.
<<<<<<< develop
3. Add Catalog product retrieval and update behavior.
4. Add product-category assignment actions if the product workflows require them.
=======
3. Add product-category assignment actions if the product workflows require them.
4. Expose the product lifecycle through an authorised delivery layer when a UI milestone begins.
>>>>>>> local
5. Define required first-release product fields and variant direction before expanding the schema.
