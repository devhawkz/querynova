import { useMemo, useState } from 'react';
import { QueryNovaApi } from '../../core/api/client';
import { t } from '../../i18n';
import {
  AUTOMATION_RULES,
  DRAFT_KINDS,
  DRAFT_LABELS,
  automationPlan,
  contentLead,
  generateDraft,
  healthReport,
  modelConnection,
  storeDraft,
  type DraftKind,
} from './model';

interface Props {
  restUrl: string;
  nonce: string;
  contentWorkspace: unknown;
}

export function ContentScreen({ restUrl, nonce, contentWorkspace }: Props) {
  const api = useMemo(() => new QueryNovaApi({ restUrl, nonce, version: '', environment: '' }), [restUrl, nonce]);
  const ready = restUrl !== '' && nonce !== '';
  const stored = isRecord(contentWorkspace) ? contentWorkspace : {};
  const initialModel = isRecord(stored.model) && typeof stored.model.model === 'string' ? stored.model.model : '';
  const [modelId, setModelId] = useState(initialModel);
  const [query, setQuery] = useState('');
  const [keyword, setKeyword] = useState('');
  const [topics, setTopics] = useState('');
  const [topResults, setTopResults] = useState('');
  const [html, setHtml] = useState('');
  const [entityName, setEntityName] = useState('');
  const [entityType, setEntityType] = useState('');
  const [brief, setBrief] = useState<Record<string, unknown> | null>(null);
  const [kind, setKind] = useState<DraftKind>('outline');
  const [draftText, setDraftText] = useState('');
  const [rule, setRule] = useState<(typeof AUTOMATION_RULES)[number]>('internal_links');
  const [automate, setAutomate] = useState(false);
  const [healthUrl, setHealthUrl] = useState('');
  const [currentClicks, setCurrentClicks] = useState('');
  const [previousClicks, setPreviousClicks] = useState('');
  const [healthKeyword, setHealthKeyword] = useState('');
  const [healthOther, setHealthOther] = useState('');
  const [message, setMessage] = useState('');
  const [rules, setRules] = useState(automationPlan('internal_links', false));
  const connection = modelConnection(modelId);
  const lead = contentLead(brief !== null);

  async function reviewDocument() {
    if (!ready) {
      setMessage(t('This session cannot review a document. QueryNova does not fetch the URL.'));
      return;
    }
    const topicRows = splitList(topics);
    const resultRows = splitList(topResults);
    try {
      const body = await api.post<Record<string, unknown>>('/content', {
        html,
        query,
        keyword,
        topics: topicRows,
        top_three: resultRows,
        top_ten: resultRows,
        entities: entityName.trim() === '' || entityType.trim() === '' ? [] : [{ name: entityName, type: entityType }],
      });
      setBrief(body);
      setMessage(t('Observations from the supplied document. This is not a content score.'));
    } catch {
      setMessage(t('The document could not be reviewed. QueryNova does not fetch the URL.'));
    }
  }

  async function saveModel() {
    if (!ready) {
      setMessage(t(connection.note));
      return;
    }
    try {
      const body = await api.post<Record<string, unknown>>('/content/model', { model_id: modelId });
      setMessage(typeof body.note === 'string' ? body.note : t(connection.note));
    } catch {
      setMessage(t('The model id was not stored. QueryNova did not call a model.'));
    }
  }

  async function saveDraft() {
    const local = storeDraft(kind, draftText);
    if (!ready) {
      setMessage(t(local.note));
      return;
    }
    try {
      const body = await api.post<Record<string, unknown>>('/content/drafts', { action: 'store', kind, text: draftText });
      setMessage(typeof body.note === 'string' ? body.note : t(local.note));
    } catch {
      setMessage(t('The draft was not stored. The live page was not changed.'));
    }
  }

  async function requestDraft() {
    const local = generateDraft(modelId, kind, draftText);
    if (!ready || local.status === 'not_connected') {
      setMessage(t(local.note));
      return;
    }
    try {
      const body = await api.post<Record<string, unknown>>('/content/drafts', { action: 'generate', kind, text: draftText });
      setMessage(typeof body.note === 'string' ? body.note : t(local.note));
    } catch {
      setMessage(t('This draft was not created. QueryNova did not call a model.'));
    }
  }

  async function reviewHealth(kind: 'decay' | 'cannibalization') {
    const local = healthReport(kind);
    const metric = (value: string): number | null => {
      const trimmed = value.trim();
      if (trimmed === '' || Number.isNaN(Number(trimmed))) {
        return null;
      }
      return Number(trimmed);
    };
    const rows = kind === 'cannibalization'
      ? [
          { keyword: healthKeyword, url: healthUrl },
          { keyword: healthKeyword, url: healthOther },
        ]
      : [
          {
            url: healthUrl,
            current: { clicks: metric(currentClicks) },
            previous: { clicks: metric(previousClicks) },
          },
        ];
    if (!ready) {
      setMessage(t(local.note));
      return;
    }
    try {
      const body = await api.post<Record<string, unknown>>('/content/health', { kind, rows });
      setMessage(typeof body.note === 'string' ? body.note : t(local.note));
    } catch {
      setMessage(t(local.note));
    }
  }

  async function saveRule() {
    const local = automationPlan(rule, automate);
    setRules(local);
    if (!ready) {
      setMessage(t(local.note));
      return;
    }
    try {
      const body = await api.post<Record<string, unknown>>('/content/automation', { rule, enabled: automate });
      setMessage(typeof body.note === 'string' ? body.note : t(local.note));
    } catch {
      setMessage(t('The rule was not stored. Nothing is inserted.'));
    }
  }

  const gap = isRecord(brief?.gap) ? brief.gap : null;
  const gapTopics = Array.isArray(gap?.topics) ? gap.topics.filter((topic): topic is string => typeof topic === 'string') : null;

  return (
    <section aria-labelledby="qn-content">
      <h1 id="qn-content">{t('Content')}</h1>
      <p>{t(lead)}</p>
      <p>{connection.status === 'not_connected' ? t('Not connected') : t('Connected')}</p>
      <p>{t(connection.note)}</p>
      <label>
        {t('Model id')}
        <input value={modelId} onChange={(event) => setModelId(event.target.value)} />
      </label>
      <button type="button" onClick={() => void saveModel()}>{t('Save model id')}</button>

      <h2>{t('Document')}</h2>
      <label>
        {t('Query')}
        <input value={query} onChange={(event) => setQuery(event.target.value)} />
      </label>
      <label>
        {t('Keyword')}
        <input value={keyword} onChange={(event) => setKeyword(event.target.value)} />
      </label>
      <label>
        {t('Topics')}
        <input value={topics} onChange={(event) => setTopics(event.target.value)} />
      </label>
      <label>
        {t('Top results')}
        <input value={topResults} onChange={(event) => setTopResults(event.target.value)} />
      </label>
      <label>
        {t('Entity name')}
        <input value={entityName} onChange={(event) => setEntityName(event.target.value)} />
      </label>
      <label>
        {t('Entity type')}
        <input value={entityType} onChange={(event) => setEntityType(event.target.value)} />
      </label>
      <label>
        {t('Document HTML')}
        <textarea value={html} onChange={(event) => setHtml(event.target.value)} />
      </label>
      <button type="button" onClick={() => void reviewDocument()}>{t('Review document')}</button>
      {brief !== null ? <Observation brief={brief} gapTopics={gapTopics} /> : null}

      <h2>{t('Drafts')}</h2>
      <p>{t('Drafts are stored only. They are not published and they do not change the live page.')}</p>
      <label>
        {t('Draft type')}
        <select value={kind} onChange={(event) => setKind(event.target.value as DraftKind)}>
          {DRAFT_KINDS.map((id) => (
            <option key={id} value={id}>{t(DRAFT_LABELS[id])}</option>
          ))}
        </select>
      </label>
      <label>
        {t('Draft text')}
        <textarea value={draftText} onChange={(event) => setDraftText(event.target.value)} />
      </label>
      <button type="button" onClick={() => void saveDraft()}>{t('Store draft')}</button>
      <button type="button" onClick={() => void requestDraft()}>{t('Generate draft')}</button>

      <h2>{t('Link and keyword rules')}</h2>
      <p>{t('Suggestions stay Suggest Only unless you turn automation on for one rule.')}</p>
      <label>
        {t('Rule')}
        <select value={rule} onChange={(event) => setRule(event.target.value as (typeof AUTOMATION_RULES)[number])}>
          <option value="internal_links">{t('Internal links')}</option>
          <option value="keyword_links">{t('Keyword links')}</option>
        </select>
      </label>
      <label>
        <input type="checkbox" checked={automate} onChange={(event) => setAutomate(event.target.checked)} />
        {t('Turn automation on for this one rule')}
      </label>
      <button type="button" onClick={() => void saveRule()}>{t('Save link rule')}</button>
      <h2>{t('Stored reports')}</h2>
      <p>{t(healthReport('decay').note)}</p>
      <label>
        {t('Page URL')}
        <input value={healthUrl} onChange={(event) => setHealthUrl(event.target.value)} />
      </label>
      <label>
        {t('Current clicks')}
        <input value={currentClicks} onChange={(event) => setCurrentClicks(event.target.value)} />
      </label>
      <label>
        {t('Previous clicks')}
        <input value={previousClicks} onChange={(event) => setPreviousClicks(event.target.value)} />
      </label>
      <button type="button" onClick={() => void reviewHealth('decay')}>{t('Review decay')}</button>
      <label>
        {t('Shared keyword')}
        <input value={healthKeyword} onChange={(event) => setHealthKeyword(event.target.value)} />
      </label>
      <label>
        {t('Second URL')}
        <input value={healthOther} onChange={(event) => setHealthOther(event.target.value)} />
      </label>
      <button type="button" onClick={() => void reviewHealth('cannibalization')}>{t('Review cannibalization')}</button>
      <p>{t('Internal links')}: {t(rules.internal_links)}</p>
      <p>{t('Keyword links')}: {t(rules.keyword_links)}</p>
      {message !== '' ? <p role="status">{message}</p> : null}
    </section>
  );
}

function Observation({ brief, gapTopics }: { brief: Record<string, unknown>; gapTopics: string[] | null }) {
  const intent = isRecord(brief.intent) ? brief.intent : null;
  const evidence = isRecord(brief.evidence) ? brief.evidence : null;
  const entities = isRecord(brief.entities) ? brief.entities : null;
  const mentions = Array.isArray(entities?.mentions) ? entities.mentions.length : null;
  const entityLabel = entities?.status === 'UNAVAILABLE' || mentions === null ? t('Unavailable') : String(mentions);
  const gapLabel = gapTopics === null ? t('Unavailable') : gapTopics.join(', ') || t('No missing important topic in the supplied results.');
  return (
    <section aria-labelledby="qn-content-observations">
      <h2 id="qn-content-observations">{t('Observations')}</h2>
      <p>{t('Intent')}: {intent && typeof intent.primary === 'string' ? intent.primary : t('Unavailable')}</p>
      <p>{t('Gap')}: {gapLabel}</p>
      <p>{t('Entities')}: {entityLabel}</p>
      <p>{evidence && typeof evidence.note === 'string' ? evidence.note : t('This is not a Google E-E-A-T score.')}</p>
    </section>
  );
}

function splitList(value: string): string[] {
  return value.split(',').map((part) => part.trim()).filter((part) => part !== '');
}

function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === 'object' && value !== null;
}
