# ADR-007: Cloud boundary

## Status

Accepted

## Decision

The plugin is cloud-ready and cloud-independent. Core SEO, metadata, sitemaps, redirects, and on-site WooCommerce fields must work with no QueryNova Cloud account. Cloud may later own heavy SERP history, keyword corpora, backlink indexes, and cross-site benchmarks.

## Consequences

A cloud outage leaves the public site and core SEO running. The admin shows a degraded provider or cloud check. The dormant cloud log handler does not transmit logs.
