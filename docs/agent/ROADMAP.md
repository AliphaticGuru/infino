# Infino — Development Roadmap

**Status:** Initial proposed sequence  
**Last reviewed:** 2026-11-11

This roadmap is a working plan. Priorities may change after test-baseline and product-scope reviews.

## Stage 0 — Shared agent workflow
**Status:** In progress

- Establish root agent instructions.
- Establish shared project documentation.
- Establish task and handoff conventions.
- Verify that a fresh agent can follow the documentation.
- Ensure agents preserve uncommitted work.

Completion criteria:
- Required project documents exist.
- Instructions are consistent across agents.
- Current state and next task are documented.
- A fresh agent can identify what to inspect and what not to change.

## Stage 1 — Foundation and application health
**Status:** Needs verification

- Verify Docker Compose configuration.
- Resolve or explain environment-variable warnings.
- Inspect Core module infrastructure.
- Verify module discovery, dependency validation and provider loading.
- Establish a reliable automated test baseline.
- Review existing Identity foundation and its tests.

Completion criteria:
- Module loading behavior is covered by tests.
- The application boots reliably.
- Relevant tests pass with results recorded.
- No unresolved critical configuration issue is hidden.

## Stage 2 — Identity foundation
**Status:** Foundation committed; current state needs verification

- Verify registration and authentication.
- Verify password reset and email verification.
- Verify profile and security settings.
- Verify two-factor authentication behavior.
- Confirm the custom User model is used consistently.
- Review authorization requirements.

Do not rebuild existing functionality without first auditing it.

## Stage 3 — Catalog MVP
**Status:** In progress — product/category foundations, retrieval/update, publication lifecycle, product-category assignment, variant administration, the Filament admin layer and the variant-first foundation are complete; attributes/options, media and pricing remain

Suggested sequence:
1. ~~Verify Catalog module registration.~~ Complete.
2. ~~Verify and execute product migration.~~ Complete.
3. ~~Verify Product model and factory.~~ Complete.
4. ~~Define product creation validation.~~ Complete.
5. ~~Add product creation tests.~~ Complete.
6. ~~Add product update and retrieval behavior.~~ Complete: find by ID/slug, partial updates and rename slug regeneration verified.
7. ~~Define product publication/status behavior.~~ Complete: draft/published/archived enum with guarded transitions (DEC-017).
8. ~~Add categories and category relationships.~~ Complete; product-category pivot, parent-child hierarchy and assignment actions verified.
9. ~~Specify variants before implementation.~~ Complete: variant-first foundation, default variants and variant-keyed inventory (DEC-019). Brands and attributes remain open.
10. ~~Integrate the administrative workflow.~~ Complete for products, categories and product variants.
11. ~~Add variant administration (multiple variants, editing, deactivation, positions).~~ Complete: retrieval, creation, update, activation/deactivation, product-scoped positioning and Filament relation-manager administration (DEC-020, 2026-11-11).
12. Add attributes/options, then media, then the Pricing module.

Completion criteria:
- Product persistence is tested.
- Product validation and slug behavior are defined.
- Catalog owns its data and does not absorb inventory or order responsibilities.
- Module-specific tests run reliably.

## Stage 4 — Commerce foundations
**Status:** In progress — Inventory stock foundation complete

Candidate areas:
- ~~Inventory.~~ Stock foundation implemented: variant/location stock records, validated adjustments and variant-keyed movement history.
- Pricing.
- Cart and wishlist.
- Checkout.
- Orders.

Define data ownership and lifecycle rules before implementing cross-module workflows.

## Stage 5 — Transaction and fulfilment capabilities
**Status:** Planned

Candidate areas:
- Payments.
- Shipping.
- Order fulfilment.
- Notifications.
- Refund and cancellation workflows.

Provider choices and integration requirements must be decided first.

## Stage 6 — Customer experience and discovery
**Status:** Planned

Candidate areas:
- Customer dashboard.
- Search and filtering.
- Reviews and ratings.
- Product recommendations.
- Customer notifications.

## Stage 7 — Administration and reporting
**Status:** Planned

- Filament administrative workflows.
- Operational dashboards.
- Sales and order reporting.
- Inventory reporting.
- Administrative audit trails.

Domain modules should own their business logic. Administration should not become a second source of business rules.

## Stage 8 — Production readiness
**Status:** Planned

- Security review.
- Performance and query review.
- Queue and cache strategy.
- Logging and monitoring.
- Backup and recovery.
- Deployment automation.
- Environment and secrets management.
- Operational documentation.

## Planning rules

- Do not start a later stage while a blocking dependency remains unresolved.
- Prefer small, verifiable increments.
- Avoid speculative abstractions.
- Do not create a module solely because it appears in the proposed module map.
- Update the roadmap when priorities or scope change.
