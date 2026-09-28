import { execFileSync, spawnSync } from 'node:child_process';
import { cpSync, mkdirSync, rmSync, existsSync } from 'node:fs';
import { join, dirname } from 'node:path';
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

const composer = composerInvocation();
execFileSync(composer[0], [...composer.slice(1), 'install', '--no-dev', '--no-interaction', '--prefer-dist', '--no-progress'], {
  cwd: stage,
  stdio: 'inherit',
});

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
if (!listing.includes('build/admin.js') || !listing.includes('build/channel.json') || !listing.includes('src/') || !listing.includes('vendor/autoload.php')) {
  throw new Error('Release ZIP is missing the compiled admin, channel, source, or autoload.');
}
execFileSync('php', [join(root, 'scripts/assert-release-autoload.php'), zipPath], {
  stdio: 'inherit',
});

console.log(zipPath);

function composerInvocation() {
  const onPath = spawnSync('composer', ['--version'], { encoding: 'utf8' });
  if (onPath.status === 0) {
    return ['composer'];
  }
  const phar = process.env.COMPOSER_PHAR;
  if (phar && existsSync(phar)) {
    return ['php', phar];
  }
  throw new Error('composer is not on PATH. Set COMPOSER_PHAR to a composer.phar. The package script will not copy a dev vendor tree, because that autoload still requires packages omitted from the ZIP.');
}
