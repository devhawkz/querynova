export interface QueryNovaBoot {
  restUrl: string;
  nonce: string;
  version: string;
  environment: string;
}

export interface ApiError {
  message: string;
  status: number;
  errorReference: string;
}

export function joinRestUrl(base: string, path: string): string {
  const route = path.startsWith('/') ? path : `/${path}`;
  return `${base.replace(/\/$/, '')}${route}`;
}

export function apiErrorDetail(error: unknown): string {
  if (typeof error !== 'object' || error === null) {
    return '';
  }
  const record = error as Partial<ApiError>;
  const message = typeof record.message === 'string' ? record.message.trim() : '';
  const reference = typeof record.errorReference === 'string' ? record.errorReference.trim() : '';
  const status = typeof record.status === 'number' ? String(record.status) : '';
  return [status === '' ? '' : `HTTP ${status}`, message, reference === '' ? '' : reference].filter((part) => part !== '').join(' ');
}

export class QueryNovaApi {
  constructor(
    private readonly boot: QueryNovaBoot,
    private readonly fetchImpl: typeof fetch = fetch,
  ) {}

  async get<T>(path: string, timeoutMs = 15000): Promise<T> {
    return this.send<T>('GET', path, undefined, timeoutMs);
  }

  async put<T>(path: string, payload: unknown, timeoutMs = 15000): Promise<T> {
    return this.send<T>('PUT', path, payload, timeoutMs);
  }

  async post<T>(path: string, payload: unknown, timeoutMs = 15000): Promise<T> {
    return this.send<T>('POST', path, payload, timeoutMs);
  }

  private async send<T>(method: 'GET' | 'PUT' | 'POST', path: string, payload: unknown, timeoutMs: number): Promise<T> {
    const controller = new AbortController();
    const timer = setTimeout(() => controller.abort(), timeoutMs);
    const requestId = typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function' ? crypto.randomUUID() : `qn-${Date.now()}`;
    const headers: Record<string, string> = {
      'X-WP-Nonce': this.boot.nonce,
      'X-QueryNova-Request': requestId,
      Accept: 'application/json',
    };
    if (payload !== undefined) {
      headers['Content-Type'] = 'application/json';
    }
    try {
      const response = await this.fetchImpl(joinRestUrl(this.boot.restUrl, path), {
        method,
        credentials: 'same-origin',
        headers,
        body: payload === undefined ? undefined : JSON.stringify(payload),
        signal: controller.signal,
      });
      const body = await readJson(response);
      if (!response.ok) {
        const data = body.data;
        const reference = typeof data === 'object' && data !== null && 'error_reference' in data && typeof data.error_reference === 'string'
          ? data.error_reference
          : '';
        const error: ApiError = {
          message: typeof body.message === 'string' && body.message.trim() !== '' ? body.message : 'Request failed',
          status: response.status,
          errorReference: reference,
        };
        throw error;
      }
      return body as T;
    } finally {
      clearTimeout(timer);
    }
  }
}

async function readJson(response: Response): Promise<Record<string, unknown>> {
  const text = await response.text();
  if (text.trim() === '') {
    return {};
  }
  try {
    const parsed = JSON.parse(text) as unknown;
    if (typeof parsed === 'object' && parsed !== null && !Array.isArray(parsed)) {
      return parsed as Record<string, unknown>;
    }
    return {};
  } catch {
    return { message: 'The server did not return JSON.' };
  }
}
