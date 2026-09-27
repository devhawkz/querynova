# ADR-004: Analytics storage

## Status

Accepted

## Decision

Store raw Search Console, GA4, and WooCommerce metrics in their own tables. Store fused product, category, and revenue rollups separately. Never write a derived score back over a raw row. Revenue is stored as measured, attributed, or estimated, with methodology and confidence.

## Consequences

Aggregation can be recomputed when a methodology version changes. The UI cannot honestly relabel an estimate as a measurement, because the kind is part of the stored value. History is retained according to the configured retention window.
