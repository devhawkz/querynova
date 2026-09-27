import { useState } from 'react';
import { CATEGORY_TABS, CATEGORY_TAB_TITLES, normalizeCategoryScreen, type CategoryTab } from './model';

interface Props {
  category: unknown;
}

export function CategoryScreen({ category }: Props) {
  const screen = normalizeCategoryScreen(category);
  const [tab, setTab] = useState<CategoryTab>('overview');
  const items = screen.tabs[tab];
  return (
    <section aria-labelledby="qn-category">
      <h1 id="qn-category">{screen.title ?? 'Category'}</h1>
      <p>{screen.title === null ? 'No stored category.' : 'Latest stored category.'}</p>
      <nav aria-label="Category">
        {CATEGORY_TABS.map((id) => (
          <button key={id} type="button" aria-current={tab === id ? 'page' : undefined} onClick={() => setTab(id)}>
            {CATEGORY_TAB_TITLES[id]}
          </button>
        ))}
      </nav>
      <section aria-labelledby={`qn-category-${tab}`}>
        <h2 id={`qn-category-${tab}`}>{CATEGORY_TAB_TITLES[tab]}</h2>
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
