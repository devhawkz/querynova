export function titleSavePlan(confirmed: boolean): { stored: boolean; rewritten: false; note: string } {
  if (!confirmed) {
    return {
      stored: false,
      rewritten: false,
      note: 'Title templates stay unchanged until you confirm. Custom titles on existing documents were not rewritten.',
    };
  }
  return {
    stored: true,
    rewritten: false,
    note: 'Title templates are stored. Custom titles on existing documents were not rewritten.',
  };
}

export function roleSavePlan(name: string, confirmed: boolean): { stored: boolean; applied: false; note: string } {
  if (!confirmed || name.trim() === '') {
    return {
      stored: false,
      applied: false,
      note: 'A custom role is not stored until you confirm a name. WordPress roles were not changed.',
    };
  }
  return {
    stored: true,
    applied: false,
    note: 'The custom role is stored. WordPress roles were not changed.',
  };
}
