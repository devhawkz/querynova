export interface TrackedKeyword {
  id: number;
  keyword: string;
  group: string;
  location: string;
  language: string;
  device: string;
  country: string;
}

export function trackedKeywords(input: unknown): TrackedKeyword[] {
  const record = isRecord(input) ? input : {};
  if (!Array.isArray(record.keywords)) {
    return [];
  }
  return record.keywords.filter(isRecord).map((row) => ({
    id: typeof row.id === 'number' ? row.id : 0,
    keyword: typeof row.keyword === 'string' ? row.keyword : '',
    group: typeof row.group === 'string' ? row.group : '',
    location: typeof row.location === 'string' ? row.location : '',
    language: typeof row.language === 'string' ? row.language : '',
    device: typeof row.device === 'string' ? row.device : '',
    country: typeof row.country === 'string' ? row.country : '',
  })).filter((row) => row.keyword !== '');
}

export function historyNote(input: unknown): string {
  const record = isRecord(input) ? input : {};
  const history = isRecord(record.history) ? record.history : {};
  return typeof history.note === 'string' && history.note !== ''
    ? history.note
    : 'No stored rank history. A missing rank is not zero. No SERP vendor is selected.';
}

export function historyRows(input: unknown): Array<{ position: string; url: string; device: string }> {
  const record = isRecord(input) ? input : {};
  const history = isRecord(record.history) ? record.history : {};
  if (!Array.isArray(history.rows)) {
    return [];
  }
  return history.rows.filter(isRecord).map((row) => ({
    position: typeof row.position === 'number' ? String(row.position) : 'Not available',
    url: typeof row.url === 'string' ? row.url : '',
    device: typeof row.device === 'string' ? row.device : '',
  }));
}

export function indexState(input: unknown): string {
  const record = isRecord(input) ? input : {};
  const status = isRecord(record.index_status) ? record.index_status : {};
  return status.state === 'Supplied' ? 'Supplied' : 'Not available';
}

export function trendState(input: unknown): string {
  const record = isRecord(input) ? input : {};
  const trends = isRecord(record.trends) ? record.trends : {};
  return trends.state === 'Supplied' ? 'Supplied' : 'Not available';
}

function isRecord(input: unknown): input is Record<string, unknown> {
  return typeof input === 'object' && input !== null;
}
