import { defineConfig } from 'vitest/config';
import react from '@vitejs/plugin-react';

export default defineConfig(({ mode }) => ({
  plugins: [react()],
  build: {
    outDir: 'build',
    emptyOutDir: true,
    sourcemap: mode !== 'production',
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
