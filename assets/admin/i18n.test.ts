import { afterEach, describe, expect, it } from 'vitest';
import { t } from './i18n';

describe('admin translations', () => {
  afterEach(() => {
    delete (globalThis as { window?: unknown }).window;
  });

  it('returns the source string until WordPress i18n is present', () => {
    expect(t('Nothing recorded.')).toBe('Nothing recorded.');
  });

  it('uses the querynova text domain', () => {
    (globalThis as unknown as { window: { wp: { i18n: { __: (text: string, domain: string) => string } } } }).window = {
      wp: {
        i18n: {
          __(text: string, domain: string) {
            return `${domain}:${text}`;
          },
        },
      },
    };

    expect(t('Setup')).toBe('querynova:Setup');
  });
});
