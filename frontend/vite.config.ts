import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'
import tailwindcss from '@tailwindcss/vite'
import path from 'path'

// https://vite.dev/config/
export default defineConfig({
  plugins: [react(), tailwindcss()],
  resolve: {
    alias: {
      '@': path.resolve(__dirname, './src'),
    },
  },
  server: {
    port: 5173,
    // Bind to 0.0.0.0 (not just localhost) so other devices on the same
    // Wi-Fi/LAN can reach the dev server at http://<this-machine-LAN-IP>:5173
    // - see docs/DEPLOYMENT_GUIDE.md "Same Wi-Fi / LAN Testing". The proxy
    // targets below stay "localhost:8000" deliberately: the proxy always
    // runs ON this machine, forwarding to the backend also running on this
    // machine, regardless of which host/IP the original client connected
    // through - it never needs to know the client's address.
    host: true,
    proxy: {
      '/api': {
        target: 'http://localhost:8000',
        changeOrigin: true,
      },
      '/sanctum': {
        target: 'http://localhost:8000',
        changeOrigin: true,
      },
      '/storage': {
        target: 'http://localhost:8000',
        changeOrigin: true,
      },
    },
  },
})
