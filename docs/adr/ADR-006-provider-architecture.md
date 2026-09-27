# ADR-006: Provider architecture

## Status

Accepted

## Decision

Providers sit behind interfaces. The adapter maps an external payload into QueryNova DTOs. Application code does not depend on a vendor URL or JSON shape. No commercial SERP, keyword, or backlink vendor is hardcoded. Google result pages are not scraped.

## Consequences

Connecting DataForSEO, a Search Console property, or an OpenAI-compatible endpoint is a settings and adapter change. Missing credentials produce a disconnected state and unavailable metrics. Official Google APIs are used for Search Console, GA4, and PageSpeed when the site owner completes OAuth or supplies a key.
