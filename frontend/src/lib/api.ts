import axios, { type InternalAxiosRequestConfig } from 'axios';

/** Longest a single request may take. */
const REQUEST_TIMEOUT_MS = 60_000;

export const api = axios.create({
  baseURL: '/api',
  timeout: REQUEST_TIMEOUT_MS,
  withCredentials: true,
  xsrfCookieName: 'XSRF-TOKEN',
  xsrfHeaderName: 'X-XSRF-TOKEN',
  headers: {
    Accept: 'application/json',
  },
});

let csrfReady: Promise<void> | null = null;

/** Sanctum requires a CSRF cookie to be present before any state-changing request. */
function ensureCsrfCookie(): Promise<void> {
  if (!csrfReady) {
    csrfReady = axios
      .get('/sanctum/csrf-cookie', { baseURL: '/', withCredentials: true, timeout: REQUEST_TIMEOUT_MS })
      .then(() => undefined)
      .catch((error) => {
        // A failed fetch must not be cached: otherwise one blip at the wrong moment would make every
        // later login, answer and score submission fail until the page is reloaded.
        csrfReady = null;
        throw error;
      });
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

type RetriableConfig = InternalAxiosRequestConfig & { _csrfRetried?: boolean };

api.interceptors.response.use(undefined, async (error) => {
  const config = error?.config as RetriableConfig | undefined;
  if (error?.response?.status === 419 && config && !config._csrfRetried) {
    config._csrfRetried = true;
    csrfReady = null;
    await ensureCsrfCookie();
    return api.request(config);
  }
  throw error;
});
