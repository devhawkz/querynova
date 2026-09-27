# Adding a feature

1. Create `src/Modules/{Name}/` with Domain, Application, Infrastructure, and Presentation directories when the module is more than a thin adapter.
2. Add a module class extending `AbstractModule`. Declare `getDependencies()`. Core is required. Mark the module optional unless the site cannot function without it.
3. Register services in `register()` on the shared container. Do not construct infrastructure inside a hook callback.
4. Register WordPress hooks through `HookRegistrar` and a `HookSubscriberInterface`. Business rules stay in an application service.
5. Register REST routes through `RestRegistrar` with a capability. Controllers validate, authorize, call the use case, and serialize.
6. Add capabilities only when the role map needs a new one. Reuse the existing QueryNova capabilities when they fit.
7. Add a new migration class if the schema changes. Do not edit `InitialSchemaMigration` after it has shipped.
8. Long work goes through `JobRegistrar` and `JobRunner::enqueue()` with an idempotency key.
9. Log with a channel, and include module and provider context. Do not log secrets.
10. Implement `healthCheck()`. Degraded is for a failed provider. Unhealthy is for a broken local dependency.
11. Add PHPUnit coverage for the domain rule and for the failure path. Do not call a live provider.
12. Add the admin view under `assets/admin/features/{name}` when the screen is part of the feature. Keep calculations on the server.
13. Update `docs/modules.md`, `docs/implementation-status.md`, and any public hook list.

Register the module in `ModuleCatalog`. Safe mode must still boot when the module is optional.
