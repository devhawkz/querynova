import { writeFileSync } from 'node:fs';
import { defineConfig } from 'vitest/config';
import react from '@vitejs/plugin-react';

export default defineConfig(({ mode }) => ({
  plugins: [
    react(),
    {
      name: 'querynova-build-channel',
      closeBundle() {
        const channel = mode === 'staging' ? 'staging' : mode === 'production' ? 'production' : 'development';
        writeFileSync(
          'build/channel.json',
          JSON.stringify({
            channel,
            diagnostics: channel === 'staging',
            debugDefault: false,
          }),
        );
      },
    },
  ],
  build: {
    outDir: 'build',
    emptyOutDir: true,
    sourcemap: mode === 'development',
    rollupOptions: {
      input: 'assets/admin/app/main.tsx',
      output: {
        entryFileNames: 'admin.js',
        assetFileNames: 'admin.[ext]',
      },
    },
  },
  test: {
    environment: 'node',
    include: ['assets/admin/**/*.test.ts'],
  },
}));
