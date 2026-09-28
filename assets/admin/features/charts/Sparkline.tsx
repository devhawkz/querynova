import { provenanceLabel, type ProvenanceKind } from '../../core/provenance';
import { t } from '../../i18n';
import { readSparklines, sparklineGeometry, type SparkPoint } from './sparkline-model';

const BADGE: Record<ProvenanceKind, string> = {
  MEASURED: 'success',
  ATTRIBUTED: 'info',
  ESTIMATED: 'opportunity',
  UNAVAILABLE: 'not-configured',
};

export function SparklineList({ source, ids }: { source: unknown; ids: string[] }) {
  const series = readSparklines(source).filter((item) => ids.includes(item.id));
  if (series.length === 0) {
    return <p>{t('Not available. A missing point is not drawn as zero.')}</p>;
  }
  return (
    <div>
      {series.map((item) => (
        <Sparkline key={item.id} label={item.label} points={item.points} />
      ))}
    </div>
  );
}

export function Sparkline({ label, points }: { label: string; points: SparkPoint[] }) {
  const line = sparklineGeometry(points);
  const drawn = line.segments.length > 0 || line.dots.length > 0;
  return (
    <figure className="qn-sparkline">
      <figcaption>
        {t(label)}
        {line.kinds.map((kind) => (
          <span key={kind} className="qn-badge" data-state={BADGE[kind]}>{provenanceLabel(kind)}</span>
        ))}
      </figcaption>
      {drawn ? (
        <svg className="qn-spark" viewBox="0 0 120 32" role="img" aria-label={t(label)}>
          {line.segments.map((path) => (
            <path key={path} d={path} />
          ))}
          {line.dots.map((dot) => (
            <circle key={`${dot.x}-${dot.y}`} cx={dot.x} cy={dot.y} r="1.5" />
          ))}
        </svg>
      ) : (
        <p>{t('Not available. A missing point is not drawn as zero.')}</p>
      )}
    </figure>
  );
}
