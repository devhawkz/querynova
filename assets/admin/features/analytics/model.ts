export const ANALYTICS_SCREENS = [
  'overview',
  'seo_performance',
  'keywords',
  'content',
  'rank_tracker',
  'index_status',
  'traffic',
  'commerce',
  'ai',
] as const;

export interface KpiCard {
  id: string;
  label: string;
  value: number | null;
  state: string;
  text: string;
}

export interface MovementRow {
  label: string;
  detail: string;
}

export function metricText(value: unknown, state: unknown): string {
  if (typeof value === 'number' && Number.isFinite(value)) {
    return String(value);
  }
  return state === 'Not connected' ? 'Not connected' : 'Not available';
}

export function connectionState(input: unknown): string {
  const record = isRecord(input) ? input : {};
  return record.state === 'Connected' ? 'Connected' : 'Not connected';
}

export function kpiCards(input: unknown): KpiCard[] {
  const record = isRecord(input) ? input : {};
  if (!Array.isArray(record.kpis)) {
    return [];
  }
  return record.kpis.filter(isRecord).map((row) => {
    const state = typeof row.state === 'string' ? row.state : 'Not available';
    const value = typeof row.value === 'number' && Number.isFinite(row.value) ? row.value : null;
    return {
      id: typeof row.id === 'string' ? row.id : '',
      label: typeof row.label === 'string' ? row.label : '',
      value,
      state,
      text: metricText(value, state),
    };
  });
}

export function keywordMovement(input: unknown, key: 'winning_keywords' | 'losing_keywords'): string[] | null {
  const comparison = comparisonRecord(input);
  const rows = comparison[key];
  if (rows === null || rows === undefined) {
    return null;
  }
  if (!Array.isArray(rows)) {
    return null;
  }
  return rows.filter((row): row is string => typeof row === 'string');
}

export function postMovement(input: unknown, key: 'winning_posts' | 'losing_posts'): MovementRow[] | null {
  const comparison = comparisonRecord(input);
  const rows = comparison[key];
  if (!Array.isArray(rows)) {
    return null;
  }
  return rows.filter(isRecord).map((row) => ({
    label: `Page ${typeof row.page_id === 'number' ? row.page_id : ''}`,
    detail: `${metricText(row.clicks, 'Measured')} clicks, previous ${metricText(row.previous_clicks, 'Measured')}`,
  }));
}

export function availabilityState(input: unknown): string {
  const record = isRecord(input) ? input : {};
  return record.state === 'Supplied' ? 'Supplied' : 'Not available';
}

export function timingText(value: unknown): string {
  return metricText(value, 'Not available');
}

export function hasChart(input: unknown): boolean {
  return isRecord(input) && (Object.prototype.hasOwnProperty.call(input, 'chart') || Object.prototype.hasOwnProperty.call(input, 'series'));
}

export function previousPeriod(input: unknown): string {
  const range = isRecord(input) && isRecord(input.range) ? input.range : {};
  const start = typeof range.previous_start === 'string' ? range.previous_start : '';
  const end = typeof range.previous_end === 'string' ? range.previous_end : '';
  if (start === '' || end === '') {
    return 'Previous period is not available.';
  }
  return `${start} to ${end}`;
}

function comparisonRecord(input: unknown): Record<string, unknown> {
  const record = isRecord(input) ? input : {};
  return isRecord(record.comparison) ? record.comparison : {};
}

function isRecord(input: unknown): input is Record<string, unknown> {
  return typeof input === 'object' && input !== null;
}
