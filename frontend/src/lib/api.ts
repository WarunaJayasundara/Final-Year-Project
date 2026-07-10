import axios from 'axios';

export const api = axios.create({
  baseURL: '/api',
  withCredentials: true,
  xsrfCookieName: 'XSRF-TOKEN',
  xsrfHeaderName: 'X-XSRF-TOKEN',
  headers: {
    Accept: 'application/json',
  },
});

let csrfReady: Promise<void> | null = null;

/**
 * Sanctum requires a CSRF cookie to be present before any state-changing
 * request. Call this once before the first login/mutation; subsequent calls
 * reuse the same in-flight/completed promise.
 */
export function ensureCsrfCookie(): Promise<void> {
  if (!csrfReady) {
    csrfReady = axios
      .get('/sanctum/csrf-cookie', { baseURL: '/', withCredentials: true })
      .then(() => undefined);
  }
  return csrfReady;
}

api.interceptors.request.use(async (config) => {
  const method = (config.method ?? 'get').toLowerCase();
  if (method !== 'get') {
    await ensureCsrfCookie();
  }
  return config;
});
