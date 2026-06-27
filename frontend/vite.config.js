import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'

// https://vitejs.dev/config/
export default defineConfig({
  plugins: [react()],
  server: {
    port: 5173,
    // Proxy : toutes les requêtes /api/* sont redirigées vers le backend PHP
    proxy: {
      '/api': {
        target: 'http://localhost/RS_planner_react/backend/api',
        changeOrigin: true,
        rewrite: (path) => path.replace(/^\/api/, '')
      }
    }
  }
})
