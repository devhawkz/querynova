import { describe, expect, it } from 'vitest';
import { DRAFT_KINDS, automationPlan, contentLead, gapTopics, generateDraft, healthReport, modelConnection, storeDraft } from './model';

describe('content workspace', () => {
  it('keeps decay and cannibalization off a crawl and a cause', () => {
    expect(healthReport('decay').crawled).toBe(false);
    expect(healthReport('decay').causation).toBe(false);
    expect(healthReport('decay').note).toContain('missing side stays empty');
    expect(healthReport('cannibalization').note).toContain('does not claim a cause');
  });

  it('explains the next step and does not score the document', () => {
    expect(contentLead(false)).toBe('Supply a document. QueryNova does not fetch the URL.');
    expect(contentLead(true)).toContain('not a content score');
    expect(contentLead(false)).not.toBe('Nothing recorded.');
  });

  it('keeps a missing model not connected and does not create a generated draft', () => {
    const missing = modelConnection('  ');
    const draft = generateDraft('', 'outline', 'A supplied outline');

    expect(missing.status).toBe('not_connected');
    expect(missing.called).toBe(false);
    expect(missing.note).toContain('did not call a model');
    expect(draft.status).toBe('not_connected');
    expect(draft.stored).toBe(false);
    expect(draft.published).toBe(false);
    expect(draft.page_changed).toBe(false);
    expect(draft.called).toBe(false);
    expect(DRAFT_KINDS).toContain('faq');
    expect(DRAFT_KINDS).toContain('product_copy');
    expect(DRAFT_KINDS).toContain('category_copy');
  });

  it('stores supplied draft text without publishing it', () => {
    const stored = storeDraft('titles', 'A title');
    const empty = storeDraft('metas', ' ');

    expect(stored.stored).toBe(true);
    expect(stored.published).toBe(false);
    expect(stored.page_changed).toBe(false);
    expect(stored.called).toBe(false);
    expect(empty.stored).toBe(false);
  });

  it('leaves link and keyword rules Suggest Only unless one rule is enabled', () => {
    const off = automationPlan('internal_links', false);
    const on = automationPlan('keyword_links', true);

    expect(off.internal_links).toBe('Suggest Only');
    expect(off.keyword_links).toBe('Suggest Only');
    expect(off.inserted).toBe(false);
    expect(on.keyword_links).toBe('Automated');
    expect(on.internal_links).toBe('Suggest Only');
    expect(on.inserted).toBe(false);
    expect(on.page_changed).toBe(false);
    expect(on.note).toContain('one rule');
  });

  it('does not turn a missing gap into zero', () => {
    const missing = gapTopics([{ topic: 'amperage', classification: null, on_our_page: null }]);
    const found = gapTopics([{ topic: 'amperage', classification: 'important', on_our_page: false }]);

    expect(missing.status).toBe('UNAVAILABLE');
    expect(missing.topics).toBeNull();
    expect(found.topics).toEqual(['amperage']);
  });
});
