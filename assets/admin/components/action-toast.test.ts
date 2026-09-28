import { describe, expect, it } from 'vitest';
import { missingSessionToast } from './action-toast';

describe('action toasts', () => {
  it('reports a save or queue that did not run and does not claim a rewrite', () => {
    expect(missingSessionToast('save')).toBe('Not saved. Live content was not changed.');
    expect(missingSessionToast('queue')).toBe('Not queued. The handler was not run.');
  });
});
