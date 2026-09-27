# Staging

Staging should match production code. Set:

```php
define( 'WP_ENVIRONMENT_TYPE', 'staging' );
```

The admin should show a staging badge once the admin shell exists. Diagnostics and INFO/DEBUG logging stay available. Safe staging mode, when implemented, will require an extra confirmation before destructive SEO changes, provider writes, cloud side effects, production reporting, automation, and external mutations.

If a staging site is a clone of production, QueryNova will warn when it can detect the same Search Console property, the same analytics property, or the same provider project. That detection is not implemented yet.

Build staging assets with `npm run build:staging`. The compiled output is production-like and may include diagnostics appropriate to staging. Do not point staging jobs at production provider projects unless that is intentional.
