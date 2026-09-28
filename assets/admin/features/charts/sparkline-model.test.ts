import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { describe, expect, it } from 'vitest';
import { normalizePoints, readSparklines, sparklineGeometry } from './sparkline-model';

describe('sparklines', () => {
  it('leaves a missing point empty and keeps a stored zero', () => {
    const points = normalizePoints([
      { value: 4, kind: 'MEASURED' },
      { value: null, kind: 'MEASURED' },
      { value: 0, kind: 'ESTIMATED' },
    ]);
    const line = sparklineGeometry(points);

    expect(points[1]).toEqual({ value: null, kind: 'UNAVAILABLE' });
    expect(points[2]).toEqual({ value: 0, kind: 'ESTIMATED' });
    expect(line.segments).toEqual([]);
    expect(line.dots.map((dot) => dot.x)).toEqual([2, 118]);
    expect(line.kinds).toEqual(['MEASURED', 'ESTIMATED', 'UNAVAILABLE']);
  });

  it('breaks the line at a gap instead of drawing zero', () => {
    const line = sparklineGeometry(normalizePoints([
      { value: 1, kind: 'MEASURED' },
      { value: 2, kind: 'MEASURED' },
      { value: null, kind: 'UNAVAILABLE' },
      { value: 4, kind: 'ATTRIBUTED' },
      { value: 5, kind: 'ATTRIBUTED' },
    ]));

    expect(line.segments).toHaveLength(2);
    expect(line.dots).toEqual([]);
    expect(line.kinds).toEqual(['MEASURED', 'ATTRIBUTED', 'UNAVAILABLE']);
    expect(line.segments[0].startsWith('M2.00')).toBe(true);
    expect(line.segments[1].startsWith('M89.00')).toBe(true);
  });

  it('draws nothing when every point is missing', () => {
    const line = sparklineGeometry(normalizePoints([{ value: null }, { value: 'missing' }]));
    expect(line.segments).toEqual([]);
    expect(line.dots).toEqual([]);
    expect(line.kinds).toEqual(['UNAVAILABLE']);
  });

  it('reads only series that have stored points', () => {
    const series = readSparklines({
      series: [
        { id: 'rank', label: 'Rank position', points: [] },
        { id: 'clicks', label: 'Clicks', points: [{ value: 3, kind: 'MEASURED' }] },
      ],
      chart: true,
    });
    expect(series.map((item) => item.id)).toEqual(['clicks']);
  });

  it('does not depend on a chart package', () => {
    const source = readFileSync(resolve('assets/admin/features/charts/sparkline-model.ts'), 'utf8');
    const screen = readFileSync(resolve('assets/admin/features/charts/Sparkline.tsx'), 'utf8');
    expect(source + screen).not.toMatch(/uplot|chart\.js|chartjs/i);
    expect(source).not.toContain('fetch(');
  });
});
