import { useState } from 'react';
import { ErrorBoundary } from '../components/ErrorBoundary';
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
    };
  }
}

export function App() {
  const boot = window.querynovaAdmin ?? {};
  const [view, setView] = useState<'today' | 'schema'>('today');
  return (
    <ErrorBoundary>
      <nav aria-label="QueryNova">
        <button type="button" aria-current={view === 'today' ? 'page' : undefined} onClick={() => setView('today')}>
          What Matters Now
        </button>
        <button type="button" aria-current={view === 'schema' ? 'page' : undefined} onClick={() => setView('schema')}>
          Schema
        </button>
      </nav>
      {view === 'schema' ? (
        <SchemaBuilder restUrl={boot.restUrl ?? ''} nonce={boot.nonce ?? ''} />
      ) : (
        <WhatMattersNow actions={boot.actions ?? []} wooCommerceActive={boot.wooCommerceActive === true} />
      )}
    </ErrorBoundary>
  );
}
