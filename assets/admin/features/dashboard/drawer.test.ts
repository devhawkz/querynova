import { describe, expect, it } from 'vitest';
import { drawerDetail, drawerResult } from './drawer';

describe('opportunity drawer', () => {
  it('leaves missing evidence, risk, and URL empty', () => {
    const detail = drawerDetail({ title: 'Rewrite the title', rationale: 'Clicks fell', confidence: 'Medium' });
    expect(detail.why).toBe('Clicks fell');
    expect(detail.evidence).toBeNull();
    expect(detail.kpi).toBeNull();
    expect(detail.risks).toBeNull();
    expect(detail.url).toBeNull();
    expect(detail.page_changed).toBe(false);
  });

  it('records an action without changing the live page', () => {
    expect(drawerResult(false).page_changed).toBe(false);
    expect(drawerResult(true).published).toBe(false);
    expect(drawerResult(true).note).toContain('live page was not changed');
  });
});
