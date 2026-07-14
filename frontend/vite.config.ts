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
  // `vite preview` (serves the production build) needs its own host/proxy
  // config - used for the public tunnel, since a bundled production build is
  // far more robust over a free tunnel than the dev server's hundreds of
  // small unbundled module requests.
  preview: {
    port: 4173,
    host: true,
    // ngrok's reserved static domain is now the primary stable public URL
    // (fixed across restarts, unlike the .loca.lt/.trycloudflare.com quick
    // tunnels tried earlier - kept in the list in case those are used again).
    allowedHosts: ['.loca.lt', '.trycloudflare.com', '.ngrok-free.dev', '.ngrok-free.app'],
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
