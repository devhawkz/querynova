import { useState } from 'react';
import { ErrorBoundary } from '../components/ErrorBoundary';
import { t } from '../i18n';
import { AdvancedDetail } from '../features/advanced/AdvancedDetail';
import { CategoryScreen } from '../features/categories/CategoryScreen';
import { ContentScreen } from '../features/content/ContentScreen';
import { ProductScreen } from '../features/products/ProductScreen';
import { WhatMattersNow, type TodayAction } from '../features/dashboard/WhatMattersNow';
import { SchemaBuilder } from '../features/schema/SchemaBuilder';
import { AiScreen } from '../features/ai/AiScreen';
import { AnalyticsScreen } from '../features/analytics/AnalyticsScreen';
import { RankScreen } from '../features/rank/RankScreen';
import { ReportsScreen } from '../features/reports/ReportsScreen';
import { LinksScreen } from '../features/links/LinksScreen';
import { NotificationPanel } from '../features/notifications/NotificationPanel';
import { LocalScreen } from '../features/local/LocalScreen';
import { SeoScreen } from '../features/seo/SeoScreen';
import { SettingsScreen } from '../features/settings/SettingsScreen';
import { type SettingsSectionId } from '../features/settings/model';
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
      settings?: unknown;
      metaDefaults?: unknown;
      seoAudit?: unknown;
      analytics?: unknown;
      rankTracker?: unknown;
      siteTools?: unknown;
      contentWorkspace?: unknown;
      aiWorkspace?: unknown;
      reportsWorkspace?: unknown;
      localWorkspace?: unknown;
      notifications?: unknown;
    };
  }
}

export function App() {
  const boot = window.querynovaAdmin ?? {};
  const [view, setView] = useState<ViewId>('dashboard');
  const [mode, setMode] = useState<AdminMode>('simple');
  const [helpOpen, setHelpOpen] = useState(false);
  const [noticesOpen, setNoticesOpen] = useState(false);
  const [settingsSection, setSettingsSection] = useState<SettingsSectionId>('general');
  const page = navItem(view);
  const badge = environmentBadge(boot.environment ?? '');
  return (
    <ErrorBoundary>
      <div className="qn-shell">
        <nav aria-label="QueryNova">
          {NAV_ITEMS.map((item) => (
            <button
              key={item.id}
              type="button"
              aria-current={view === item.id ? 'page' : undefined}
              onClick={() => {
                if (item.id === 'settings') {
                  setSettingsSection('general');
                }
                setView(item.id);
              }}
            >
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
              <button type="button" aria-expanded={noticesOpen} onClick={() => setNoticesOpen((open) => !open)}>
                {t('Notifications')}
              </button>
            </div>
          </header>
          {helpOpen ? <p role="status">{t(page.hint)}</p> : null}
          {noticesOpen ? <NotificationPanel notifications={boot.notifications} /> : null}
          <main>
            {view === 'dashboard' ? (
              <WhatMattersNow
                actions={boot.actions ?? []}
                sections={boot.sections}
                wooCommerceActive={boot.wooCommerceActive === true}
                mode={mode}
              />
            ) : null}
            {view === 'seo' ? (
              <SeoScreen restUrl={boot.restUrl ?? ''} nonce={boot.nonce ?? ''} metaDefaults={boot.metaDefaults} seoAudit={boot.seoAudit} />
            ) : null}
            {view === 'analytics' ? (
              <AnalyticsScreen restUrl={boot.restUrl ?? ''} nonce={boot.nonce ?? ''} analytics={boot.analytics} rankTracker={boot.rankTracker} />
            ) : null}
            {view === 'schema' ? (
              <SchemaBuilder
                restUrl={boot.restUrl ?? ''}
                nonce={boot.nonce ?? ''}
                initialRules={boot.schemaRules}
                onOpenDiagnostics={() => {
                  setSettingsSection('tools');
                  setView('settings');
                }}
              />
            ) : null}
            {view === 'rank' ? <RankScreen restUrl={boot.restUrl ?? ''} nonce={boot.nonce ?? ''} rankTracker={boot.rankTracker} /> : null}
            {view === 'links' ? <LinksScreen restUrl={boot.restUrl ?? ''} nonce={boot.nonce ?? ''} siteTools={boot.siteTools} /> : null}
            {view === 'content' ? <ContentScreen restUrl={boot.restUrl ?? ''} nonce={boot.nonce ?? ''} contentWorkspace={boot.contentWorkspace} /> : null}
            {view === 'ai' ? <AiScreen restUrl={boot.restUrl ?? ''} nonce={boot.nonce ?? ''} aiWorkspace={boot.aiWorkspace} /> : null}
            {view === 'reports' ? <ReportsScreen restUrl={boot.restUrl ?? ''} nonce={boot.nonce ?? ''} /> : null}
            {view === 'local' ? <LocalScreen restUrl={boot.restUrl ?? ''} nonce={boot.nonce ?? ''} localWorkspace={boot.localWorkspace} /> : null}
            {view === 'rank' && mode === 'advanced' ? <AdvancedDetail advanced={boot.advanced} /> : null}
            {view === 'commerce' ? (
              <>
                <ProductScreen product={boot.product} restUrl={boot.restUrl ?? ''} nonce={boot.nonce ?? ''} />
                <CategoryScreen category={boot.category} restUrl={boot.restUrl ?? ''} nonce={boot.nonce ?? ''} />
              </>
            ) : null}
            {view === 'settings' ? (
              <SettingsScreen
                settings={boot.settings}
                section={settingsSection}
                onSection={setSettingsSection}
                version={boot.version ?? ''}
                wooCommerceActive={boot.wooCommerceActive === true}
                schemaRules={boot.schemaRules}
                setup={boot.setup}
                diagnostics={boot.diagnostics}
                restUrl={boot.restUrl ?? ''}
                nonce={boot.nonce ?? ''}
                siteTools={boot.siteTools}
                metaDefaults={boot.metaDefaults}
                mode={mode}
                onOpenSchema={() => setView('schema')}
              />
            ) : null}
            {view !== 'dashboard' && view !== 'schema' && view !== 'rank' && view !== 'commerce' && view !== 'settings' && view !== 'seo' && view !== 'analytics' && view !== 'links' && view !== 'content' && view !== 'ai' && view !== 'reports' && view !== 'local' ? (
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
