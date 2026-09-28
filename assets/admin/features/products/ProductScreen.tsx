import { useMemo, useState } from 'react';
import { QueryNovaApi } from '../../core/api/client';
import { t } from '../../i18n';
import { WorkspaceFilters } from '../commerce/WorkspaceFilters';
import {
  emptyIdentifiers,
  presentWorkspace,
  readWorkspace,
  urlBasePlan,
  IDENTIFIER_KEYS,
  type IdentifierKey,
} from '../commerce/workspace';
import { SparklineList } from '../charts/Sparkline';
import { PRODUCT_TABS, PRODUCT_TAB_TITLES, normalizeProductScreen, type ProductTab } from './model';

interface Props {
  product: unknown;
  restUrl?: string;
  nonce?: string;
  sparklines?: unknown;
}

const IDENTIFIER_LABELS: Record<IdentifierKey, string> = {
  gtin: 'GTIN',
  gtin8: 'GTIN-8',
  gtin12: 'GTIN-12',
  gtin13: 'GTIN-13',
  gtin14: 'GTIN-14',
  ean: 'EAN',
  upc: 'UPC',
  mpn: 'MPN',
  isbn: 'ISBN',
};

export function ProductScreen({ product, restUrl = '', nonce = '', sparklines }: Props) {
  const screen = normalizeProductScreen(product);
  const stored = readWorkspace(recordField(product, 'workspace'), 'product');
  const api = useMemo(() => new QueryNovaApi({ restUrl, nonce, version: '', environment: '' }), [restUrl, nonce]);
  const ready = restUrl !== '' && nonce !== '';
  const [tab, setTab] = useState<ProductTab>('overview');
  const [search, setSearch] = useState('');
  const [issue, setIssue] = useState('');
  const [opportunity, setOpportunity] = useState('');
  const [productId, setProductId] = useState('');
  const [identifiers, setIdentifiers] = useState(emptyIdentifiers());
  const [variationSku, setVariationSku] = useState('');
  const [variationGtin, setVariationGtin] = useState('');
  const [variationMpn, setVariationMpn] = useState('');
  const [variationIsbn, setVariationIsbn] = useState('');
  const [identifierConfirm, setIdentifierConfirm] = useState(false);
  const [productBase, setProductBase] = useState('');
  const [categoryBase, setCategoryBase] = useState('');
  const [urlConfirm, setUrlConfirm] = useState(false);
  const [message, setMessage] = useState('');
  const workspace = presentWorkspace(stored.entities, search, issue, opportunity, 'product');
  const items = screen.tabs[tab];

  async function storeIdentifiers() {
    if (!identifierConfirm) {
      setMessage(t('Identifiers are not written until you confirm one product.'));
      return;
    }
    const variations = variationSku.trim() === '' && variationGtin.trim() === '' && variationMpn.trim() === '' && variationIsbn.trim() === ''
      ? []
      : [{ id: 0, sku: variationSku, gtin: variationGtin, mpn: variationMpn, isbn: variationIsbn }];
    if (!ready) {
      setMessage(t('This session cannot store identifiers. The product URL was not changed.'));
      return;
    }
    try {
      const body = await api.post<Record<string, unknown>>('/commerce/identifiers', {
        product_id: Number(productId) || 0,
        ...identifiers,
        variations,
        confirmed: true,
      });
      setMessage(typeof body.note === 'string' ? body.note : t('Identifiers were stored for this product. The product URL was not changed.'));
    } catch {
      setMessage(t('Identifiers were not written. The product URL was not changed.'));
    }
  }

  async function storeUrlBase() {
    const plan = urlBasePlan(productBase, categoryBase, urlConfirm);
    if (!urlConfirm || !ready) {
      setMessage(t(plan.note));
      return;
    }
    try {
      const body = await api.post<Record<string, unknown>>('/commerce/url-base', {
        product_base: productBase,
        category_base: categoryBase,
        confirmed: true,
      });
      setMessage(typeof body.note === 'string' ? body.note : t(plan.note));
    } catch {
      setMessage(t('The request was not stored. Live product and category URLs were not changed.'));
    }
  }

  return (
    <section aria-labelledby="qn-product">
      <h1 id="qn-product">{screen.title ?? t('Product')}</h1>
      <WorkspaceFilters
        kind="product"
        lead={workspace.lead}
        recent={workspace.recent}
        search={search}
        issue={issue}
        opportunity={opportunity}
        onSearch={setSearch}
        onIssue={setIssue}
        onOpportunity={setOpportunity}
      />
      <h2>{t('Identifiers')}</h2>
      <p>{t('Identifiers are not written until you confirm one product.')}</p>
      <label>
        {t('Product ID')}
        <input value={productId} onChange={(event) => setProductId(event.target.value)} />
      </label>
      {IDENTIFIER_KEYS.map((key) => (
        <label key={key}>
          {t(IDENTIFIER_LABELS[key])}
          <input value={identifiers[key]} onChange={(event) => setIdentifiers({ ...identifiers, [key]: event.target.value })} />
        </label>
      ))}
      <label>
        {t('Variation SKU')}
        <input value={variationSku} onChange={(event) => setVariationSku(event.target.value)} />
      </label>
      <label>
        {t('Variation GTIN')}
        <input value={variationGtin} onChange={(event) => setVariationGtin(event.target.value)} />
      </label>
      <label>
        {t('Variation MPN')}
        <input value={variationMpn} onChange={(event) => setVariationMpn(event.target.value)} />
      </label>
      <label>
        {t('Variation ISBN')}
        <input value={variationIsbn} onChange={(event) => setVariationIsbn(event.target.value)} />
      </label>
      <label>
        <input type="checkbox" checked={identifierConfirm} onChange={(event) => setIdentifierConfirm(event.target.checked)} />
        {t('Confirm this product')}
      </label>
      <button type="button" onClick={() => void storeIdentifiers()}>{t('Store identifiers')}</button>

      <h2>{t('URL bases')}</h2>
      <p>{t('Product and category URL bases stay unchanged until you confirm.')}</p>
      <label>
        {t('Product base')}
        <input value={productBase} onChange={(event) => setProductBase(event.target.value)} />
      </label>
      <label>
        {t('Category base')}
        <input value={categoryBase} onChange={(event) => setCategoryBase(event.target.value)} />
      </label>
      <label>
        <input type="checkbox" checked={urlConfirm} onChange={(event) => setUrlConfirm(event.target.checked)} />
        {t('Confirm URL base request')}
      </label>
      <button type="button" onClick={() => void storeUrlBase()}>{t('Store URL base request')}</button>
      {message !== '' ? <p role="status">{message}</p> : null}

      <nav aria-label={t('Product')}>
        {PRODUCT_TABS.map((id) => (
          <button key={id} type="button" aria-current={tab === id ? 'page' : undefined} onClick={() => setTab(id)}>
            {t(PRODUCT_TAB_TITLES[id])}
          </button>
        ))}
      </nav>
      <section aria-labelledby={`qn-product-${tab}`}>
        <h2 id={`qn-product-${tab}`}>{t(PRODUCT_TAB_TITLES[tab])}</h2>
        {tab === 'revenue' ? <SparklineList source={sparklines} ids={['revenue-measured', 'revenue-attributed', 'revenue-estimated']} /> : null}
        {items.length === 0 ? (
          <p>{t('Nothing recorded.')}</p>
        ) : (
          <ul>
            {items.map((item) => (
              <li key={item.id}>
                <h3>{item.title}</h3>
                {item.summary !== '' ? <p>{item.summary}</p> : null}
              </li>
            ))}
          </ul>
        )}
      </section>
    </section>
  );
}

function recordField(input: unknown, key: string): unknown {
  if (typeof input !== 'object' || input === null) {
    return null;
  }
  return (input as Record<string, unknown>)[key];
}
