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

The WordPress environment and the QueryNova build are separate. `wp_get_environment_type()` is the only source of the WordPress environment. QueryNova does not infer it from the installed ZIP or from `build/channel.json`, and it does not write `WP_ENVIRONMENT_TYPE` or `wp-config.php`. Logging, stack traces, developer diagnostics, temporary debug windows, and `FeatureRegistry::overrideForEnvironment()` follow this WordPress environment. An unknown type stays production.

The installed package is recorded in `build/channel.json`. `production` is the stable release channel, `staging` is beta, and `development` is the development channel. A missing or unrecognized file is `unknown`. Diagnostics, the system report, the downloadable diagnostic report, `wp querynova diagnostics`, and log context show the WordPress environment, the QueryNova build, the release channel, and a status.

A production WordPress environment with a production build is Normal. A staging WordPress environment with a staging build is Normal. A staging build on production WordPress is a non-blocking Warning: the plugin keeps working and the WordPress environment stays production. A production build on staging WordPress is an informational notice, not a fatal error.
