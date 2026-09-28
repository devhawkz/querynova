import { describe, expect, it } from 'vitest';
import { urlBasePlan } from '../commerce/workspace';
import { roleSavePlan, titleSavePlan } from './settings-edits';

describe('settings edits', () => {
  it('stores titles, roles, and URL bases only after confirmation and does not rewrite live content', () => {
    expect(titleSavePlan(false).stored).toBe(false);
    expect(titleSavePlan(true).rewritten).toBe(false);
    expect(roleSavePlan('editor', false).applied).toBe(false);
    expect(roleSavePlan('  ', true).stored).toBe(false);
    expect(roleSavePlan('editor', true).applied).toBe(false);
    const bases = urlBasePlan('shop', 'product-category', true);
    expect(bases.stored).toBe(true);
    expect(bases.applied).toBe(false);
    expect(bases.flushed).toBe(false);
  });
});
