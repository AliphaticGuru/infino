# Inventory Module

## Purpose

Inventory owns stock quantities and stock adjustment operations. It records
stock against Catalog products without duplicating product information.

## Responsibilities

- Track stock quantities per product and stock location.
- Adjust stock through explicit, validated actions.
- Prevent stock quantities from becoming negative.

## Depends On

- Core
- Catalog (product references only)

## Used By

- Future cart, checkout and order workflows.

## Notes

- Inventory records are keyed by product and location, so a product may have
  multiple inventory records when multiple stock locations are used.
- Inventory references Catalog products through a foreign key and belongs-to
  relationship. Catalog does not depend on Inventory.
- Stock movements, reservations, low-stock thresholds and reporting are not
  implemented yet.
