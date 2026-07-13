# Deployment Guide

Covers local-machine LAN testing (letting other devices on your network
try the app before public deployment) and production deployment
readiness. **Local-machine hosting, LAN or otherwise, is suitable for
controlled testing only — it is not a production deployment.**

## 1. Same Wi-Fi / LAN Testing

Lets another device on the same network (a phone, another laptop) reach
the app running on your development machine.

### Step 1 — find your machine's LAN IP

```powershell
ipconfig            # Windows - look for "IPv4 Address" under your active adapter
```

### Step 2 — start the frontend bound to all interfaces

`vite.config.ts` already sets `server: { host: true }`, so:

```bash
cd frontend && npm run dev
```

Vite will print both a `Local` and a `Network` URL — use the `Network`
one (e.g. `http://192.168.1.20:5173`) on other devices.

### Step 3 — start the backend

`php artisan serve` only needs to bind to `localhost` — the Vite dev
proxy always runs on **your** machine and forwards `/api`, `/sanctum`,
and `/storage` requests to `localhost:8000` server-side, regardless of
which host/IP the original browser request came in on. Other devices
never talk to port 8000 directly.

```bash
cd backend && php artisan serve
```

### Step 4 — session cookies work automatically

`SESSION_DOMAIN` is left **unset** by default specifically for this
reason — an explicit `SESSION_DOMAIN=localhost` would make the browser
reject the session cookie the moment you access the app via a LAN IP
instead of `localhost` (the cookie's `Domain` attribute has to match the
host the browser is actually on). Leaving it unset issues a host-only
cookie that works correctly for `localhost`, `127.0.0.1`, and any LAN IP
without editing anything.

### Step 5 — Google OAuth (only if a LAN device needs to sign in with Google)

Google's OAuth redirect URI is validated by Google, not by this app — a
LAN IP redirect URI must be added in the Google Cloud Console
(*APIs & Credentials → your OAuth client → Authorized redirect URIs*)
alongside the existing `http://localhost:8000/api/auth/google/callback`
entry, then `GOOGLE_REDIRECT_URI` in `.env` updated to match whichever URI
you're testing with. This can't be automated — Google requires it
configured through their console. Skip this step entirely if LAN testers
only need username/password login.

### Step 6 — firewall

Windows Defender Firewall may prompt to allow Node.js/PHP on first LAN
connection attempt — allow it for **Private** networks only, never
**Public**.

### What never needs to change

- The ML microservice (`:8100`) is only ever called server-to-server by
  Laravel — it never needs LAN exposure.
- The frontend has zero hardcoded `localhost` references anywhere in its
  source (verified via full-project grep) — all API calls are relative
  (`/api/...`), so they automatically follow whatever host the page was
  loaded from.

### What must never be exposed

- **MySQL** — never bind it to `0.0.0.0` or forward its port for LAN/
  internet testing. It has no purpose being reachable by anything other
  than the Laravel process on the same machine.
- **`.env`** — contains the app key, DB credentials, and any configured
  API keys. Never serve it, commit it, or place it under a public web
  root.
- **Debug information** — `APP_DEBUG=true` (the local default) renders
  full stack traces on error pages; this is fine for LAN testing among
  trusted testers, but must be `false` before any real deployment.

## 2. Production deployment checklist

- [ ] `APP_ENV=production`, `APP_DEBUG=false`
- [ ] `APP_URL` / `FRONTEND_URL` set to real domains (HTTPS)
- [ ] `SESSION_DOMAIN` set explicitly to your real domain (unlike local/LAN
      dev, production *should* pin this for security)
- [ ] `SANCTUM_STATEFUL_DOMAINS` set to the real frontend domain
- [ ] `CORS_ALLOWED_ORIGINS` set to the real frontend origin only (or left
      to fall back to `FRONTEND_URL`)
- [ ] Fresh `APP_KEY` generated for production (`php artisan key:generate`)
      — never reuse a development key
- [ ] Real database credentials, not the local XAMPP defaults
- [ ] `GOOGLE_REDIRECT_URI` updated to the production callback URL, added
      in Google Cloud Console
- [ ] `GEMINI_API_KEY` set if AI features should use the real Gemini API
      rather than the Mock fallback (the platform runs correctly with it
      unset — Mock implementations are the honest, zero-config default)
- [ ] `SUPER_ADMIN_EMAIL`/`SUPER_ADMIN_PASSWORD` changed from the
      development defaults before seeding a production admin
- [ ] `ML_SERVICE_URL` pointed at wherever the FastAPI service actually
      runs in production (same host or a separate one — Laravel calls it
      over plain HTTP server-to-server either way)
- [ ] `php artisan migrate --force` run against the production database
- [ ] Frontend built for production (`npm run build`) and served as
      static files, not via the Vite dev server
- [ ] `.env` file permissions locked down, never committed (confirm
      `.gitignore` coverage)
- [ ] `ml-service/data/raw/` and `ml-service/catboost_info/` confirmed
      gitignored (the OULAD clickstream alone is ~450MB)

## 3. Environment variable reference

See `backend/.env.example` for the full annotated list (every variable
this project actually reads, with inline comments explaining what each
does and when it needs changing) and `frontend/vite.config.ts` for the
frontend's own dev-server configuration. The ML microservice currently
reads no environment variables — all its paths are relative to the script,
and its host/port are set via the `uvicorn` command line, not a `.env`
file.
