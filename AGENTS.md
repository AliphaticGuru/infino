# Infino — Agent Instructions

## Project Identity

Infino is a modular-monolith e-commerce platform built with Laravel.

The repository root contains the infrastructure and project documentation.
The Laravel application lives inside `src/`.

## Mandatory Context

Before making changes, read:

1. docs/agent/README.md
2. docs/agent/PRODUCT_BLUEPRINT.md
3. docs/agent/PROJECT_STATE.md
4. docs/agent/ROADMAP.md
5. docs/agent/DECISIONS.md
6. docs/agent/HANDOFF.md

Read relevant module specifications and task documents.

## Repository Rules

- Inspect Git status before modifying files.
- Never discard existing user changes without permission.
- Never assume documentation proves implementation.
- Inspect existing code before introducing new abstractions.
- Preserve established architectural decisions.
- Avoid unrelated modifications.
- Do not change dependencies without approval.
- Do not overwrite existing documentation without reviewing it.

## Technical Rules

- Laravel 13.
- PHP runtime must be verified.
- Livewire 4.
- Filament 5.
- PostgreSQL.
- Redis.
- Docker Compose.
- Pest 4.

Follow the existing Laravel instructions in src/AGENTS.md
when working inside the application.

Use Docker Compose for application commands.

## Module Architecture

Infino uses a modular monolith.

Modules own their business capabilities and data.
Avoid unnecessary cross-module coupling.

Shared infrastructure belongs in Core/Foundation
where appropriate.

Do not introduce microservices without explicit approval.

## Task Execution

1. Inspect the actual implementation.
2. Identify the relevant task and acceptance criteria.
3. Plan the smallest coherent change.
4. Implement incrementally.
5. Write or update tests.
6. Run relevant verification.
7. Update shared project documentation.

## Documentation Protocol

After completing work, update:

- PROJECT_STATE.md
- WORK_LOG.md
- HANDOFF.md

Update PRODUCT_BLUEPRINT.md when product scope changes.

Update DECISIONS.md when an architectural decision is made.

Do not mark incomplete or unverified work as completed.

## Git Protocol

- Never reset or overwrite uncommitted user work.
- Use task-specific feature branches.
- Do not commit unless authorized.
- Do not merge branches without authorization.
- Inspect conflicts before integration.

## Handoff Requirements

Every completed task must report:

- Objective
- Implementation
- Files changed
- Tests executed and actual results
- Outstanding issues
- Next recommended action

The repository is the source of truth.
Conversation history is supplementary context.