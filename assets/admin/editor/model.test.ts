import { describe, expect, it } from 'vitest';
import {
  dangerous,
  editorRequestBody,
  EDITOR_TABS,
  elementorAdapter,
  fieldsFrom,
  searchPreview,
  shouldSendEditorSave,
} from './model';

describe('editor panel', () => {
  it('uses the six document tabs and keeps Elementor unmounted', () => {
    expect([...EDITOR_TABS]).toEqual(['General', 'Advanced', 'Schema', 'Social', 'AI/Content', 'Links']);
    expect(elementorAdapter([{ id: 'elementor', label: 'Elementor', active: false, mounted: false }])).toEqual({
      id: 'elementor',
      label: 'Elementor',
      active: false,
      mounted: false,
    });
  });

  it('previews the stored title and labels noindex and canonical as dangerous', () => {
    const fields = fieldsFrom({
      title: 'Stored title',
      description: 'Stored description',
      canonical: 'https://example.test/guide',
      robots_index: 'noindex',
      focus_keyword: 'lager, pilsner',
    });
    expect(searchPreview(fields, 'Post title', 'https://example.test/guide').title).toBe('Stored title');
    expect(searchPreview(fieldsFrom({}), 'Post title', 'https://example.test/post')).toEqual({
      title: 'Post title',
      description: '',
      url: 'https://example.test/post',
    });
    expect(dangerous(fields)).toEqual({ robots: true, canonical: true });
    expect(dangerous(fieldsFrom({})).robots).toBe(false);
  });

  it('sends one document and skips autosave', () => {
    const body = editorRequestBody(12, fieldsFrom({ title: 'One document' }));
    expect(body.object_id).toBe(12);
    expect(body.title).toBe('One document');
    expect(body).not.toHaveProperty('object_ids');
    expect(shouldSendEditorSave(false, true, false, true)).toBe(true);
    expect(shouldSendEditorSave(false, true, true, true)).toBe(false);
    expect(shouldSendEditorSave(true, true, false, true)).toBe(false);
    expect(shouldSendEditorSave(false, true, false, false)).toBe(false);
  });
});
