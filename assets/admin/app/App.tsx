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
import { environmentBadge, NAV_ITEMS, navItem, type AdminMode, type ViewId } from './navigation';

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
      schemaRules?: unknown;
    };
  }
}

export function App() {
  const boot = window.querynovaAdmin ?? {};
  const [view, setView] = useState<ViewId>('dashboard');
  const [mode, setMode] = useState<AdminMode>('simple');
  const [helpOpen, setHelpOpen] = useState(false);
  const page = navItem(view);
  const badge = environmentBadge(boot.environment ?? '');
  return (
    <ErrorBoundary>
      <div className="qn-shell">
        <nav aria-label="QueryNova">
          {NAV_ITEMS.map((item) => (
            <button key={item.id} type="button" aria-current={view === item.id ? 'page' : undefined} onClick={() => setView(item.id)}>
              {t(item.label)}
            </button>
          ))}
        </nav>
        <div className="qn-workspace">
          <header className="qn-bar">
            <div>
              <p>{t('QueryNova')}</p>
              <h1>{t(page.label)}</h1>
              <p>{t(page.hint)}</p>
            </div>
            <div className="qn-badges">
              {badge === '' ? null : (
                <span className="qn-badge" data-state="info">
                  {t(badge)}
                </span>
              )}
              <span className="qn-badge" data-state="not-configured">
                {t('Providers not configured')}
              </span>
            </div>
            <div className="qn-actions">
              <button type="button" aria-pressed={mode === 'simple'} onClick={() => setMode('simple')}>
                {t('Simple')}
              </button>
              <button type="button" aria-pressed={mode === 'advanced'} onClick={() => setMode('advanced')}>
                {t('Advanced')}
              </button>
              <button type="button" onClick={() => setHelpOpen((open) => !open)}>
                {t('Help')}
              </button>
            </div>
          </header>
          {helpOpen ? <p role="status">{t(page.hint)}</p> : null}
          <main>
            {view === 'dashboard' ? (
              <WhatMattersNow
                actions={boot.actions ?? []}
                sections={boot.sections}
                wooCommerceActive={boot.wooCommerceActive === true}
                mode={mode}
              />
            ) : null}
            {view === 'schema' ? (
              <SchemaBuilder
                restUrl={boot.restUrl ?? ''}
                nonce={boot.nonce ?? ''}
                initialRules={boot.schemaRules}
                onOpenDiagnostics={() => setView('settings')}
              />
            ) : null}
            {view === 'rank' && mode === 'advanced' ? <AdvancedDetail advanced={boot.advanced} /> : null}
            {view === 'rank' && mode === 'simple' ? (
              <section>
                <h2>{t('Rank history stays in Advanced')}</h2>
                <p>{t('Simple mode keeps the next actions on the dashboard. Switch to Advanced to inspect stored SERP rows. Missing ranks stay empty.')}</p>
              </section>
            ) : null}
            {view === 'commerce' ? (
              <>
                <ProductScreen product={boot.product} />
                <CategoryScreen category={boot.category} />
              </>
            ) : null}
            {view === 'settings' ? (
              <>
                <SetupScreen setup={boot.setup} restUrl={boot.restUrl ?? ''} nonce={boot.nonce ?? ''} />
                <DiagnosticsScreen diagnostics={boot.diagnostics} />
              </>
            ) : null}
            {view !== 'dashboard' && view !== 'schema' && view !== 'rank' && view !== 'commerce' && view !== 'settings' ? (
              <section>
                <h2>{t(page.label)}</h2>
                <p>{t(page.hint)}</p>
                <p>{t('This section is not on the screen yet. Stored data is unchanged.')}</p>
              </section>
            ) : null}
          </main>
        </div>
      </div>
    </ErrorBoundary>
  );
}
