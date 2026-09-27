import { useState } from 'react';
import { ErrorBoundary } from '../components/ErrorBoundary';
import { t } from '../i18n';
import { AdvancedDetail } from '../features/advanced/AdvancedDetail';
import { CategoryScreen } from '../features/categories/CategoryScreen';
import { DiagnosticsScreen } from '../features/diagnostics/DiagnosticsScreen';
import { ProductScreen } from '../features/products/ProductScreen';
import { SetupScreen } from '../features/setup/SetupScreen';
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
      category?: unknown;
      diagnostics?: unknown;
      setup?: unknown;
    };
  }
}

export function App() {
  const boot = window.querynovaAdmin ?? {};
  const [view, setView] = useState<'today' | 'schema' | 'advanced' | 'product' | 'category' | 'diagnostics' | 'setup'>('today');
  return (
    <ErrorBoundary>
      <nav aria-label="QueryNova">
        <button type="button" aria-current={view === 'today' ? 'page' : undefined} onClick={() => setView('today')}>
          {t('What Matters Now')}
        </button>
        <button type="button" aria-current={view === 'schema' ? 'page' : undefined} onClick={() => setView('schema')}>
          {t('Schema')}
        </button>
        <button type="button" aria-current={view === 'advanced' ? 'page' : undefined} onClick={() => setView('advanced')}>
          {t('Advanced')}
        </button>
        <button type="button" aria-current={view === 'product' ? 'page' : undefined} onClick={() => setView('product')}>
          {t('Product')}
        </button>
        <button type="button" aria-current={view === 'category' ? 'page' : undefined} onClick={() => setView('category')}>
          {t('Category')}
        </button>
        <button type="button" aria-current={view === 'diagnostics' ? 'page' : undefined} onClick={() => setView('diagnostics')}>
          {t('Diagnostics')}
        </button>
        <button type="button" aria-current={view === 'setup' ? 'page' : undefined} onClick={() => setView('setup')}>
          {t('Setup')}
        </button>
      </nav>
      <main>
        {view === 'schema' ? <SchemaBuilder restUrl={boot.restUrl ?? ''} nonce={boot.nonce ?? ''} /> : null}
        {view === 'advanced' ? <AdvancedDetail advanced={boot.advanced} /> : null}
        {view === 'product' ? <ProductScreen product={boot.product} /> : null}
        {view === 'category' ? <CategoryScreen category={boot.category} /> : null}
        {view === 'diagnostics' ? <DiagnosticsScreen diagnostics={boot.diagnostics} /> : null}
        {view === 'setup' ? <SetupScreen setup={boot.setup} restUrl={boot.restUrl ?? ''} nonce={boot.nonce ?? ''} /> : null}
        {view === 'today' ? (
          <WhatMattersNow
            actions={boot.actions ?? []}
            sections={boot.sections}
            wooCommerceActive={boot.wooCommerceActive === true}
          />
        ) : null}
      </main>
    </ErrorBoundary>
  );
}
