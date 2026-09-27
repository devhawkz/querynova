# ADR-003: Logging

## Status

Accepted

## Decision

Implement a PSR-3 logger inside the plugin, with file, database, debug, null, and a dormant cloud handler. Redact secrets before handlers see the record. Production defaults to INFO. Debug in production is a timed window.

## Consequences

Monolog is not bundled, which avoids colliding with other plugins that ship their own logger. Error references replace raw exceptions in the admin. Log storage is operational data, separate from the audit log.
