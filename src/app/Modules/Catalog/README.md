# Catalog Module

## Purpose

Catalog owns product information and product organisation. It references no
inventory quantities, cart state, orders or payments.

## Responsibilities

- Create, retrieve and update products.
- Manage categories and the category-product relationship.
- Own product variants and the default variant created with every product.
- Generate collision-safe slugs through `Support\UniqueSlug` and collision-safe
  SKUs through `Support\UniqueSku`.
- Validate product and category input.

## Depends On

- Core

## Used By

- Inventory (variant references only).
- Future pricing, cart, checkout and order workflows.

## Notes

- Products represent `name`, `slug`, `description` and `is_active`, plus a
  separate publication `status`.
- Every product owns at least one `ProductVariant`. The variant is the canonical
  purchasable unit and carries a globally unique (nullable) SKU.
- `CreateProduct` creates the product and its default variant in one transaction,
  rejecting duplicate SKUs and generating `SKU-{productId}` when none is given.
- Existing products were backfilled idempotently with default variants.
- Product retrieval supports ID and slug lookup through `FindsProducts`;
  unknown values raise `ModelNotFoundException`.
- `UpdateProduct` supports partial updates: omitted fields keep their stored
  values, and renames regenerate a unique slug while unchanged names keep the
  existing slug.
- Categories support nullable parents with `nullOnDelete` promotion to root and
  a many-to-many relationship with products.
- Filament product and category administration exists; variant administration,
  attributes, media and pricing are not implemented yet.
