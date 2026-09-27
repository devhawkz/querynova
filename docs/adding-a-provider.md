# Adding a provider

1. Implement the domain contract (`KeywordProviderInterface` or the relevant sibling). Do not leak the vendor JSON shape past the adapter.
2. Map the vendor response into a QueryNova DTO that includes source, provider, timestamp, confidence, and methodology when the number is derived.
3. Read credentials from `SecretsStore`. Never accept them from a REST response body that is later echoed back.
4. Publish capabilities such as country, device, historical SERP, and citation support. Callers must check a capability before assuming a field exists.
5. Report health: connected, authentication failed, rate limited, quota exceeded, temporarily unavailable, or disabled.
6. Send HTTP through `HttpClientInterface` so timeouts, correlation ids, and safe logging stay consistent.
7. Throttle with the shared rate limiter and stop calling through the circuit breaker after repeated failures.
8. Authentication failures throw `ProviderAuthenticationException` and must not be retried forever. Rate limits throw `ProviderRateLimitException` with `retryAfterSeconds()`.
9. If the provider returns nothing, return unavailable. Do not coerce that to zero.
10. Add a fake provider under tests and a contract test that the fake and the adapter both honor. Tests must not hit the network.
11. Register the adapter in the module `register()` method and document the environment variables or settings fields in `docs/providers.md`.

Do not add a paid vendor as a hard dependency. The site owner connects credentials. QueryNova does not scrape Google results pages.
