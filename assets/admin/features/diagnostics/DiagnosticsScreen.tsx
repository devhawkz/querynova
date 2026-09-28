import { useMemo, useState } from 'react';
import { QueryNovaApi } from '../../core/api/client';
import { t } from '../../i18n';
import { displayValue, normalizeDiagnostics, providerCard, reportText, type DiagnosticsModel } from './model';

interface Props {
  diagnostics: unknown;
  restUrl?: string;
  nonce?: string;
}

export function DiagnosticsScreen({ diagnostics, restUrl = '', nonce = '' }: Props) {
  const api = useMemo(() => new QueryNovaApi({ restUrl, nonce, version: '', environment: '' }), [restUrl, nonce]);
  const [note, setNote] = useState('');
  const report = normalizeDiagnostics(diagnostics);
  const text = reportText(report);
  return (
    <section aria-labelledby="qn-diagnostics">
      <h1 id="qn-diagnostics">{t('Diagnostics')}</h1>
      <dl>
        <dt>{t('WordPress Environment')}</dt>
        <dd>{displayValue(titleCase(report.environment))}</dd>
        <dt>{t('QueryNova Build')}</dt>
        <dd>{displayValue(titleCase(report.querynovaBuild))}</dd>
        <dt>{t('Release Channel')}</dt>
        <dd>{displayValue(titleCase(report.releaseChannel))}</dd>
        <dt>{t('Status')}</dt>
        <dd>{displayValue(statusLabel(report.releaseStatus))}</dd>
        <dt>{t('QueryNova version')}</dt>
        <dd>{displayValue(report.querynovaVersion)}</dd>
        <dt>{t('WP version')}</dt>
        <dd>{displayValue(report.wpVersion)}</dd>
        <dt>{t('PHP version')}</dt>
        <dd>{displayValue(report.phpVersion)}</dd>
        <dt>{t('WooCommerce version')}</dt>
        <dd>{displayValue(report.wooCommerceVersion)}</dd>
        <dt>{t('DB version')}</dt>
        <dd>{displayValue(report.dbVersion)}</dd>
        <dt>{t('Schema version')}</dt>
        <dd>{displayValue(report.schemaVersion)}</dd>
        <dt>{t('Cron')}</dt>
        <dd>{displayValue(report.cronScheduled)}</dd>
        <dt>{t('Cache')}</dt>
        <dd>{displayValue(report.cacheAdapter)}. {t('Hits')} {displayValue(report.cacheHits)}</dd>
      </dl>
      {report.releaseNotice === '' ? null : <p role="status">{t(report.releaseNotice)}</p>}
      <h2>{t('Modules')}</h2>
      {report.modules.length === 0 ? <p>{t('No modules were recorded for this request. Open diagnostics again after the plugin finishes booting.')}</p> : <ul>{report.modules.map((name) => <li key={name}>{name}</li>)}</ul>}
      <h2>{t('Providers')}</h2>
      {report.providers.length === 0 ? (
        <p>{t('No providers are connected. Connect one in setup before expecting measurements.')}</p>
      ) : (
        report.providers.map((provider) => {
          const card = providerCard(provider.name, provider.state);
          return (
            <fieldset key={provider.name}>
              <legend>{provider.name}</legend>
              <p><span className="qn-badge" data-state="not-configured">{t(card.state)}</span></p>
              <button type="button" onClick={() => void configure(api, provider.name, restUrl !== '' && nonce !== '', setNote)}>{t('Configure')}</button>
            </fieldset>
          );
        })
      )}
      {note !== '' ? <p role="status">{note}</p> : null}
      <h2>{t('Queue')}</h2>
      {Object.keys(report.queue).length === 0 ? (
        <p>{t('No queued jobs were counted. A public request does not start a crawl.')}</p>
      ) : (
        <ul>
          {Object.entries(report.queue).map(([status, count]) => (
            <li key={status}>
              {status} {count}
            </li>
          ))}
        </ul>
      )}
      <h2>{t('Migrations')}</h2>
      {report.pendingMigrations.length === 0 ? <p>{t('No pending migrations were recorded.')}</p> : <ul>{report.pendingMigrations.map((version) => <li key={version}>{version}</li>)}</ul>}
      <h2>{t('Recent errors')}</h2>
      {report.recentErrors.length === 0 ? (
        <p>{t('No recent errors were stored.')}</p>
      ) : (
        <ul>
          {report.recentErrors.map((error) => (
            <li key={`${error.reference}-${error.message}`}>
              {error.message} {error.reference}
            </li>
          ))}
        </ul>
      )}
      <h2>{t('System report')}</h2>
      <textarea readOnly value={text} aria-label={t('System report')} />
      <button
        type="button"
        onClick={() => {
          if (typeof navigator !== 'undefined' && navigator.clipboard) {
            void navigator.clipboard.writeText(text);
          }
        }}
      >
        {t('Copy system report')}
      </button>
      <button type="button" onClick={() => downloadReport(text)}>
        {t('Download diagnostic report')}
      </button>
    </section>
  );
}

async function configure(api: QueryNovaApi, name: string, ready: boolean, setNote: (note: string) => void): Promise<void> {
  const fallback = 'Configure was recorded. No vendor was called.';
  if (!ready) {
    setNote(t(fallback));
    return;
  }
  try {
    const body = await api.post<Record<string, unknown>>('/providers/configure', { name, confirmed: true });
    setNote(typeof body.note === 'string' ? body.note : t(fallback));
  } catch {
    setNote(t(fallback));
  }
}

function titleCase(value: string): string {
  if (value === '') {
    return '';
  }
  return value.charAt(0).toUpperCase() + value.slice(1);
}

function statusLabel(status: DiagnosticsModel['releaseStatus']): string {
  if (status === 'normal') {
    return 'Normal';
  }
  if (status === 'warning') {
    return 'Warning';
  }
  if (status === 'info') {
    return 'Informational';
  }
  return status;
}

function downloadReport(text: string): void {
  if (typeof document === 'undefined') {
    return;
  }
  const url = URL.createObjectURL(new Blob([text], { type: 'application/json' }));
  const link = document.createElement('a');
  link.href = url;
  link.download = 'querynova-diagnostics.json';
  link.click();
  URL.revokeObjectURL(url);
}
