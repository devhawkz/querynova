import { useMemo, useState } from 'react';
import { QueryNovaApi } from '../../core/api/client';
import { t } from '../../i18n';
import { RankScreen } from '../rank/RankScreen';
import {
  ANALYTICS_SCREENS,
  availabilityState,
  connectionState,
  kpiCards,
  keywordMovement,
  postMovement,
  previousPeriod,
  timingText,
  type KpiCard,
} from './model';

interface Props {
  restUrl: string;
  nonce: string;
  analytics: unknown;
  rankTracker: unknown;
}

const SCREEN_LABELS: Record<(typeof ANALYTICS_SCREENS)[number], string> = {
  overview: 'Overview',
  seo_performance: 'SEO performance',
  keywords: 'Keywords',
  content: 'Content',
  rank_tracker: 'Rank tracker',
  index_status: 'Index status',
  traffic: 'Traffic',
  commerce: 'Commerce',
  ai: 'AI',
};

export function AnalyticsScreen({ restUrl, nonce, analytics, rankTracker }: Props) {
  const api = useMemo(() => new QueryNovaApi({ restUrl, nonce, version: '', environment: '' }), [restUrl, nonce]);
  const [report, setReport] = useState(analytics);
  const [section, setSection] = useState<(typeof ANALYTICS_SCREENS)[number]>('overview');
  const [searchProperty, setSearchProperty] = useState(propertyOf(analytics, 'search_console'));
  const [ga4Property, setGa4Property] = useState(propertyOf(analytics, 'ga4'));
  const [start, setStart] = useState(textOf(analytics, 'range', 'start'));
  const [end, setEnd] = useState(textOf(analytics, 'range', 'end'));
  const [message, setMessage] = useState('');
  const cards = kpiCards(report);
  const winningKeywords = keywordMovement(report, 'winning_keywords');
  const losingKeywords = keywordMovement(report, 'losing_keywords');
  const winningPosts = postMovement(report, 'winning_posts');
  const losingPosts = postMovement(report, 'losing_posts');

  async function connection(channel: 'search_console' | 'ga4', action: 'connect' | 'test' | 'disconnect' | 'resync') {
    if (restUrl === '' || nonce === '') {
      setMessage(t('The connection cannot be changed in this session.'));
      return;
    }
    const property = channel === 'search_console' ? searchProperty : ga4Property;
    try {
      const result = await api.post<unknown>('/analytics/connections', { channel, action, property, start, end });
      const record = isRecord(result) ? result : {};
      setMessage(typeof record.note === 'string' ? record.note : t('Not connected'));
      if (typeof record.state === 'string' && action !== 'test' && action !== 'resync') {
        setReport(replaceCard(report, channel, record));
      }
    } catch {
      setMessage(t('The connection request could not be completed. Metrics were not invented.'));
    }
  }

  async function applyRange() {
    if (restUrl === '' || nonce === '') {
      setMessage(t('The date range cannot be applied in this session.'));
      return;
    }
    try {
      const next = await api.get<unknown>(`/analytics/screens?start=${encodeURIComponent(start)}&end=${encodeURIComponent(end)}`);
      setReport(next);
      setMessage(t('Date range updated from stored data. This request did not call a provider.'));
    } catch {
      setMessage(t('The date range could not be applied.'));
    }
  }

  return (
    <>
      <section aria-labelledby="qn-analytics-connections">
        <h2 id="qn-analytics-connections">{t('Search Console and GA4')}</h2>
        <p>{t('Disconnected stays Not connected. Clicks, impressions, sessions, and revenue are not invented.')}</p>
        <ProviderCard
          title="Search Console"
          state={connectionState(cardOf(report, 'search_console'))}
          property={searchProperty}
          onProperty={setSearchProperty}
          onConnect={() => void connection('search_console', 'connect')}
          onTest={() => void connection('search_console', 'test')}
          onDisconnect={() => void connection('search_console', 'disconnect')}
          onResync={() => void connection('search_console', 'resync')}
        />
        <ProviderCard
          title="GA4"
          state={connectionState(cardOf(report, 'ga4'))}
          property={ga4Property}
          onProperty={setGa4Property}
          onConnect={() => void connection('ga4', 'connect')}
          onTest={() => void connection('ga4', 'test')}
          onDisconnect={() => void connection('ga4', 'disconnect')}
          onResync={() => void connection('ga4', 'resync')}
        />
      </section>
      <section aria-labelledby="qn-analytics-range">
        <h2 id="qn-analytics-range">{t('Date range')}</h2>
        <p>{t('Previous period')} {previousPeriod(report)}</p>
        <label>
          {t('Start')}
          <input value={start} onChange={(event) => setStart(event.target.value)} />
        </label>
        <label>
          {t('End')}
          <input value={end} onChange={(event) => setEnd(event.target.value)} />
        </label>
        <button type="button" onClick={() => void applyRange()}>{t('Apply range')}</button>
      </section>
      <section aria-labelledby="qn-analytics-screens">
        <h2 id="qn-analytics-screens">{t(SCREEN_LABELS[section])}</h2>
        <div>
          {ANALYTICS_SCREENS.map((id) => (
            <button key={id} type="button" aria-pressed={section === id} onClick={() => setSection(id)}>
              {t(SCREEN_LABELS[id])}
            </button>
          ))}
        </div>
        {section === 'rank_tracker' ? <RankScreen restUrl={restUrl} nonce={nonce} rankTracker={rankTracker} /> : null}
        {section === 'index_status' ? <AvailabilityBlock title="Index status" block={blockOf(report, 'index_status')} /> : null}
        {section !== 'rank_tracker' && section !== 'index_status' ? (
          <>
            <KpiList cards={cards} />
            <Movement title="Winning keywords" rows={winningKeywords} empty="Winning keywords appear after both periods have stored rows. A missing period is not zero." />
            <Movement title="Losing keywords" rows={losingKeywords} empty="Losing keywords appear after both periods have stored rows. A missing period is not zero." />
            <Movement title="Winning posts" rows={winningPosts?.map((row) => `${row.label}: ${row.detail}`) ?? null} empty="Winning posts appear after both periods have stored rows. A missing period is not zero." />
            <Movement title="Losing posts" rows={losingPosts?.map((row) => `${row.label}: ${row.detail}`) ?? null} empty="Losing posts appear after both periods have stored rows. A missing period is not zero." />
            {section === 'overview' || section === 'seo_performance' ? <AvailabilityBlock title="Trends" block={blockOf(report, 'trends')} /> : null}
            <ExperienceBlock report={report} />
            {section === 'commerce' ? <p>{t(textOf(report, 'commerce', 'note') || 'Commerce metrics stay empty until a provider supplies them.')}</p> : null}
            {section === 'ai' ? <p>{t(textOf(report, 'ai', 'note') || 'AI metrics are shown only when a provider supplies them.')}</p> : null}
          </>
        ) : null}
      </section>
      {message === '' ? null : <p role="status">{message}</p>}
    </>
  );
}

function ProviderCard({ title, state, property, onProperty, onConnect, onTest, onDisconnect, onResync }: {
  title: string;
  state: string;
  property: string;
  onProperty: (value: string) => void;
  onConnect: () => void;
  onTest: () => void;
  onDisconnect: () => void;
  onResync: () => void;
}) {
  return (
    <fieldset>
      <legend>{t(title)}</legend>
      <p>
        <span className="qn-badge" data-state={state === 'Connected' ? 'success' : 'not-configured'}>{t(state)}</span>
      </p>
      <label>
        {t('Property')}
        <input value={property} onChange={(event) => onProperty(event.target.value)} />
      </label>
      <button type="button" onClick={onConnect}>{t('Connect')}</button>
      <button type="button" onClick={onTest}>{t('Test connection')}</button>
      <button type="button" onClick={onDisconnect}>{t('Disconnect')}</button>
      <button type="button" onClick={onResync}>{t('Resync')}</button>
    </fieldset>
  );
}

function KpiList({ cards }: { cards: KpiCard[] }) {
  if (cards.length === 0) {
    return <p>{t('No stored metrics. Connect a provider before expecting clicks, impressions, sessions, or revenue.')}</p>;
  }
  return (
    <ul>
      {cards.map((card) => (
        <li key={card.id}>
          <p>{t(card.label)}</p>
          <span className="qn-badge" data-state={card.state === 'Measured' ? 'success' : 'not-configured'}>{t(card.text)}</span>
        </li>
      ))}
    </ul>
  );
}

function Movement({ title, rows, empty }: { title: string; rows: string[] | null; empty: string }) {
  return (
    <div>
      <h3>{t(title)}</h3>
      {rows === null || rows.length === 0 ? <p>{t(empty)}</p> : (
        <table>
          <thead>
            <tr>
              <th>{t(title)}</th>
            </tr>
          </thead>
          <tbody>
            {rows.map((row) => (
              <tr key={row}>
                <td>{row}</td>
              </tr>
            ))}
          </tbody>
        </table>
      )}
    </div>
  );
}

function AvailabilityBlock({ title, block }: { title: string; block: unknown }) {
  const state = availabilityState(block);
  const record = isRecord(block) ? block : {};
  const rows = Array.isArray(record.rows) ? record.rows.filter(isRecord) : [];
  return (
    <div>
      <h3>{t(title)}</h3>
      <p><span className="qn-badge" data-state="info">{t(state)}</span></p>
      <p>{t(typeof record.note === 'string' ? record.note : 'Not available')}</p>
      {rows.length === 0 ? null : (
        <table>
          <thead>
            <tr>
              <th>{t('Evidence')}</th>
            </tr>
          </thead>
          <tbody>
            {rows.map((row, index) => (
              <tr key={`${title}-${index}`}>
                <td>{typeof row.url === 'string' ? row.url : t('Supplied')}</td>
              </tr>
            ))}
          </tbody>
        </table>
      )}
    </div>
  );
}

function ExperienceBlock({ report }: { report: unknown }) {
  const experience = isRecord(report) && isRecord(report.experience) ? report.experience : {};
  return (
    <div>
      <h3>{t('Page experience')}</h3>
      <ul>
        <li>{t('LCP')} <span className="qn-badge" data-state="info">{timingText(experience.lcp)}</span></li>
        <li>{t('INP')} <span className="qn-badge" data-state="info">{timingText(experience.inp)}</span></li>
        <li>{t('CLS')} <span className="qn-badge" data-state="info">{timingText(experience.cls)}</span></li>
        <li>{t('TTFB')} <span className="qn-badge" data-state="info">{timingText(experience.ttfb)}</span></li>
      </ul>
      <p>{t(typeof experience.note === 'string' ? experience.note : 'Page experience timings stay empty when they were not measured.')}</p>
    </div>
  );
}

function propertyOf(report: unknown, channel: string): string {
  const card = cardOf(report, channel);
  return isRecord(card) && typeof card.property === 'string' ? card.property : '';
}

function cardOf(report: unknown, channel: string): unknown {
  const record = isRecord(report) ? report : {};
  return record[channel];
}

function blockOf(report: unknown, key: string): unknown {
  const record = isRecord(report) ? report : {};
  return record[key];
}

function textOf(report: unknown, group: string, key: string): string {
  const record = isRecord(report) ? report : {};
  const nested = isRecord(record[group]) ? record[group] : {};
  return typeof nested[key] === 'string' ? nested[key] : '';
}

function replaceCard(report: unknown, channel: string, card: Record<string, unknown>): unknown {
  const record = isRecord(report) ? { ...report } : {};
  record[channel] = card;
  return record;
}

function isRecord(input: unknown): input is Record<string, unknown> {
  return typeof input === 'object' && input !== null;
}
