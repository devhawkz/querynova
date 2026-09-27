import { useMemo, useState } from 'react';
import { QueryNovaApi } from '../../core/api/client';
import { t } from '../../i18n';
import { draftPayload, normalizeSetup, SETUP_STEPS, setupLine, type SetupModel } from './model';

interface Props {
  setup: unknown;
  restUrl: string;
  nonce: string;
}

export function SetupScreen({ setup, restUrl, nonce }: Props) {
  const [model, setModel] = useState<SetupModel>(() => normalizeSetup(setup));
  const [message, setMessage] = useState('');
  const api = useMemo(
    () => new QueryNovaApi({ restUrl, nonce, version: '', environment: '' }),
    [restUrl, nonce],
  );

  async function save() {
    if (restUrl === '' || nonce === '') {
      setMessage(t('Setup cannot be saved in this session.'));
      return;
    }
    try {
      const saved = await api.put<unknown>('/setup', draftPayload(model));
      setModel(normalizeSetup(saved));
      setMessage(t('Setup answers are stored. Providers stay not configured and no crawl was started.'));
    } catch {
      setMessage(t('Setup answers could not be saved.'));
    }
  }

  return (
    <section aria-labelledby="qn-setup">
      <h1 id="qn-setup">{t('Setup')}</h1>
      <p>{model.completed ? t('Setup answers are stored.') : t('Setup is not finished.')}</p>
      <p>{t('Saving records these answers. It does not connect a provider, store an API key, or start a crawl.')}</p>
      <ol>
        {SETUP_STEPS.map((step) => (
          <li key={step}>{t(step)}</li>
        ))}
      </ol>
      <label>
        {t('Site type')}
        <select
          value={model.siteType ?? ''}
          onChange={(event) => setModel({ ...model, siteType: event.target.value === '' ? null : event.target.value })}
        >
          <option value="">{t('Nothing recorded.')}</option>
          <option value="store">{t('Store')}</option>
          <option value="publisher">{t('Publisher')}</option>
          <option value="business">{t('Business')}</option>
          <option value="other">{t('Other')}</option>
        </select>
      </label>
      <label>
        {t('Business type')}
        <input
          value={model.businessType ?? ''}
          onChange={(event) => setModel({ ...model, businessType: event.target.value === '' ? null : event.target.value })}
        />
      </label>
      <p>
        {t('WooCommerce')} {model.wooCommerce === null ? t('Nothing recorded.') : model.wooCommerce ? t('Active') : t('Not active')}
      </p>
      <label>
        {t('Organization')}
        <input
          value={model.organizationName ?? ''}
          onChange={(event) => setModel({ ...model, organizationName: event.target.value === '' ? null : event.target.value })}
        />
      </label>
      <label>
        {t('Organization URL')}
        <input
          value={model.organizationUrl ?? ''}
          onChange={(event) => setModel({ ...model, organizationUrl: event.target.value === '' ? null : event.target.value })}
        />
      </label>
      <label>
        {t('Organization logo')}
        <input
          value={model.organizationLogo ?? ''}
          onChange={(event) => setModel({ ...model, organizationLogo: event.target.value === '' ? null : event.target.value })}
        />
      </label>
      <label>
        {t('Search Console')}
        <input
          value={model.searchConsole ?? ''}
          onChange={(event) => setModel({ ...model, searchConsole: event.target.value === '' ? null : event.target.value })}
        />
      </label>
      <p>
        {t('Search Console')} {setupLine(model.searchConsole, 'Not configured')} · {t('Not configured')}
      </p>
      <label>
        {t('GA4')}
        <input
          value={model.ga4 ?? ''}
          onChange={(event) => setModel({ ...model, ga4: event.target.value === '' ? null : event.target.value })}
        />
      </label>
      <p>
        {t('GA4')} {setupLine(model.ga4, 'Not configured')} · {t('Not configured')}
      </p>
      <label>
        {t('SEO defaults')}
        <select
          value={model.titleSeparator ?? ''}
          onChange={(event) => setModel({ ...model, titleSeparator: event.target.value === '' ? null : event.target.value })}
        >
          <option value="">{t('Nothing recorded.')}</option>
          <option value="|">|</option>
          <option value="-">-</option>
          <option value="–">–</option>
        </select>
      </label>
      <Choice
        label="Schema"
        value={model.schemaEnabled}
        onChange={(schemaEnabled) => setModel({ ...model, schemaEnabled })}
      />
      <Choice
        label="Sitemap"
        value={model.sitemapEnabled}
        onChange={(sitemapEnabled) => setModel({ ...model, sitemapEnabled })}
      />
      <p>
        {t('Provider setup')} {t('Not configured')}
      </p>
      <Choice
        label="Crawler"
        value={model.crawlerEnabled}
        onChange={(crawlerEnabled) => setModel({ ...model, crawlerEnabled })}
      />
      <label>
        {t('Crawler origin')}
        <input
          value={model.crawlerOrigin ?? ''}
          onChange={(event) => setModel({ ...model, crawlerOrigin: event.target.value === '' ? null : event.target.value })}
        />
      </label>
      <p>{model.crawlerOrigin === null ? t('Nothing recorded.') : `${model.crawlerOrigin} · ${t('Not started')}`}</p>
      <button type="button" onClick={() => void save()}>
        {t('Save setup answers')}
      </button>
      {message === '' ? null : <p role="status">{message}</p>}
    </section>
  );
}

function Choice({
  label,
  value,
  onChange,
}: {
  label: string;
  value: boolean | null;
  onChange: (value: boolean | null) => void;
}) {
  return (
    <label>
      {t(label)}
      <select
        value={value === null ? '' : value ? 'yes' : 'no'}
        onChange={(event) => onChange(event.target.value === '' ? null : event.target.value === 'yes')}
      >
        <option value="">{t('Nothing recorded.')}</option>
        <option value="yes">{t('Yes')}</option>
        <option value="no">{t('No')}</option>
      </select>
    </label>
  );
}
