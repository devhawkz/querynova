import { useMemo, useState } from 'react';
import { QueryNovaApi } from '../../core/api/client';
import { t } from '../../i18n';
import { historyNote, historyRows, indexState, trackedKeywords, trendState, type TrackedKeyword } from './model';

interface Props {
  restUrl: string;
  nonce: string;
  rankTracker: unknown;
}

export function RankScreen({ restUrl, nonce, rankTracker }: Props) {
  const api = useMemo(() => new QueryNovaApi({ restUrl, nonce, version: '', environment: '' }), [restUrl, nonce]);
  const [catalog, setCatalog] = useState(rankTracker);
  const [keyword, setKeyword] = useState('');
  const [group, setGroup] = useState('');
  const [location, setLocation] = useState('');
  const [language, setLanguage] = useState('');
  const [device, setDevice] = useState('desktop');
  const [country, setCountry] = useState('');
  const [bulk, setBulk] = useState('');
  const [csv, setCsv] = useState('');
  const [message, setMessage] = useState('');
  const keywords = trackedKeywords(catalog);
  const history = historyRows(catalog);

  async function addOne() {
    await send('/serp/keywords', { keyword, group, location, language, device, country });
  }

  async function addBulk() {
    const keywords = bulk.split('\n').map((line) => line.trim()).filter((line) => line !== '').map((line) => ({
      keyword: line,
      group,
      location,
      language,
      device,
      country,
    }));
    await send('/serp/keywords/bulk', { keywords });
  }

  async function importCsv() {
    await send('/serp/keywords/csv', { csv });
  }

  async function send(path: string, payload: unknown) {
    if (restUrl === '' || nonce === '') {
      setMessage(t('Keywords cannot be saved in this session.'));
      return;
    }
    try {
      const result = await api.post<unknown>(path, payload);
      const record = isRecord(result) ? result : {};
      if (Array.isArray(record.keywords)) {
        setCatalog({ ...(isRecord(catalog) ? catalog : {}), keywords: record.keywords });
      }
      setMessage(typeof record.note === 'string' ? record.note : t('The keyword is stored locally. No SERP vendor was called.'));
    } catch {
      setMessage(t('The keyword could not be stored. No SERP vendor was called.'));
    }
  }

  return (
    <section aria-labelledby="qn-rank-tracker">
      <h2 id="qn-rank-tracker">{t('Rank tracker')}</h2>
      <p>{t('No SERP vendor is selected. A missing rank is not zero.')}</p>
      <label>
        {t('Keyword')}
        <input value={keyword} onChange={(event) => setKeyword(event.target.value)} />
      </label>
      <label>
        {t('Group')}
        <input value={group} onChange={(event) => setGroup(event.target.value)} />
      </label>
      <label>
        {t('Location')}
        <input value={location} onChange={(event) => setLocation(event.target.value)} />
      </label>
      <label>
        {t('Language')}
        <input value={language} onChange={(event) => setLanguage(event.target.value)} />
      </label>
      <label>
        {t('Device')}
        <select value={device} onChange={(event) => setDevice(event.target.value)}>
          <option value="desktop">{t('Desktop')}</option>
          <option value="mobile">{t('Mobile')}</option>
          <option value="tablet">{t('Tablet')}</option>
        </select>
      </label>
      <label>
        {t('Country')}
        <input value={country} onChange={(event) => setCountry(event.target.value)} />
      </label>
      <button type="button" onClick={() => void addOne()}>{t('Add keyword')}</button>
      <label>
        {t('Bulk keywords')}
        <textarea value={bulk} onChange={(event) => setBulk(event.target.value)} />
      </label>
      <button type="button" onClick={() => void addBulk()}>{t('Add bulk')}</button>
      <label>
        {t('CSV')}
        <textarea value={csv} onChange={(event) => setCsv(event.target.value)} />
      </label>
      <button type="button" onClick={() => void importCsv()}>{t('Import CSV')}</button>
      <KeywordTable keywords={keywords} />
      <h3>{t('History')}</h3>
      <p>{t(historyNote(catalog))}</p>
      {history.length === 0 ? null : (
        <table>
          <thead>
            <tr>
              <th>{t('Position')}</th>
              <th>{t('URL')}</th>
              <th>{t('Device')}</th>
            </tr>
          </thead>
          <tbody>
            {history.map((row, index) => (
              <tr key={`${row.url}-${index}`}>
                <td>{row.position}</td>
                <td>{row.url}</td>
                <td>{row.device}</td>
              </tr>
            ))}
          </tbody>
        </table>
      )}
      <p><span className="qn-badge" data-state="info">{t('Index status')} {t(indexState(catalog))}</span></p>
      <p><span className="qn-badge" data-state="info">{t('Trends')} {t(trendState(catalog))}</span></p>
      {message === '' ? null : <p role="status">{message}</p>}
    </section>
  );
}

function KeywordTable({ keywords }: { keywords: TrackedKeyword[] }) {
  if (keywords.length === 0) {
    return <p>{t('No tracked keywords yet. Add one, add a list, or import a CSV. Nothing is sent to a SERP vendor.')}</p>;
  }
  return (
    <table>
      <thead>
        <tr>
          <th>{t('Keyword')}</th>
          <th>{t('Group')}</th>
          <th>{t('Location')}</th>
          <th>{t('Language')}</th>
          <th>{t('Device')}</th>
        </tr>
      </thead>
      <tbody>
        {keywords.map((row) => (
          <tr key={row.id}>
            <td>{row.keyword}</td>
            <td>{row.group}</td>
            <td>{row.location}</td>
            <td>{row.language}</td>
            <td>{row.device}</td>
          </tr>
        ))}
      </tbody>
    </table>
  );
}

function isRecord(input: unknown): input is Record<string, unknown> {
  return typeof input === 'object' && input !== null;
}
