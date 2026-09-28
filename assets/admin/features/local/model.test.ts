import { describe, expect, it } from 'vitest';
import { localPlan, locationPlan } from './model';

describe('local seo workspace', () => {
  it('stores the module only after confirmation and does not change URLs', () => {
    expect(localPlan(true, false).stored).toBe(false);
    expect(localPlan(true, false).urlChanged).toBe(false);
    expect(localPlan(true, true).enabled).toBe(true);
    expect(localPlan(true, true).flushed).toBe(false);
    expect(locationPlan('Taproom', true, false).stored).toBe(false);
    expect(locationPlan('Taproom', true, true).urlChanged).toBe(false);
    expect(locationPlan('  ', true, true).stored).toBe(false);
  });
});
