import { describe, expect, it } from 'vitest';
import { TEMPLATE_CONTEXTS, auditFindings, checklistNote, reportHasScore, statusLabel } from './model';

describe('on-page seo screen', () => {
  it('keeps the checklist note and does not treat a report as a score', () => {
    expect(TEMPLATE_CONTEXTS).toEqual([
      'homepage',
      'post',
      'page',
      'product',
      'product_category',
      'tag',
      'category',
      'author',
      'archive',
      'cpt',
      'taxonomy',
      'location',
    ]);
    expect(checklistNote({ note: 'This checklist does not predict rankings.' })).toBe('This checklist does not predict rankings.');
    expect(reportHasScore({ checks: [], note: 'This checklist does not predict rankings.' })).toBe(false);
    expect(statusLabel('failed')).toBe('Failed');
    expect(auditFindings({
      findings: [{ status: 'warning', explanation: 'Missing title', evidence: 'No title element was found.', how_to_fix: 'Add a title element. This audit does not rewrite titles.', url: 'https://example.test/a' }],
    })[0]).toMatchObject({ status: 'warning', howToFix: 'Add a title element. This audit does not rewrite titles.' });
  });
});
