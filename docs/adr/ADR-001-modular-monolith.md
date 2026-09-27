# ADR-001: Modular monolith

## Status

Accepted

## Decision

QueryNova ships as one WordPress plugin with explicit modules, not as microservices and not as a single procedural file. Modules declare dependencies. The registry boots them in order and isolates optional failures.

## Consequences

A new feature is a module with its own health check. Core SEO can run when backlinks or a future cloud service cannot. Cross-module calls go through the container, events, or public `querynova_` hooks.
