import { useState } from 'react';
import { ErrorBoundary } from '../components/ErrorBoundary';
import { AdvancedDetail } from '../features/advanced/AdvancedDetail';
import { ProductScreen } from '../features/products/ProductScreen';
import { WhatMattersNow, type TodayAction } from '../features/dashboard/WhatMattersNow';
import { SchemaBuilder } from '../features/schema/SchemaBuilder';

declare global {
  interface Window {
    querynovaAdmin?: {
      restUrl?: string;
      nonce?: string;
      version?: string;
      environment?: string;
      wooCommerceActive?: boolean;
      actions?: TodayAction[];
      sections?: unknown;
      advanced?: unknown;
      product?: unknown;
    };
  }
}

export function App() {
  const boot = window.querynovaAdmin ?? {};
  const [view, setView] = useState<'today' | 'schema' | 'advanced' | 'product'>('today');
  return (
    <ErrorBoundary>
      <nav aria-label="QueryNova">
        <button type="button" aria-current={view === 'today' ? 'page' : undefined} onClick={() => setView('today')}>
          What Matters Now
        </button>
        <button type="button" aria-current={view === 'schema' ? 'page' : undefined} onClick={() => setView('schema')}>
          Schema
        </button>
        <button type="button" aria-current={view === 'advanced' ? 'page' : undefined} onClick={() => setView('advanced')}>
          Advanced
        </button>
        <button type="button" aria-current={view === 'product' ? 'page' : undefined} onClick={() => setView('product')}>
          Product
        </button>
      </nav>
      {view === 'schema' ? <SchemaBuilder restUrl={boot.restUrl ?? ''} nonce={boot.nonce ?? ''} /> : null}
      {view === 'advanced' ? <AdvancedDetail advanced={boot.advanced} /> : null}
      {view === 'product' ? <ProductScreen product={boot.product} /> : null}
      {view === 'today' ? (
        <WhatMattersNow
          actions={boot.actions ?? []}
          sections={boot.sections}
          wooCommerceActive={boot.wooCommerceActive === true}
        />
      ) : null}
    </ErrorBoundary>
  );
}
