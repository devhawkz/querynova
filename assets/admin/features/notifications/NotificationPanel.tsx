import { t } from '../../i18n';
import { notificationFeed } from './model';

export function NotificationPanel({ notifications }: { notifications: unknown }) {
  const feed = notificationFeed(notifications);

  return (
    <section aria-labelledby="qn-notifications">
      <h2 id="qn-notifications">{t('Notifications')}</h2>
      <p>{t(feed.note)}</p>
      {feed.items.length === 0 ? <p>{t('No QueryNova notices.')}</p> : (
        <ul>
          {feed.items.map((item) => (
            <li key={item.id}>
              <span className="qn-badge" data-state={item.level}>{t(item.level === 'warning' ? 'Warning' : 'Info')}</span>
              <p>{item.message}</p>
            </li>
          ))}
        </ul>
      )}
    </section>
  );
}
