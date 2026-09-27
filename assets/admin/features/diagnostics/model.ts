export interface DiagnosticError {
  message: string;
  reference: string;
}

export interface DiagnosticsModel {
  environment: string;
  querynovaVersion: string;
  wpVersion: string | null;
  phpVersion: string;
  wooCommerceVersion: string | null;
  dbVersion: string | null;
  schemaVersion: string;
  modules: string[];
  providers: Array<{ name: string; state: string }>;
  queue: Record<string, number>;
  cronScheduled: boolean | null;
  cacheAdapter: string;
  cacheHits: null;
  pendingMigrations: string[];
  recentErrors: DiagnosticError[];
}

export function emptyDiagnostics(): DiagnosticsModel {
  return {
    environment: '',
    querynovaVersion: '',
    wpVersion: null,
    phpVersion: '',
    wooCommerceVersion: null,
    dbVersion: null,
    schemaVersion: '',
    modules: [],
    providers: [],
    queue: {},
    cronScheduled: null,
    cacheAdapter: '',
    cacheHits: null,
    pendingMigrations: [],
    recentErrors: [],
  };
}

export function normalizeDiagnostics(input: unknown): DiagnosticsModel {
  const report = emptyDiagnostics();
  if (typeof input !== 'object' || input === null) {
    return report;
  }
  const record = input as Record<string, unknown>;
  report.environment = text(record.environment);
  report.querynovaVersion = text(record.querynova_version);
  report.wpVersion = optionalText(record.wp_version);
  report.phpVersion = text(record.php_version);
  report.wooCommerceVersion = optionalText(record.woocommerce_version);
  report.dbVersion = null;
  report.schemaVersion = text(record.schema_version);
  report.modules = strings(record.modules);
  report.providers = providers(record.providers);
  report.queue = counts(record.queue);
  const cron = record.cron;
  if (typeof cron === 'object' && cron !== null) {
    const scheduled = (cron as Record<string, unknown>).scheduled;
    report.cronScheduled = typeof scheduled === 'boolean' ? scheduled : null;
  }
  const cache = record.cache;
  if (typeof cache === 'object' && cache !== null) {
    report.cacheAdapter = text((cache as Record<string, unknown>).adapter);
  }
  const migrations = record.migrations;
  if (typeof migrations === 'object' && migrations !== null) {
    report.pendingMigrations = strings((migrations as Record<string, unknown>).pending);
  }
  report.recentErrors = errors(record.recent_errors);
  return report;
}

export function displayValue(value: string | number | boolean | null): string {
  if (value === null || value === '') {
    return 'Unavailable';
  }
  if (typeof value === 'boolean') {
    return value ? 'Scheduled' : 'Not scheduled';
  }
  return scrub(String(value));
}

export function reportText(report: DiagnosticsModel): string {
  return JSON.stringify(
    {
      environment: displayValue(report.environment),
      querynova_version: displayValue(report.querynovaVersion),
      wp_version: displayValue(report.wpVersion),
      php_version: displayValue(report.phpVersion),
      woocommerce_version: displayValue(report.wooCommerceVersion),
      db_version: displayValue(report.dbVersion),
      schema_version: displayValue(report.schemaVersion),
      modules: report.modules.map(scrub),
      providers: report.providers.map((provider) => ({
        name: scrub(provider.name),
        state: provider.state === 'not_configured' ? 'Not configured' : scrub(provider.state),
      })),
      queue: report.queue,
      cron: displayValue(report.cronScheduled),
      cache: {
        adapter: displayValue(report.cacheAdapter),
        hits: 'Unavailable',
      },
      pending_migrations: report.pendingMigrations.map(scrub),
      recent_errors: report.recentErrors.map((error) => ({
        message: scrub(error.message),
        reference: scrub(error.reference),
      })),
    },
    null,
    2,
  );
}

function text(value: unknown): string {
  return typeof value === 'string' ? value.trim() : '';
}

function optionalText(value: unknown): string | null {
  const trimmed = text(value);
  return trimmed === '' ? null : trimmed;
}

function strings(value: unknown): string[] {
  if (!Array.isArray(value)) {
    return [];
  }
  return value.filter((item): item is string => typeof item === 'string' && item.trim() !== '').map((item) => item.trim());
}

function providers(value: unknown): Array<{ name: string; state: string }> {
  if (!Array.isArray(value)) {
    return [];
  }
  const rows: Array<{ name: string; state: string }> = [];
  for (const item of value) {
    if (typeof item !== 'object' || item === null) {
      continue;
    }
    const record = item as Record<string, unknown>;
    const name = text(record.name);
    if (name === '') {
      continue;
    }
    rows.push({ name, state: text(record.state) === '' ? 'not_configured' : text(record.state) });
  }
  return rows;
}

function counts(value: unknown): Record<string, number> {
  if (typeof value !== 'object' || value === null) {
    return {};
  }
  const counts: Record<string, number> = {};
  for (const [key, count] of Object.entries(value)) {
    if (typeof count === 'number' && Number.isFinite(count)) {
      counts[key] = count;
    }
  }
  return counts;
}

function errors(value: unknown): DiagnosticError[] {
  if (!Array.isArray(value)) {
    return [];
  }
  const rows: DiagnosticError[] = [];
  for (const item of value) {
    if (typeof item !== 'object' || item === null) {
      continue;
    }
    const record = item as Record<string, unknown>;
    const message = scrub(text(record.message));
    if (message === '') {
      continue;
    }
    rows.push({ message, reference: scrub(text(record.reference)) });
  }
  return rows;
}

function scrub(value: string): string {
  return value
    .replace(/Bearer\s+[A-Za-z0-9\-._~+/]+=*/gi, 'Bearer [redacted]')
    .replace(/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/gi, '[redacted]');
}
