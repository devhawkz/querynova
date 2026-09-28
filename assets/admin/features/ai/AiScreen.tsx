import { useMemo, useState } from 'react';
import { QueryNovaApi } from '../../core/api/client';
import { t } from '../../i18n';
import {
  AI_SECTIONS,
  AI_SECTION_TITLES,
  MCP_READS,
  crawlerPlan,
  llmsPlan,
  mcpWrite,
  providerStatus,
  trackPrompt,
  type AiSection,
} from './model';

interface Props {
  restUrl: string;
  nonce: string;
  aiWorkspace: unknown;
}

export function AiScreen({ restUrl, nonce, aiWorkspace }: Props) {
  const api = useMemo(() => new QueryNovaApi({ restUrl, nonce, version: '', environment: '' }), [restUrl, nonce]);
  const ready = restUrl !== '' && nonce !== '';
  const workspace = isRecord(aiWorkspace) ? aiWorkspace : {};
  const providerId = isRecord(workspace.provider) && typeof workspace.provider.provider === 'string' ? workspace.provider.provider : '';
  const connection = providerStatus(providerId);
  const [section, setSection] = useState<AiSection>('overview');
  const [prompt, setPrompt] = useState('');
  const [locale, setLocale] = useState('');
  const [country, setCountry] = useState('');
  const [language, setLanguage] = useState('');
  const [promptProvider, setPromptProvider] = useState('');
  const [frequency, setFrequency] = useState('');
  const [tags, setTags] = useState('');
  const [agent, setAgent] = useState('');
  const [decision, setDecision] = useState('block');
  const [crawlerConfirm, setCrawlerConfirm] = useState(false);
  const [llmsEnabled, setLlmsEnabled] = useState(false);
  const [llmsBody, setLlmsBody] = useState('');
  const [permitted, setPermitted] = useState(false);
  const [message, setMessage] = useState('');
  const metric = isRecord(workspace[section]) ? workspace[section] : null;
  const metricValue = metric && 'value' in metric ? metric.value : null;

  async function savePrompt() {
    const local = trackPrompt({
      prompt,
      locale,
      country,
      language,
      provider: promptProvider,
      frequency,
      tags: tags.split(',').map((tag) => tag.trim()).filter((tag) => tag !== ''),
    });
    if (!ready) {
      setMessage(t(local.note));
      return;
    }
    try {
      const body = await api.post<Record<string, unknown>>('/ai/prompts/track', {
        prompt,
        locale,
        country,
        language,
        provider: promptProvider,
        frequency,
        tags: tags.split(',').map((tag) => tag.trim()).filter((tag) => tag !== ''),
      });
      setMessage(typeof body.note === 'string' ? body.note : t(local.note));
    } catch {
      setMessage(t('The prompt was not stored. QueryNova did not call a model.'));
    }
  }

  async function saveCrawler() {
    const local = crawlerPlan(agent, decision, crawlerConfirm);
    if (!crawlerConfirm || !ready) {
      setMessage(t(local.note));
      return;
    }
    try {
      const body = await api.post<Record<string, unknown>>('/ai/crawlers', { agent, decision, confirmed: true });
      setMessage(typeof body.note === 'string' ? body.note : t(local.note));
    } catch {
      setMessage(t('The preference was not stored. robots.txt was not changed.'));
    }
  }

  async function saveLlms() {
    const local = llmsPlan(llmsEnabled, llmsBody);
    if (!ready) {
      setMessage(t(local.note));
      return;
    }
    try {
      const body = await api.post<Record<string, unknown>>('/ai/llms', { enabled: llmsEnabled, body: llmsBody });
      setMessage(typeof body.note === 'string' ? body.note : t(local.note));
    } catch {
      setMessage(t('llms.txt was not changed. It is not a ranking requirement.'));
    }
  }

  function reviewWrite() {
    setMessage(t(mcpWrite(permitted).note));
  }

  return (
    <section aria-labelledby="qn-ai">
      <h1 id="qn-ai">{t('AI Visibility')}</h1>
      <p>{t('This is the site workspace. It is not a cloud dashboard.')}</p>
      <p>{connection.status === 'not_connected' ? t('Not connected') : t('Connected')}</p>
      <p>{t(connection.note)}</p>
      <nav aria-label={t('AI Visibility')}>
        {AI_SECTIONS.map((id) => (
          <button key={id} type="button" aria-current={section === id ? 'page' : undefined} onClick={() => setSection(id)}>
            {t(AI_SECTION_TITLES[id])}
          </button>
        ))}
      </nav>
      <section aria-labelledby={`qn-ai-${section}`}>
        <h2 id={`qn-ai-${section}`}>{t(AI_SECTION_TITLES[section])}</h2>
        <p>{metric && typeof metric.note === 'string' ? metric.note : t('Unavailable')}</p>
        {metricValue === null || metricValue === undefined ? <p>{t('Unavailable')}</p> : <p>{String(metricValue)}</p>}
      </section>

      <h2>{t('Prompt tracking')}</h2>
      <label>
        {t('Prompt')}
        <input value={prompt} onChange={(event) => setPrompt(event.target.value)} />
      </label>
      <label>
        {t('Locale')}
        <input value={locale} onChange={(event) => setLocale(event.target.value)} />
      </label>
      <label>
        {t('Country')}
        <input value={country} onChange={(event) => setCountry(event.target.value)} />
      </label>
      <label>
        {t('Language')}
        <input value={language} onChange={(event) => setLanguage(event.target.value)} />
      </label>
      <label>
        {t('Provider name')}
        <input value={promptProvider} onChange={(event) => setPromptProvider(event.target.value)} />
      </label>
      <label>
        {t('Frequency')}
        <input value={frequency} onChange={(event) => setFrequency(event.target.value)} />
      </label>
      <label>
        {t('Tags')}
        <input value={tags} onChange={(event) => setTags(event.target.value)} />
      </label>
      <button type="button" onClick={() => void savePrompt()}>{t('Store prompt')}</button>

      <h2>{t('Crawler access')}</h2>
      <p>{t('Crawler access stays unchanged until you confirm. robots.txt is not rewritten.')}</p>
      <label>
        {t('Crawler')}
        <input value={agent} onChange={(event) => setAgent(event.target.value)} />
      </label>
      <label>
        {t('Access')}
        <select value={decision} onChange={(event) => setDecision(event.target.value)}>
          <option value="allow">{t('Allow')}</option>
          <option value="block">{t('Block')}</option>
        </select>
      </label>
      <label>
        <input type="checkbox" checked={crawlerConfirm} onChange={(event) => setCrawlerConfirm(event.target.checked)} />
        {t('Confirm crawler preference')}
      </label>
      <button type="button" onClick={() => void saveCrawler()}>{t('Store crawler preference')}</button>

      <h2>{t('llms.txt')}</h2>
      <p>{t(llmsPlan(false, '').note)}</p>
      <label>
        <input type="checkbox" checked={llmsEnabled} onChange={(event) => setLlmsEnabled(event.target.checked)} />
        {t('Enable llms.txt')}
      </label>
      <label>
        {t('llms.txt body')}
        <textarea value={llmsBody} onChange={(event) => setLlmsBody(event.target.value)} />
      </label>
      <button type="button" onClick={() => void saveLlms()}>{t('Store llms.txt')}</button>

      <h2>{t('Assistant tools')}</h2>
      <ul>
        {MCP_READS.map((tool) => (
          <li key={tool}>{tool}</li>
        ))}
      </ul>
      <label>
        <input type="checkbox" checked={permitted} onChange={(event) => setPermitted(event.target.checked)} />
        {t('Grant write permission')}
      </label>
      <button type="button" onClick={reviewWrite}>{t('Review write permission')}</button>
      {message !== '' ? <p role="status">{message}</p> : null}
    </section>
  );
}

function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === 'object' && value !== null;
}
