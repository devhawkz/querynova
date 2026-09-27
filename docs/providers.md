# Providers

External data crosses one boundary:

External API, then a QueryNova adapter, then a QueryNova DTO, then application code.

Planned contracts:

- `KeywordProviderInterface`
- `SerpProviderInterface`
- `BacklinkProviderInterface`
- `SearchConsoleProviderInterface`
- `AnalyticsProviderInterface`
- `PageSpeedProviderInterface`
- `LLMProviderInterface`
- `AIVisibilityProviderInterface`

No adapter is connected to a paid vendor yet. The product will not scrape Google for rankings. A provider that is not connected reports unavailable data and a disconnected health state. It does not invent volume, rankings, backlinks, revenue, or citations.

Credentials stay in `SecretsStore`, encrypted with AES-256-GCM using a key derived from `wp_salt( 'auth' )`. They are not returned to JavaScript, REST responses, or logs. The admin sees a mask.

Each provider will publish capabilities (`supportsSearchVolume()`, `supportsCountry()`, and so on), a state (`connected`, `authentication_failed`, `rate_limited`, `quota_exceeded`, `temporarily_unavailable`, `disabled`), and will pass through the shared rate limiter and circuit breaker when those adapters land.

See `docs/adding-a-provider.md` and `docs/adr/ADR-006-provider-architecture.md`.
