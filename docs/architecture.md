# Architecture

QueryNova is a modular monolith inside one WordPress plugin. The main file `querynova.php` only defines constants, loads Composer, and registers activation hooks. `QueryNova\Core\Plugin` is the composition root.

## Layers

Presentation (REST, wp-admin, WP-CLI) calls application services. Application services orchestrate domain rules. Infrastructure implements database, HTTP, cache, and provider ports. Domain types do not know which SERP vendor, admin screen, or React component is in use.

## Modules

Each module implements `QueryNova\Core\Contracts\ModuleInterface`: name, version, dependencies, register, boot, hooks, routes, jobs, capabilities, migrations, admin pages, and health. `ModuleRegistry` rejects missing and circular dependencies and boots modules so dependencies start first. Optional module failures are recorded and logged. Core keeps running.

Safe mode (`QUERYNOVA_SAFE_MODE` or the `querynova_safe_mode` option) loads only the core module.

## Dependency injection

`ServiceContainer` is an explicit container. Services are registered with `set`, `singleton`, or `factory`. There is no `getInstance()` service locator. Constructor injection is the default. See `docs/adr/ADR-002-dependency-injection.md`.

## Public hooks

Internal events are dispatched in-process and mirrored to `querynova_event_{name}`. Documented extension points use the `querynova_` prefix:

- `querynova_before_analysis`
- `querynova_after_analysis`
- `querynova_opportunity_score_inputs`
- `querynova_recommendation_generated`
- `querynova_product_seo_data`

A nonce is never treated as authorization. Private REST routes under `querynova/v1` use `permission_callback` with QueryNova capabilities.

## Provenance

`MetricValue` carries a value or an explicit unavailable state, plus source, provider, methodology, methodology version, and confidence. Kinds are `MEASURED`, `ATTRIBUTED`, `ESTIMATED`, and `UNAVAILABLE`. Estimated values are labeled Estimated. Missing provider data is unavailable, not zero.
