# Catalog Module

## Purpose

Catalog owns product information and product organisation. It references no
inventory quantities, cart state, orders or payments.

## Responsibilities

- Create, retrieve and update products.
- Manage categories and the category-product relationship.
- Generate collision-safe slugs through `Support\UniqueSlug`.
- Validate product and category input.

## Depends On

- Core

## Used By

- Inventory (product references only).
- Future pricing, cart, checkout and order workflows.

## Notes

- Products represent `name`, `slug`, `description` and `is_active`.
- Product retrieval supports ID and slug lookup through `FindsProducts`;
  unknown values raise `ModelNotFoundException`.
- `UpdateProduct` supports partial updates: omitted fields keep their stored
  values, and renames regenerate a unique slug while unchanged names keep the
  existing slug.
- Categories support nullable parents with `nullOnDelete` promotion to root and
  a many-to-many relationship with products.
- Controllers, API endpoints, Filament resources and UI are not implemented yet.
- Product publication lifecycle, brands, attributes, variants and media remain
  undecided or unimplemented.
