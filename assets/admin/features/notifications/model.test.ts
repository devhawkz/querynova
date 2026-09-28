import { describe, expect, it } from 'vitest';
import { notificationFeed } from './model';

describe('notification center', () => {
  it('keeps notices inside QueryNova and does not hide security notices', () => {
    const feed = notificationFeed({
      hides_security: true,
      items: [
        { id: 'build', level: 'warning', message: 'Staging build is running on a production WordPress environment.' },
        { id: 'blank', level: 'info', message: '  ' },
      ],
    });

    expect(feed.hidesSecurity).toBe(false);
    expect(feed.items).toEqual([
      {
        id: 'build',
        level: 'warning',
        message: 'Staging build is running on a production WordPress environment.',
      },
    ]);
    expect(feed.note).toContain('WordPress security notices stay visible');
  });
});
