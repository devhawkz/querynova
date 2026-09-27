import { t } from '../i18n';

export type ProvenanceKind = 'MEASURED' | 'ATTRIBUTED' | 'ESTIMATED' | 'UNAVAILABLE';

export interface MetricPayload {
  value: number | null;
  kind: ProvenanceKind;
  label: string;
  reason?: string;
}

const labels: Record<ProvenanceKind, string> = {
  MEASURED: 'Measured',
  ATTRIBUTED: 'Attributed',
  ESTIMATED: 'Estimated',
  UNAVAILABLE: 'Unavailable',
};

export function provenanceLabel(kind: ProvenanceKind): string {
  return t(labels[kind]);
}

export function formatMetric(metric: MetricPayload): string {
  if (metric.kind === 'UNAVAILABLE' || metric.value === null) {
    return t('Unavailable');
  }
  const rounded = Math.round(metric.value);
  return `${rounded} · ${provenanceLabel(metric.kind)}`;
}
