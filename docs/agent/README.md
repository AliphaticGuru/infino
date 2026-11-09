# Infino — Shared Agent Workspace

This directory contains the shared project memory for all
developers and AI coding agents working on Infino.

## Source of Truth

| Document | Responsibility |
|---|---|
| PRODUCT_BLUEPRINT.md | Complete application scope |
| PROJECT_STATE.md | Verified implementation status |
| ROADMAP.md | Development sequence |
| DECISIONS.md | Architectural decisions |
| WORK_LOG.md | Historical development record |
| HANDOFF.md | Immediate continuation instructions |

## Directories

- architecture/: Architecture documentation.
- modules/: Module specifications.
- tasks/active/: Current implementation tasks.
- tasks/backlog/: Future implementation tasks.

## Operating Protocol

Before work:
1. Read the project instructions.
2. Read the product blueprint.
3. Inspect current project state.
4. Read the active handoff.
5. Inspect the actual code and Git state.

After work:
1. Verify implementation.
2. Run relevant tests.
3. Update project state.
4. Record work performed.
5. Prepare the next handoff.

## Status Definitions

- Completed: Implemented and verified.
- In progress: Currently being implemented.
- Blocked: Cannot proceed because of an unresolved dependency.
- Planned: Approved or proposed future work, not implemented.
- Deferred: Intentionally postponed.
- Needs verification: Implementation exists but has not been sufficiently verified.