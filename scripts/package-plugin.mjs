import { execFileSync, spawnSync } from 'node:child_process';
import { cpSync, mkdirSync, readFileSync, rmSync, existsSync } from 'node:fs';
import { join, dirname, sep } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = join(dirname(fileURLToPath(import.meta.url)), '..');
const channel = process.argv.includes('--staging') ? 'staging' : 'production';
const stage = join(root, 'dist', 'querynova');
const zipPath = join(root, 'dist', `querynova-${channel}.zip`);

rmSync(stage, { recursive: true, force: true });
mkdirSync(stage, { recursive: true });

execFileSync('npm', ['run', channel === 'staging' ? 'build:staging' : 'build'], {
  cwd: root,
  stdio: 'inherit',
});

for (const path of ['querynova.php', 'uninstall.php', 'composer.json', 'composer.lock', 'src']) {
  cpSync(join(root, path), join(stage, path), { recursive: true });
}
mkdirSync(join(stage, 'build'), { recursive: true });
cpSync(join(root, 'build', 'admin.js'), join(stage, 'build', 'admin.js'));
cpSync(join(root, 'build', 'channel.json'), join(stage, 'build', 'channel.json'));
if (existsSync(join(root, 'languages'))) {
  cpSync(join(root, 'languages'), join(stage, 'languages'), { recursive: true });
}

const composer = spawnSync('composer', ['--version'], { encoding: 'utf8' });
if (composer.status === 0) {
  execFileSync('composer', ['install', '--no-dev', '--no-interaction', '--prefer-dist', '--no-progress'], {
    cwd: stage,
    stdio: 'inherit',
  });
} else {
  copyProductionVendor(root, stage);
}

rmSync(zipPath, { force: true });
execFileSync('zip', ['-r', '-X', zipPath, 'querynova'], {
  cwd: join(root, 'dist'),
  stdio: 'inherit',
});

const listing = execFileSync('unzip', ['-l', zipPath], { encoding: 'utf8' });
const forbidden = ['node_modules/', 'tests/', '.git/', '.github/', '.map', '.env', '.pem', '.key', 'phpunit', 'phpstan'];
for (const token of forbidden) {
  if (listing.includes(token)) {
    throw new Error(`Release ZIP contains ${token}`);
  }
}
if (!listing.includes('build/admin.js') || !listing.includes('build/channel.json') || !listing.includes('src/')) {
  throw new Error('Release ZIP is missing the compiled admin, channel, or source.');
}

console.log(zipPath);

function copyProductionVendor(projectRoot, destination) {
  const installed = JSON.parse(readFileSync(join(projectRoot, 'vendor/composer/installed.json'), 'utf8'));
  const devNames = new Set(installed['dev-package-names'] ?? []);
  const blockedRoots = new Set(['bin']);
  for (const name of devNames) {
    blockedRoots.add(name.split('/')[0] ?? name);
  }
  const vendorRoot = join(projectRoot, 'vendor');
  cpSync(vendorRoot, join(destination, 'vendor'), {
    recursive: true,
    filter: (source) => {
      if (source === vendorRoot) {
        return true;
      }
      const relative = source.slice(vendorRoot.length + 1);
      const top = relative.split(sep)[0] ?? '';
      return !blockedRoots.has(top);
    },
  });
}
