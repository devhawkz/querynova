# Environments

QueryNova uses `wp_get_environment_type()`. Accepted values are `local`, `development`, `staging`, and `production`. Unknown values are treated as production.

Set the type in `wp-config.php`:

```php
define( 'WP_ENVIRONMENT_TYPE', 'local' );
```

`WordPressEnvironment` exposes `isLocal()`, `isDevelopment()`, `isStaging()`, `isProduction()`, and `allowsVerboseDiagnostics()`.

| Behavior | local / development | staging | production |
| --- | --- | --- | --- |
| Default log level | DEBUG | DEBUG | INFO |
| Stack traces shown to people | No | No | No |
| Developer diagnostics | Yes | Yes | No |
| Temporary debug window | Available | Available | 15 minutes, 1 hour, 6 hours, or 24 hours, then it expires |

There is no second environment system. Feature flags may still vary by environment through `FeatureRegistry::overrideForEnvironment()`.
