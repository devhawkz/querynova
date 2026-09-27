# Migrations

`MigrationManager` applies `MigrationInterface` implementations in version order. Versions are zero-padded timestamps such as `202609270001`, not the plugin version.

Each applied migration is written to `{prefix}qn_migrations` and the option `querynova_db_version` moves forward only after `up()` succeeds. A failure is logged on the `migrations` channel and thrown as `MigrationException`.

The first migration, `InitialSchemaMigration`, creates the custom tables. Later schema changes must be new migration classes. Do not edit an already shipped migration.

Large data backfills belong in background jobs, not in a single request. Activation calls `migrate()`. `wp querynova migrate` will call the same manager when the CLI command is added.

Migrations are idempotent at the runner level: a version that is already recorded is not executed again.
