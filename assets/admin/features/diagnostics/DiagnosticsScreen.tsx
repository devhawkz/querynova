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
  const [jobs, setJobs] = useState<Array<Record<string, unknown>>>([]);
  const [logLevel, setLogLevel] = useState('');
  const [logChannel, setLogChannel] = useState('');
  const [logModule, setLogModule] = useState('');
  const [logProvider, setLogProvider] = useState('');
  const [logDate, setLogDate] = useState('');
  const [logReference, setLogReference] = useState('');
  const [logCorrelation, setLogCorrelation] = useState('');
  const [logRows, setLogRows] = useState<Array<Record<string, unknown>>>([]);
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
      <h2>{t('Job monitor')}</h2>
      <p>{t('Retry puts a failed job back in the queue. The handler is not run.')}</p>
      <button type="button" onClick={() => void loadJobs(api, restUrl !== '' && nonce !== '', setJobs, setNote)}>{t('Load jobs')}</button>
      {jobs.length === 0 ? (
        <p>{t('No stored jobs are on this page. A missing duration stays empty.')}</p>
      ) : (
        <table>
          <caption>{t('Jobs')}</caption>
          <thead>
            <tr>
              <th scope="col">{t('Job')}</th>
              <th scope="col">{t('Module')}</th>
              <th scope="col">{t('Status')}</th>
              <th scope="col">{t('Created')}</th>
              <th scope="col">{t('Started')}</th>
              <th scope="col">{t('Duration')}</th>
              <th scope="col">{t('Attempts')}</th>
              <th scope="col">{t('Correlation id')}</th>
              <th scope="col">{t('Retry')}</th>
            </tr>
          </thead>
          <tbody>
            {jobs.map((job) => (
              <tr key={String(job.job ?? '') + String(job.correlation_id ?? '')}>
                <td>{cell(job.job)}</td>
                <td>{cell(job.module)}</td>
                <td>{cell(job.status)}</td>
                <td>{cell(job.created)}</td>
                <td>{cell(job.started)}</td>
                <td>{cell(job.duration)}</td>
                <td>{cell(job.attempts)}</td>
                <td>{cell(job.correlation_id)}</td>
                <td><button type="button" onClick={() => void retryJob(api, job, restUrl !== '' && nonce !== '', setNote)}>{t('Retry')}</button></td>
              </tr>
            ))}
          </tbody>
        </table>
      )}
      <h2>{t('Migrations')}</h2>
      {report.pendingMigrations.length === 0 ? <p>{t('No pending migrations were recorded.')}</p> : <ul>{report.pendingMigrations.map((version) => <li key={version}>{version}</li>)}</ul>}
      <h2>{t('Log viewer')}</h2>
      <p>{t('Filters stay inside QueryNova. Secrets are omitted.')}</p>
      <label>
        {t('Level')}
        <input value={logLevel} onChange={(event) => setLogLevel(event.target.value)} />
      </label>
      <label>
        {t('Channel')}
        <input value={logChannel} onChange={(event) => setLogChannel(event.target.value)} />
      </label>
      <label>
        {t('Module')}
        <input value={logModule} onChange={(event) => setLogModule(event.target.value)} />
      </label>
      <label>
        {t('Provider')}
        <input value={logProvider} onChange={(event) => setLogProvider(event.target.value)} />
      </label>
      <label>
        {t('Date')}
        <input value={logDate} onChange={(event) => setLogDate(event.target.value)} />
      </label>
      <label>
        {t('Error reference')}
        <input value={logReference} onChange={(event) => setLogReference(event.target.value)} />
      </label>
      <label>
        {t('Correlation id')}
        <input value={logCorrelation} onChange={(event) => setLogCorrelation(event.target.value)} />
      </label>
      <button
        type="button"
        onClick={() => void loadLogs(api, restUrl !== '' && nonce !== '', {
          level: logLevel,
          channel: logChannel,
          module: logModule,
          provider: logProvider,
          date: logDate,
          error_reference: logReference,
          correlation_id: logCorrelation,
        }, setLogRows, setNote)}
      >
        {t('Filter logs')}
      </button>
      {logRows.length === 0 ? <p>{t('No log rows match these filters.')}</p> : (
        <ul>
          {logRows.map((row) => (
            <li key={`${String(row.error_reference ?? '')}-${String(row.message ?? '')}`}>{cell(row.message)} {cell(row.error_reference)}</li>
          ))}
        </ul>
      )}
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

function cell(value: unknown): string {
  if (value === null || value === undefined || value === '') {
    return t('Unavailable');
  }
  return String(value);
}

async function loadJobs(
  api: QueryNovaApi,
  ready: boolean,
  setJobs: (rows: Array<Record<string, unknown>>) => void,
  setNote: (note: string) => void,
): Promise<void> {
  if (!ready) {
    setNote(t('No stored jobs are on this page. A missing duration stays empty.'));
    return;
  }
  try {
    const body = await api.get<Record<string, unknown>>('/jobs');
    const rows = Array.isArray(body.rows) ? body.rows.filter((row): row is Record<string, unknown> => typeof row === 'object' && row !== null) : [];
    setJobs(rows);
  } catch {
    setNote(t('Jobs could not be loaded. The handler was not run.'));
  }
}

async function retryJob(api: QueryNovaApi, job: Record<string, unknown>, ready: boolean, setNote: (note: string) => void): Promise<void> {
  const fallback = 'Retry put the job back in the queue. The handler was not run.';
  if (!ready) {
    setNote(t(fallback));
    return;
  }
  try {
    const body = await api.post<Record<string, unknown>>('/jobs/retry', { id: job.id ?? 0 });
    setNote(typeof body.note === 'string' ? body.note : t(fallback));
  } catch {
    setNote(t(fallback));
  }
}

async function loadLogs(
  api: QueryNovaApi,
  ready: boolean,
  filters: Record<string, string>,
  setRows: (rows: Array<Record<string, unknown>>) => void,
  setNote: (note: string) => void,
): Promise<void> {
  if (!ready) {
    setNote(t('The log viewer omits secrets.'));
    return;
  }
  const params = new URLSearchParams();
  for (const [key, value] of Object.entries(filters)) {
    if (value.trim() !== '') {
      params.set(key, value.trim());
    }
  }
  try {
    const body = await api.get<Record<string, unknown>>(`/logs?${params.toString()}`);
    const rows = Array.isArray(body.rows) ? body.rows.filter((row): row is Record<string, unknown> => typeof row === 'object' && row !== null) : [];
    setRows(rows);
    setNote(typeof body.note === 'string' ? body.note : t('The log viewer omits secrets.'));
  } catch {
    setNote(t('Logs could not be filtered. Secrets are omitted.'));
  }
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
