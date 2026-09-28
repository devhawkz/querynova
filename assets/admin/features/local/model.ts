export function localPlan(enabled: boolean, confirmed: boolean): {
  stored: boolean;
  enabled: boolean;
  urlChanged: false;
  flushed: false;
  note: string;
} {
  if (!confirmed) {
    return {
      stored: false,
      enabled: false,
      urlChanged: false,
      flushed: false,
      note: 'Local SEO stays unchanged until you confirm. Live URLs were not changed.',
    };
  }
  return {
    stored: true,
    enabled,
    urlChanged: false,
    flushed: false,
    note: enabled
      ? 'Local SEO is enabled. Live URLs were not changed.'
      : 'Local SEO stays off. Live URLs were not changed.',
  };
}

export function locationPlan(name: string, confirmed: boolean, moduleEnabled: boolean): {
  stored: boolean;
  urlChanged: false;
  flushed: false;
  note: string;
} {
  if (!moduleEnabled || !confirmed || name.trim() === '') {
    return {
      stored: false,
      urlChanged: false,
      flushed: false,
      note: 'The location stays unstored until local SEO is enabled and you confirm. Live URLs were not changed.',
    };
  }
  return {
    stored: true,
    urlChanged: false,
    flushed: false,
    note: 'The location is stored. Live URLs were not changed.',
  };
}
