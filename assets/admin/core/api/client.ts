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

  private async send<T>(method: 'GET' | 'PUT', path: string, payload: unknown, timeoutMs: number): Promise<T> {
    const controller = new AbortController();
    const timer = setTimeout(() => controller.abort(), timeoutMs);
    const requestId = crypto.randomUUID();
    const headers: Record<string, string> = {
      'X-WP-Nonce': this.boot.nonce,
      'X-QueryNova-Request': requestId,
      Accept: 'application/json',
    };
    if (payload !== undefined) {
      headers['Content-Type'] = 'application/json';
    }
    try {
      const response = await this.fetchImpl(`${this.boot.restUrl.replace(/\/$/, '')}${path}`, {
        method,
        headers,
        body: payload === undefined ? undefined : JSON.stringify(payload),
        signal: controller.signal,
      });
      const body = (await response.json()) as { message?: string; data?: { error_reference?: string } };
      if (!response.ok) {
        const error: ApiError = {
          message: body.message ?? 'Request failed',
          status: response.status,
          errorReference: body.data?.error_reference ?? '',
        };
        throw error;
      }
      return body as T;
    } finally {
      clearTimeout(timer);
    }
  }
}
