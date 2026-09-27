# ADR-002: Dependency injection

## Status

Accepted

## Decision

Use a small explicit `ServiceContainer` with constructor injection. Do not adopt a third-party container and do not use `getInstance()` as the architecture.

## Consequences

Services are registered in the composition root or in a module `register()` method. Tests construct collaborators directly. Circular service graphs fail fast. PHP-Scoper is not applied to this container because it is first-party code under the `QueryNova` namespace.
