import { useState } from 'react';
import { t } from '../../i18n';
import { PRODUCT_TABS, PRODUCT_TAB_TITLES, normalizeProductScreen, type ProductTab } from './model';

interface Props {
  product: unknown;
}

export function ProductScreen({ product }: Props) {
  const screen = normalizeProductScreen(product);
  const [tab, setTab] = useState<ProductTab>('overview');
  const items = screen.tabs[tab];
  return (
    <section aria-labelledby="qn-product">
      <h1 id="qn-product">{screen.title ?? t('Product')}</h1>
      <p>{screen.title === null ? t('No stored product.') : t('Latest stored product.')}</p>
      <nav aria-label={t('Product')}>
        {PRODUCT_TABS.map((id) => (
          <button key={id} type="button" aria-current={tab === id ? 'page' : undefined} onClick={() => setTab(id)}>
            {t(PRODUCT_TAB_TITLES[id])}
          </button>
        ))}
      </nav>
      <section aria-labelledby={`qn-product-${tab}`}>
        <h2 id={`qn-product-${tab}`}>{t(PRODUCT_TAB_TITLES[tab])}</h2>
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
