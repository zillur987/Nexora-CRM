import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';

// base './' keeps the build portable: drop `dist/` in any folder or sub-path.
export default defineConfig({
  base: './',
  plugins: [vue()],
});
