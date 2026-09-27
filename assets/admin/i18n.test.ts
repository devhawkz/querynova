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

  it('sends schema and setup field labels through the text domain', () => {
    const sources = import.meta.glob('./features/{setup,schema}/*.tsx', {
      query: '?raw',
      eager: true,
      import: 'default',
    });
    const setup = sources['./features/setup/SetupScreen.tsx'];
    const schema = sources['./features/schema/SchemaBuilder.tsx'];
    expect(setup).toContain("t('Site type')");
    expect(setup).toContain("t('Organization')");
    expect(setup).toContain("t('Search Console')");
    expect(setup).toContain("t('Crawler origin')");
    expect(schema).toContain("t('Schema type')");
    expect(schema).toContain("t('Condition source')");
    expect(schema).toContain("t('Field, template, or value')");
  });
});
