import { describe, expect, it } from 'vitest';
import { AI_SECTIONS, MCP_READS, crawlerPlan, emptyObservation, llmsPlan, mcpWrite, providerStatus, trackPrompt } from './model';

describe('AI visibility workspace', () => {
  it('keeps a missing provider not connected and does not invent citations', () => {
    const provider = providerStatus('');
    const citations = emptyObservation();

    expect(provider.status).toBe('not_connected');
    expect(provider.called).toBe(false);
    expect(provider.note).toContain('did not call a model');
    expect(citations.value).toBeNull();
    expect(citations.status).toBe('UNAVAILABLE');
    expect(AI_SECTIONS).toContain('citations');
    expect(AI_SECTIONS).toContain('revenue');
    expect(AI_SECTIONS).toContain('crawlers');
  });

  it('stores prompt fields without connecting a provider', () => {
    const stored = trackPrompt({
      prompt: 'best welder',
      locale: 'en_US',
      country: 'RS',
      language: 'en',
      provider: 'openai',
      frequency: 'weekly',
      tags: ['commerce'],
    });

    expect(stored.stored).toBe(true);
    expect(stored.called).toBe(false);
    expect(stored.connected).toBe(false);
    expect(stored.note).toContain('does not connect');
  });

  it('keeps crawler changes and llms.txt off until confirmed', () => {
    const held = crawlerPlan('GPTBot', 'block', false);
    const stored = crawlerPlan('GPTBot', 'block', true);
    const llms = llmsPlan(false, 'Guide');

    expect(held.stored).toBe(false);
    expect(held.robots_changed).toBe(false);
    expect(stored.robots_changed).toBe(false);
    expect(stored.note).toContain('robots.txt was not changed');
    expect(llms.enabled).toBe(false);
    expect(llms.experimental).toBe(true);
    expect(llms.ranking_requirement).toBe(false);
    expect(llms.note).toContain('not a ranking requirement');
  });

  it('refuses a write without permission and lists read tools', () => {
    const refused = mcpWrite(false);
    const allowed = mcpWrite(true);

    expect(refused.accepted).toBe(false);
    expect(refused.changed).toBe(false);
    expect(allowed.accepted).toBe(true);
    expect(allowed.changed).toBe(false);
    expect(allowed.published).toBe(false);
    expect(MCP_READS).toContain('ai_visibility');
    expect(MCP_READS).toContain('not_found');
    expect(MCP_READS).not.toContain('secrets');
  });
});
