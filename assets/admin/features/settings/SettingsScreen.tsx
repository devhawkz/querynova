import { DiagnosticsScreen } from '../diagnostics/DiagnosticsScreen';
import { RoleSettings, TitleSettings, UrlBaseSettings } from './SettingsEditors';
import { SetupScreen } from '../setup/SetupScreen';
import { BreadcrumbFields, HtaccessFields, ImageAltFields, SitemapFields, WebmasterFields } from './SiteToolForms';
import { type AdminMode } from '../../app/navigation';
import { t } from '../../i18n';
import {
  featureStatus,
  featuresFor,
  hasModule,
  normalizeSettings,
  SETTINGS_SECTIONS,
  type FeatureCard,
  type ModuleCard,
  type SettingsSectionId,
} from './model';

interface Props {
  settings: unknown;
  section: SettingsSectionId;
  onSection: (section: SettingsSectionId) => void;
  version: string;
  wooCommerceActive: boolean;
  schemaRules: unknown;
  setup: unknown;
  diagnostics: unknown;
  restUrl: string;
  nonce: string;
  siteTools: unknown;
  metaDefaults: unknown;
  mode: AdminMode;
  onOpenSchema: () => void;
}

export function SettingsScreen({
  settings,
  section,
  onSection,
  version,
  wooCommerceActive,
  schemaRules,
  setup,
  diagnostics,
  restUrl,
  nonce,
  siteTools,
  metaDefaults,
  mode,
  onOpenSchema,
}: Props) {
  const model = normalizeSettings(settings);
  const current = SETTINGS_SECTIONS.find((item) => item.id === section) ?? SETTINGS_SECTIONS[0];
  return (
    <>
      <section aria-labelledby="qn-modules">
        <h2 id="qn-modules">{t('Module Manager')}</h2>
        <p>{t('Status follows the module and feature registries. This screen does not turn features off.')}</p>
        {model.modules.length === 0 ? (
          <p>{t('No modules are registered in this request. Stored SEO data is unchanged.')}</p>
        ) : (
          <div className="qn-card-grid">
            {model.modules.map((module) => (
              <ModuleCardView key={module.name} module={module} />
            ))}
          </div>
        )}
      </section>
      <div className="qn-settings">
        <nav aria-label={t('Settings')} className="qn-settings-nav">
          {SETTINGS_SECTIONS.map((item) => (
            <button key={item.id} type="button" aria-current={section === item.id ? 'true' : undefined} onClick={() => onSection(item.id)}>
              {t(item.label)}
            </button>
          ))}
        </nav>
        <section aria-labelledby="qn-settings-section">
          <h2 id="qn-settings-section">{t(current.label)}</h2>
          {section === 'general' ? <GeneralSection environment={model.wordpressEnvironment} build={model.querynovaBuild} version={version} /> : null}
          {section === 'seo' ? <p>{t('Title templates and the on-page checklist are on the SEO screen. Saving templates there does not rewrite custom titles, canonicals, or indexability.')}</p> : null}
          {section === 'titles' ? <TitleSettings restUrl={restUrl} nonce={nonce} metaDefaults={metaDefaults} /> : null}
          {section === 'links' ? <p>{t('Link suggestions stay Suggest Only. Open Links to review the graph. This screen does not insert links.')}</p> : null}
          {section === 'breadcrumbs' ? <BreadcrumbFields restUrl={restUrl} nonce={nonce} siteTools={siteTools} mode={mode} /> : null}
          {section === 'images' ? <ImageAltFields restUrl={restUrl} nonce={nonce} siteTools={siteTools} mode={mode} /> : null}
          {section === 'sitemaps' ? (
            <>
              <FeatureList features={featuresFor(model, 'sitemap')} empty={t('No sitemap features are registered.')} />
              <SitemapFields restUrl={restUrl} nonce={nonce} siteTools={siteTools} mode={mode} />
            </>
          ) : null}
          {section === 'schema' ? <SchemaSection rules={schemaRules} onOpenSchema={onOpenSchema} /> : null}
          {section === 'webmaster' ? <WebmasterFields restUrl={restUrl} nonce={nonce} siteTools={siteTools} mode={mode} /> : null}
          {section === 'woocommerce' ? (
            <>
              <WooSection active={wooCommerceActive} />
              <UrlBaseSettings restUrl={restUrl} nonce={nonce} />
            </>
          ) : null}
          {section === 'local' ? <LocalSection registered={hasModule(model, 'local')} /> : null}
          {section === 'analytics' ? <p>{t('Search Console and GA4 are on the Analytics screen. Disconnected stays Not connected. This screen does not invent clicks, impressions, sessions, or revenue.')}</p> : null}
          {section === 'providers' ? <p>{t('An empty provider stays Not connected. Configure is on Diagnostics and does not call a vendor.')}</p> : null}
          {section === 'ai' ? <AiSection features={featuresFor(model, 'ai')} /> : null}
          {section === 'roles' ? (
            <>
              <RolesSection roles={model.roles} />
              <RoleSettings restUrl={restUrl} nonce={nonce} />
            </>
          ) : null}
          {section === 'advanced' ? <HtaccessFields restUrl={restUrl} nonce={nonce} siteTools={siteTools} mode={mode} /> : null}
          {section === 'tools' ? (
            <>
              <p>{t('Setup stores answers only. Diagnostics reads the current snapshot. Neither tool rewrites live SEO data.')}</p>
              <SetupScreen setup={setup} restUrl={restUrl} nonce={nonce} />
              <DiagnosticsScreen diagnostics={diagnostics} restUrl={restUrl} nonce={nonce} />
            </>
          ) : null}
        </section>
      </div>
    </>
  );
}

function ModuleCardView({ module }: { module: ModuleCard }) {
  const state = module.failed ? 'critical' : module.registered ? 'info' : 'not-configured';
  const label = module.failed ? 'Failed' : module.registered ? (module.optional ? 'Optional' : 'Required') : 'Not registered';
  return (
    <article className="qn-card">
      <h3>{module.name}</h3>
      <p>
        <span className="qn-badge" data-state={state}>
          {t(label)}
        </span>
      </p>
      <p>{module.version === '' ? t('Version is not available.') : `${t('Version')} ${module.version}`}</p>
      <p>{module.dependencies.length === 0 ? t('No dependencies.') : `${t('Depends on')} ${module.dependencies.join(', ')}`}</p>
      {module.failed ? <p>{t('This module failed during boot. Stored SEO data is unchanged.')}</p> : null}
      <FeatureList features={module.features} empty={t('No features are registered for this module.')} />
    </article>
  );
}

function FeatureList({ features, empty }: { features: FeatureCard[]; empty: string }) {
  if (features.length === 0) {
    return <p>{empty}</p>;
  }
  return (
    <ul>
      {features.map((feature) => {
        const status = featureStatus(feature);
        return (
          <li key={feature.id === '' ? feature.name : feature.id}>
            {feature.name === '' ? t('Unnamed feature') : feature.name}{' '}
            <span className="qn-badge" data-state={status.state}>
              {t(status.label)}
            </span>
          </li>
        );
      })}
    </ul>
  );
}

function GeneralSection({ environment, build, version }: { environment: string; build: string; version: string }) {
  return (
    <>
      <p>{t('The WordPress environment and the QueryNova build are separate. This screen does not change either one.')}</p>
      <dl>
        <dt>{t('WordPress environment')}</dt>
        <dd>{environment === '' ? t('Not available') : environment}</dd>
        <dt>{t('QueryNova build')}</dt>
        <dd>{build === '' ? t('Not available') : build}</dd>
        <dt>{t('QueryNova version')}</dt>
        <dd>{version === '' ? t('Not available') : version}</dd>
      </dl>
    </>
  );
}

function SchemaSection({ rules, onOpenSchema }: { rules: unknown; onOpenSchema: () => void }) {
  const stored = Array.isArray(rules);
  return (
    <>
      <p>{stored && rules.length === 0 ? t('No stored schema rules.') : stored ? t('Stored schema rules are available.') : t('Stored schema rules were not included in this page load.')}</p>
      <p>{t('Open Schema to review rules. This section does not save them.')}</p>
      <button type="button" onClick={onOpenSchema}>
        {t('Open Schema')}
      </button>
    </>
  );
}

function WooSection({ active }: { active: boolean }) {
  return (
    <>
      <p>{active ? t('WooCommerce is active.') : t('WooCommerce is not active.')}</p>
      <p>{t('This screen does not change product URLs or indexability.')}</p>
    </>
  );
}

function LocalSection({ registered }: { registered: boolean }) {
  return <p>{registered ? t('Local SEO is registered. This screen does not create locations.') : t('The locations module is not registered. Local SEO stays off. This screen does not create locations.')}</p>;
}

function AiSection({ features }: { features: FeatureCard[] }) {
  return (
    <>
      <FeatureList features={features} empty={t('No AI features are registered. Nothing is published from this screen.')} />
      <p>{t('Drafts are not published from this screen.')}</p>
    </>
  );
}

function RolesSection({ roles }: { roles: { role: string; capabilities: string[] }[] }) {
  return (
    <>
      <p>{t('These roles are read from the role map. This screen does not change them.')}</p>
      {roles.length === 0 ? (
        <p>{t('No role map was provided for this request.')}</p>
      ) : (
        <table>
          <caption>{t('Role capabilities')}</caption>
          <thead>
            <tr>
              <th scope="col">{t('Role')}</th>
              <th scope="col">{t('Capabilities')}</th>
            </tr>
          </thead>
          <tbody>
            {roles.map((role) => (
              <tr key={role.role}>
                <th scope="row">{role.role}</th>
                <td>{role.capabilities.length === 0 ? t('None') : role.capabilities.join(', ')}</td>
              </tr>
            ))}
          </tbody>
        </table>
      )}
    </>
  );
}

