import { useState } from 'react';
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
      <h1 id="qn-product">{screen.title ?? 'Product'}</h1>
      <p>{screen.title === null ? 'No stored product.' : 'Latest stored product.'}</p>
      <nav aria-label="Product">
        {PRODUCT_TABS.map((id) => (
          <button key={id} type="button" aria-current={tab === id ? 'page' : undefined} onClick={() => setTab(id)}>
            {PRODUCT_TAB_TITLES[id]}
          </button>
        ))}
      </nav>
      <section aria-labelledby={`qn-product-${tab}`}>
        <h2 id={`qn-product-${tab}`}>{PRODUCT_TAB_TITLES[tab]}</h2>
        {items.length === 0 ? (
          <p>Nothing recorded.</p>
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
