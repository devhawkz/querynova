import type { ProvenanceKind } from '../../core/provenance';

export interface SparkPoint {
  value: number | null;
  kind: ProvenanceKind;
}

export interface SparkSeries {
  id: string;
  label: string;
  points: SparkPoint[];
}

export interface SparkGeometry {
  segments: string[];
  dots: Array<{ x: number; y: number }>;
  kinds: ProvenanceKind[];
}

const KINDS: ProvenanceKind[] = ['MEASURED', 'ATTRIBUTED', 'ESTIMATED', 'UNAVAILABLE'];

export function readSparklines(input: unknown): SparkSeries[] {
  const record = isRecord(input) ? input : {};
  if (!Array.isArray(record.series)) {
    return [];
  }
  return record.series.filter(isRecord).flatMap((row) => {
    const points = normalizePoints(row.points);
    if (points.length === 0) {
      return [];
    }
    return [{
      id: typeof row.id === 'string' ? row.id : '',
      label: typeof row.label === 'string' && row.label !== '' ? row.label : 'Trend',
      points,
    }];
  });
}

export function normalizePoints(input: unknown): SparkPoint[] {
  if (!Array.isArray(input)) {
    return [];
  }
  return input.filter(isRecord).map((row) => {
    const value = typeof row.value === 'number' && Number.isFinite(row.value) ? row.value : null;
    return {
      value,
      kind: value === null ? 'UNAVAILABLE' : storedKind(row.kind),
    };
  });
}

export function sparklineGeometry(points: SparkPoint[], width = 120, height = 32): SparkGeometry {
  const kinds = KINDS.filter((kind) => points.some((point) => point.kind === kind));
  const numbers = points.flatMap((point) => (point.value === null ? [] : [point.value]));
  if (numbers.length === 0) {
    return { segments: [], dots: [], kinds: kinds.length === 0 ? ['UNAVAILABLE'] : kinds };
  }
  const min = Math.min(...numbers);
  const max = Math.max(...numbers);
  const span = max - min;
  const pad = 2;
  const xAt = (index: number) => (
    points.length === 1 ? width / 2 : pad + (index * (width - pad * 2)) / (points.length - 1)
  );
  const yAt = (value: number) => (
    span === 0 ? height / 2 : pad + (1 - (value - min) / span) * (height - pad * 2)
  );
  const segments: string[] = [];
  const dots: Array<{ x: number; y: number }> = [];
  let run: Array<{ x: number; y: number }> = [];
  const flush = () => {
    if (run.length >= 2) {
      segments.push(run.map((point, index) => `${index === 0 ? 'M' : 'L'}${round(point.x)} ${round(point.y)}`).join(' '));
    } else if (run.length === 1) {
      dots.push(run[0]);
    }
    run = [];
  };
  points.forEach((point, index) => {
    if (point.value === null) {
      flush();
      return;
    }
    run.push({ x: xAt(index), y: yAt(point.value) });
  });
  flush();
  return { segments, dots, kinds };
}

function storedKind(value: unknown): ProvenanceKind {
  if (value === 'ATTRIBUTED' || value === 'ESTIMATED' || value === 'MEASURED') {
    return value;
  }
  return 'MEASURED';
}

function round(value: number): string {
  return value.toFixed(2);
}

function isRecord(input: unknown): input is Record<string, unknown> {
  return typeof input === 'object' && input !== null;
}
