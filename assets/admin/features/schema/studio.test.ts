import { describe, expect, it } from 'vitest';
import { previewImport, SCHEMA_TEMPLATES, templateProperties } from './studio';

describe('schema studio', () => {
  it('offers three template cards and does not dump blank fields', () => {
    expect(SCHEMA_TEMPLATES.map((template) => template.id)).toEqual(['article', 'product', 'breadcrumb']);
    expect(templateProperties('article')).toEqual(['headline', 'description']);
    expect(templateProperties('product')).toEqual(['name', 'description']);
    expect(templateProperties('breadcrumb')).toEqual(['itemListElement']);
    const fields = SCHEMA_TEMPLATES.reduce((count, template) => count + template.properties.length, 0);
    expect(fields).toBe(5);
    expect(fields).toBeLessThan(60);
  });

  it('previews supplied JSON-LD properties and does not save', () => {
    const preview = previewImport('{"@type":"Article","headline":"Lager","description":"","image":""}');
    expect(preview.valid).toBe(true);
    expect(preview.saved).toBe(false);
    expect(preview.properties).toEqual(['headline']);
    expect(preview.note).toContain('Nothing was saved');
  });

  it('rejects an unsupported schema type', () => {
    const preview = previewImport('{"@type":"FAQPage","name":"Questions"}');
    expect(preview.valid).toBe(false);
    expect(preview.saved).toBe(false);
    expect(preview.properties).toEqual([]);
  });
});
