import { describe, expect, it } from 'vitest';
import { previewImport, SCHEMA_TEMPLATES, SPEAKABLE_NOTE, templateProperties, videoDocument } from './studio';
import { SCHEMA_TYPES } from './rules';

describe('schema studio', () => {
  it('offers template cards and does not dump blank fields', () => {
    expect(SCHEMA_TEMPLATES.map((template) => template.id)).toEqual(['article', 'product', 'breadcrumb', 'faq']);
    expect(templateProperties('article')).toEqual(['headline', 'description']);
    expect(templateProperties('product')).toEqual(['name', 'description']);
    expect(templateProperties('breadcrumb')).toEqual(['itemListElement']);
    expect(templateProperties('faq')).toEqual(['mainEntity']);
    const fields = SCHEMA_TEMPLATES.reduce((count, template) => count + template.properties.length, 0);
    expect(fields).toBe(6);
    expect(fields).toBeLessThan(60);
    expect(SCHEMA_TYPES).not.toContain('Speakable');
    expect(SPEAKABLE_NOTE).toContain('headline and a CSS selector');
    expect(videoDocument('Pour', 'https://example.test/lager.mp4').fetched).toBe(false);
    expect(videoDocument('Pour', '').ready).toBe(false);
  });

  it('previews supplied JSON-LD properties and does not save', () => {
    const preview = previewImport('{"@type":"Article","headline":"Lager","description":"","image":""}');
    expect(preview.valid).toBe(true);
    expect(preview.saved).toBe(false);
    expect(preview.properties).toEqual(['headline']);
    expect(preview.note).toContain('Nothing was saved');
  });

  it('keeps a supplied FAQ property and drops an empty one', () => {
    const preview = previewImport('{"@type":"FAQPage","name":"Questions","mainEntity":""}');
    expect(preview.valid).toBe(true);
    expect(preview.saved).toBe(false);
    expect(preview.properties).toEqual(['name']);
    const empty = previewImport('{"@type":"FAQPage","name":"","mainEntity":""}');
    expect(empty.valid).toBe(true);
    expect(empty.properties).toEqual([]);
    const unsupported = previewImport('{"@type":"HowTo","name":"Steps"}');
    expect(unsupported.valid).toBe(false);
    expect(unsupported.saved).toBe(false);
  });
});
