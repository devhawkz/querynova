import { describe, expect, it } from 'vitest';
import { channelFlags, htaccessVisibility, indexNowPlan, linkPresentation, linkSettingsPlan, podcastPlan, ROBOTS_NOTE, SITEMAP_CHANNELS } from './model';

describe('site tools', () => {
  it('keeps suggestions on Suggest Only', () => {
    expect(linkPresentation({ mode: 'Suggest Only', inserted: false }).mode).toBe('Suggest Only');
    expect(linkPresentation({ inserted: false }).inserted).toBe(false);
    expect(linkPresentation(undefined).mode).toBe('Suggest Only');
  });

  it('hides htaccess on Nginx and outside Advanced', () => {
    expect(htaccessVisibility('nginx/1.24.0', true)).toEqual({ visible: false, note: 'Hidden on Nginx.' });
    expect(htaccessVisibility('Apache', false).visible).toBe(false);
    expect(htaccessVisibility('Apache', true).visible).toBe(true);
  });

  it('states that robots.txt stays in QueryNova until a file write is allowed', () => {
    expect(ROBOTS_NOTE).toContain('physical file is not changed unless you allow it');
  });

  it('skips noindex URLs and does not send IndexNow', () => {
    const plan = indexNowPlan(['https://example.test/a', 'https://example.test/b'], ['https://example.test/b']);
    expect(plan.queued).toEqual(['https://example.test/a']);
    expect(plan.skipped).toEqual(['https://example.test/b']);
    expect(plan.requested).toBe(false);
  });

  it('keeps link defaults off and publishes nothing for podcast', () => {
    const held = linkSettingsPlan({ new_tab: true, auto_insert: true }, false);
    const stored = linkSettingsPlan({ new_tab: true }, true);
    expect(held.stored).toBe(false);
    expect(held.inserted).toBe(false);
    expect(held.applied).toBe(false);
    expect(stored.inserted).toBe(false);
    expect(stored.note).toContain('Nothing is inserted');
    expect(podcastPlan(true, false).stored).toBe(false);
    expect(podcastPlan(true, true).published).toBe(false);
    expect(podcastPlan(false, true).enabled).toBe(false);
  });

  it('keeps news and KML off until they are configured', () => {
    expect(SITEMAP_CHANNELS.map(([key]) => key)).toContain('author');
    const flags = channelFlags({ news: true, kml: true, post: true }, false, false);
    expect(flags.news).toBe(false);
    expect(flags.kml).toBe(false);
    expect(flags.post).toBe(true);
    expect(channelFlags({ news: true, kml: true }, true, true).kml).toBe(true);
  });
});
