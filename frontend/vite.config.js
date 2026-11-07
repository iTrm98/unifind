import { copyFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import tailwindcss from '@tailwindcss/vite';
import react from '@vitejs/plugin-react';
import { defineConfig } from 'vite';

// In development the API runs on Laravel's port 8000 and Vite proxies to it,
// so the browser only ever talks to http://localhost:5173.
const backend = 'http://127.0.0.1:8000';

// GitHub Pages has no SPA fallback: it serves 404.html for unknown paths, so a
// copy of index.html there lets deep links like /unifind/admin/cases load.
const spaFallback = {
  name: 'spa-404-fallback',
  apply: 'build',
  writeBundle(options) {
    copyFileSync(`${options.dir}/index.html`, `${options.dir}/404.html`);
  },
};

export default defineConfig(({ command, isPreview }) => ({
  // Production builds (and `vite preview` of them) are served from
  // https://itrm98.github.io/unifind/; local dev stays at /.
  base: command === 'build' || isPreview ? '/unifind/' : '/',
  // '@/' is src/.
  resolve: { alias: { '@': fileURLToPath(new URL('./src', import.meta.url)) } },
  plugins: [react(), tailwindcss(), spaFallback],
  server: {
    port: 5173,
    strictPort: true,
    proxy: {
      '/api': { target: backend, changeOrigin: true },
      '/storage': { target: backend, changeOrigin: true },
    },
  },
}));
