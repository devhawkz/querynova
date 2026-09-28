export const SETTINGS_SECTIONS = [
  { id: 'general', label: 'General' },
  { id: 'seo', label: 'SEO' },
  { id: 'titles', label: 'Titles & Meta' },
  { id: 'links', label: 'Links' },
  { id: 'breadcrumbs', label: 'Breadcrumbs' },
  { id: 'images', label: 'Images' },
  { id: 'sitemaps', label: 'Sitemaps' },
  { id: 'schema', label: 'Schema' },
  { id: 'webmaster', label: 'Webmaster Tools' },
  { id: 'woocommerce', label: 'WooCommerce' },
  { id: 'local', label: 'Local SEO' },
  { id: 'analytics', label: 'Analytics' },
  { id: 'providers', label: 'Providers' },
  { id: 'ai', label: 'AI' },
  { id: 'roles', label: 'Roles' },
  { id: 'advanced', label: 'Advanced' },
  { id: 'tools', label: 'Tools' },
] as const;

export type SettingsSectionId = (typeof SETTINGS_SECTIONS)[number]['id'];

export interface FeatureCard {
  id: string;
  name: string;
  module: string;
  state: string;
  enabled: boolean;
  experimental: boolean;
}

export interface ModuleCard {
  name: string;
  version: string;
  optional: boolean;
  dependencies: string[];
  failed: boolean;
  registered: boolean;
  features: FeatureCard[];
}

export interface RoleGrant {
  role: string;
  capabilities: string[];
}

export interface SettingsModel {
  wordpressEnvironment: string;
  querynovaBuild: string;
  modules: ModuleCard[];
  roles: RoleGrant[];
}

export interface FeatureStatus {
  label: string;
  state: string;
}

export function normalizeSettings(input: unknown): SettingsModel {
  const record = isRecord(input) ? input : {};
  return {
    wordpressEnvironment: text(record.wordpress_environment),
    querynovaBuild: text(record.querynova_build),
    modules: modules(record.modules),
    roles: roles(record.roles),
  };
}

export function featureStatus(feature: FeatureCard): FeatureStatus {
  if (feature.state === 'OFF') {
    return { label: 'Off', state: 'disabled' };
  }
  if (feature.experimental || feature.state === 'EXPERIMENTAL') {
    return { label: 'Experimental', state: 'experimental' };
  }
  if (!feature.enabled) {
    return { label: 'Disabled', state: 'disabled' };
  }
  if (feature.state === 'ON') {
    return { label: 'On', state: 'success' };
  }
  return { label: 'Not available', state: 'not-configured' };
}

export function featuresFor(model: SettingsModel, moduleName: string): FeatureCard[] {
  return model.modules.filter((module) => module.name === moduleName).flatMap((module) => module.features);
}

export function hasModule(model: SettingsModel, moduleName: string): boolean {
  return model.modules.some((module) => module.name === moduleName && module.registered);
}

function modules(input: unknown): ModuleCard[] {
  if (!Array.isArray(input)) {
    return [];
  }
  return input.filter(isRecord).map((module) => ({
    name: text(module.name),
    version: text(module.version),
    optional: module.optional === true,
    dependencies: stringList(module.dependencies),
    failed: module.failed === true,
    registered: module.registered !== false,
    features: features(module.features),
  }));
}

function features(input: unknown): FeatureCard[] {
  if (!Array.isArray(input)) {
    return [];
  }
  return input.filter(isRecord).map((feature) => ({
    id: text(feature.id),
    name: text(feature.name),
    module: text(feature.module),
    state: text(feature.state),
    enabled: feature.enabled === true,
    experimental: feature.experimental === true,
  }));
}

function roles(input: unknown): RoleGrant[] {
  if (!Array.isArray(input)) {
    return [];
  }
  return input.filter(isRecord).map((role) => ({
    role: text(role.role),
    capabilities: stringList(role.capabilities),
  }));
}

function stringList(input: unknown): string[] {
  if (!Array.isArray(input)) {
    return [];
  }
  return input.filter((item): item is string => typeof item === 'string');
}

function text(input: unknown): string {
  return typeof input === 'string' ? input : '';
}

function isRecord(input: unknown): input is Record<string, unknown> {
  return typeof input === 'object' && input !== null;
}
