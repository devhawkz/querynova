import { describe, expect, it } from 'vitest';
import { environmentBadge, NAV_ITEMS, navItem } from './navigation';

describe('admin navigation', () => {
  it('keeps the WordPress environment label separate from the build', () => {
    expect(environmentBadge('production')).toBe('WordPress Production');
    expect(environmentBadge('staging')).toBe('WordPress Staging');
    expect(environmentBadge('')).toBe('');
    expect(environmentBadge('production')).not.toContain('Staging build');
  });

  it('names every primary section once', () => {
    const labels = NAV_ITEMS.map((item) => item.label);
    expect(labels).toEqual([
      'Dashboard',
      'SEO',
      'Content',
      'Keywords',
      'Analytics',
      'Rank Tracking',
      'Links',
      'WooCommerce',
      'Schema',
      'Local SEO',
      'AI Visibility',
      'Reports',
      'Settings & Tools',
    ]);
    expect(new Set(labels).size).toBe(labels.length);
    expect(navItem('dashboard').hint).toContain('ranking score');
  });
});
