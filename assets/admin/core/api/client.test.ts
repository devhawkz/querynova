import { describe, expect, it } from 'vitest';
import { apiErrorDetail, joinRestUrl, QueryNovaApi } from './client';

describe('querynova api client', () => {
  it('joins pretty and plain permalink REST urls', () => {
    expect(joinRestUrl('https://example.test/wp-json/querynova/v1', '/schema/rules')).toBe(
      'https://example.test/wp-json/querynova/v1/schema/rules',
    );
    expect(joinRestUrl('https://example.test/index.php?rest_route=/querynova/v1', '/schema/rules')).toBe(
      'https://example.test/index.php?rest_route=/querynova/v1/schema/rules',
    );
  });

  it('keeps the server message when schema rules are forbidden', async () => {
    const api = new QueryNovaApi(
      { restUrl: 'https://example.test/wp-json/querynova/v1', nonce: 'nonce', version: '0.1.2', environment: 'production' },
      async () =>
        new Response(JSON.stringify({ code: 'rest_forbidden', message: 'Sorry, you are not allowed to do that.', data: { status: 401 } }), {
          status: 401,
          headers: { 'Content-Type': 'application/json' },
        }),
    );

    await expect(api.get('/schema/rules')).rejects.toMatchObject({
      status: 401,
      message: 'Sorry, you are not allowed to do that.',
    });
  });

  it('does not throw a parse error when the body is HTML', async () => {
    const api = new QueryNovaApi(
      { restUrl: 'https://example.test/wp-json/querynova/v1/', nonce: 'nonce', version: '0.1.2', environment: 'production' },
      async () => new Response('<html>not json</html>', { status: 404 }),
    );

    const error = await api.get('/schema/rules').then(
      () => null,
      (caught: unknown) => caught,
    );
    expect(apiErrorDetail(error)).toContain('HTTP 404');
    expect(apiErrorDetail(error)).toContain('The server did not return JSON.');
  });
});
