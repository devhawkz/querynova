import { displayValue, normalizeDiagnostics, reportText } from './model';

interface Props {
  diagnostics: unknown;
}

export function DiagnosticsScreen({ diagnostics }: Props) {
  const report = normalizeDiagnostics(diagnostics);
  const text = reportText(report);
  return (
    <section aria-labelledby="qn-diagnostics">
      <h1 id="qn-diagnostics">Diagnostics</h1>
      <dl>
        <dt>Environment</dt>
        <dd>{displayValue(report.environment)}</dd>
        <dt>QueryNova version</dt>
        <dd>{displayValue(report.querynovaVersion)}</dd>
        <dt>WP version</dt>
        <dd>{displayValue(report.wpVersion)}</dd>
        <dt>PHP version</dt>
        <dd>{displayValue(report.phpVersion)}</dd>
        <dt>WooCommerce version</dt>
        <dd>{displayValue(report.wooCommerceVersion)}</dd>
        <dt>DB version</dt>
        <dd>{displayValue(report.dbVersion)}</dd>
        <dt>Schema version</dt>
        <dd>{displayValue(report.schemaVersion)}</dd>
        <dt>Cron</dt>
        <dd>{displayValue(report.cronScheduled)}</dd>
        <dt>Cache</dt>
        <dd>{displayValue(report.cacheAdapter)}. Hits {displayValue(report.cacheHits)}</dd>
      </dl>
      <h2>Modules</h2>
      {report.modules.length === 0 ? <p>Nothing recorded.</p> : <ul>{report.modules.map((name) => <li key={name}>{name}</li>)}</ul>}
      <h2>Providers</h2>
      {report.providers.length === 0 ? (
        <p>Nothing recorded.</p>
      ) : (
        <ul>
          {report.providers.map((provider) => (
            <li key={provider.name}>
              {provider.name} {provider.state === 'not_configured' ? 'Not configured' : provider.state}
            </li>
          ))}
        </ul>
      )}
      <h2>Queue</h2>
      {Object.keys(report.queue).length === 0 ? (
        <p>Nothing recorded.</p>
      ) : (
        <ul>
          {Object.entries(report.queue).map(([status, count]) => (
            <li key={status}>
              {status} {count}
            </li>
          ))}
        </ul>
      )}
      <h2>Migrations</h2>
      {report.pendingMigrations.length === 0 ? <p>Nothing recorded.</p> : <ul>{report.pendingMigrations.map((version) => <li key={version}>{version}</li>)}</ul>}
      <h2>Recent errors</h2>
      {report.recentErrors.length === 0 ? (
        <p>Nothing recorded.</p>
      ) : (
        <ul>
          {report.recentErrors.map((error) => (
            <li key={`${error.reference}-${error.message}`}>
              {error.message} {error.reference}
            </li>
          ))}
        </ul>
      )}
      <h2>System report</h2>
      <textarea readOnly value={text} aria-label="System report" />
      <button
        type="button"
        onClick={() => {
          if (typeof navigator !== 'undefined' && navigator.clipboard) {
            void navigator.clipboard.writeText(text);
          }
        }}
      >
        Copy system report
      </button>
      <button type="button" onClick={() => downloadReport(text)}>
        Download diagnostic report
      </button>
    </section>
  );
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
