# Inventory Module

## Purpose

Inventory owns stock quantities, stock adjustment operations and append-only
stock movement history. It records stock against Catalog products without
duplicating product information.

## Responsibilities

- Track stock quantities per product and stock location.
- Adjust stock through explicit, validated actions.
- Record every successful adjustment as an immutable stock movement.
- Provide read-only movement history retrieval and reporting.
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
- Stock movements snapshot the product and location at the time of adjustment
  and store the signed delta, resulting quantity, reason, optional reference and
  creation timestamp. There is no update or delete operation for movements.
- Stock updates and movement creation happen in one database transaction with
  row locking.
- `StockMovementReadService` provides read-only pagination (newest first),
  product, reason and inclusive date-range filtering, per-product history and
  daily increase/decrease summaries. It never writes to inventory or movement
  records.
- Summaries are computed from recorded movements rather than stored aggregates,
  so they cannot drift from the audit trail.
- Reservations, low-stock thresholds, location management, per-location
  reporting and HTTP/UI layers are not implemented yet.
