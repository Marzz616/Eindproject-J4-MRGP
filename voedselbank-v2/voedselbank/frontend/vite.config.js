import { fileURLToPath, URL } from 'node:url';
import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';

/**
 * Vite bouwt de Vue-app. Tijdens het ontwikkelen (npm run dev) stuurt de
 * proxy alle /api-verzoeken door naar de PHP-backend, zodat frontend en
 * backend voor de browser op hetzelfde adres staan (sessiecookie werkt dan).
 */
export default defineConfig({
  plugins: [vue()],
  resolve: {
    alias: { '@': fileURLToPath(new URL('./src', import.meta.url)) },
  },
  server: {
    port: Number(process.env.VITE_PORT || 5173),
    proxy: {
      '/api': process.env.VITE_API_URL || 'http://localhost:8080',
    },
  },
});
