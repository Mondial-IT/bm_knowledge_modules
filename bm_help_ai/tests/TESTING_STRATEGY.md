# BM Help AI — Test Strategy

## Scope

- Unit coverage only; no kernel or browser tests are required yet.
- Focus on deterministic behavior of services and form glue code.

## Goals

- Validate context capture (routes, roles, permissions, enabled modules).
- Validate help aggregation normalization for topics and hook_help output.
- Ensure the AI relevance stub preserves ordering for now.

## Future Extensions

- Add kernel tests to verify integration with the actual help topic manager.
- Add browser tests for the `/help/ai` form rendering and menu placement once UI stabilizes.
