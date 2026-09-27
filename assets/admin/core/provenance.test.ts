import { describe, expect, it } from 'vitest';
import { formatMetric, provenanceLabel } from './provenance';

describe('provenance labels', () => {
  it('never labels an estimate as measured', () => {
    expect(provenanceLabel('ESTIMATED')).toBe('Estimated');
    expect(formatMetric({ value: 87.3649, kind: 'ESTIMATED', label: 'Estimated' })).toBe('87 · Estimated');
    expect(formatMetric({ value: 87.3649, kind: 'ESTIMATED', label: 'Estimated' })).not.toContain('Measured');
  });

  it('renders missing data as unavailable', () => {
    expect(formatMetric({ value: null, kind: 'UNAVAILABLE', label: 'Unavailable' })).toBe('Unavailable');
  });
});
