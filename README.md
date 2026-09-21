# HelaIQ

**An AI-powered cognitive-training platform for IQ development in Sri Lanka.**
Final-year project CT/2020/074, W.R. Jayasundara, University of Kelaniya.

Students take an adaptive placement test, practise daily, play cognitive games, and get an
explainable exam-readiness prediction and a personalised study plan. The interface is fully
bilingual (English / Sinhala) and works on phones.

## Features

| Area | What it does |
|---|---|
| Adaptive testing | Rasch (IRT) computerised-adaptive placement, maximum-likelihood ability estimation, deviation-IQ score |
| Question bank | 6,759 active competitive-exam-style questions across 5 cognitive categories, including 1,484 generated SVG image questions |
| Practice | Daily sessions weighted towards weak areas, timed mock exams, per-question response-time capture |
| Games | 8 cognitive games (memory, working memory, attention, mental rotation, reasoning, mental maths) |
| Exam readiness | XGBoost classifier with SHAP explanations, risk of dropping practice, next-score estimate |
| Study support | Rule-based study plan, spaced-repetition study notes, AI coach chat (Gemini, with offline fallback) |
| Admin | Question/category/user management, psychometrics, question-bank statistics, ML research dashboard, AI question review |

## Architecture

```
React 19 SPA (Vite, Tailwind 4)  --/api-->  Laravel 9 API (Sanctum)  --HTTP-->  FastAPI ML service
                                                   |                              (XGBoost + SHAP)
                                                MySQL 8
```

* `frontend/`   React + TypeScript SPA (TanStack Query, i18next, shadcn/ui, Recharts)
* `backend/`    Laravel 9 / PHP 8.0 API, IRT engine, question generators, tests
* `ml-service/` Python 3.11 training pipeline and inference API
* `docs/`       Thesis document and database schema

External integrations (Gemini, ML service) follow one pattern: an interface, a mock that works with
zero configuration, and a real implementation chosen by config. Nothing needs an API key to run.

## Run locally (development)

On Windows with XAMPP, `start-dev.bat` starts everything below in one go. Otherwise:

Prerequisites: PHP 8.0, Composer, Node 20+, MySQL 8 (XAMPP is fine), Python 3.11.

```bash
# 1. Backend  ->  http://localhost:8000
cd backend
cp .env.example .env            # then set DB_* and run: php artisan key:generate
composer install
php artisan migrate
php artisan content:import      # loads the validated question bank (levels, categories, games, badges, questions)
php artisan db:seed --class=SuperAdminSeeder
php artisan serve

# 2. Frontend  ->  http://localhost:5173  (proxies /api to :8000)
cd frontend && npm install && npm run dev

# 3. ML service (optional; the app degrades gracefully without it)  ->  http://localhost:8100
cd ml-service
python -m venv venv && ./venv/Scripts/pip install -r requirements.txt   # use venv/bin on Linux/macOS
./venv/Scripts/python -m uvicorn app:app --host 127.0.0.1 --port 8100
```

Admins sign in at `/admin/login`; students sign in with Google. Configure the OAuth client in
`backend/.env` (`GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`, `GOOGLE_REDIRECT_URI`).

## Host it (Docker, single origin)

One command brings up MySQL, the ML service and the web app (Laravel serving the built SPA):

```bash
cp .env.docker.example .env.docker     # set APP_KEY and the two passwords
docker compose up --build              # http://localhost:8080
```

On first start the container migrates the database, imports the question bank
(`backend/database/content/content.json.gz`) and creates the admin account. All of that is idempotent.
For a real domain, set `APP_URL`, `FRONTEND_URL`, `SANCTUM_STATEFUL_DOMAINS` and `GOOGLE_REDIRECT_URI` to
that domain in `.env.docker` and add the redirect URI to the Google OAuth client.

To refresh the shipped question bank after editing questions: `php artisan content:export`.

## Quality checks

```bash
cd backend  && php artisan test                 # PHPUnit (uses the dev database, cleans up after itself)
cd frontend && npm run typecheck && npm run lint
cd backend  && python tools/validate_sinhala.py --all   # Sinhala text validator (see below)
```

Code style: Laravel Pint (`backend/vendor/bin/pint`), oxlint, `.editorconfig`.

## Machine learning

Training data is a hybrid of real and calibrated synthetic rows (73,637 rows, 43 features):
Open University Learning Analytics Dataset (OULAD, 32,593 rows), UCI Student Performance (1,044 rows)
and 40,000 synthetic rows whose weights were calibrated on the real outcomes.

| Metric (held-out test set) | Value |
|---|---|
| Model | XGBoost (selected from 9 candidates, Optuna nested-CV tuning) |
| Accuracy / macro-F1 | 0.697 / 0.681 |
| ROC-AUC (one-vs-rest, macro) | 0.906 |
| Macro-F1 with a student-disjoint split | 0.679 |

The student-disjoint check (`ml-service/grouped_split_validation.py`) measures leakage from students who
appear in several OULAD module presentations; it lowers the score by about 0.6 percentage points, so the
result is not an artefact of the split. Only 45.7% of rows are real, and the label for the rest comes from a
calibrated heuristic, so treat the numbers as estimates of this pipeline, not of real-world exam outcomes.

## Sinhala content

Admins can draft the Sinhala for a new question from its English text (Gemini, using the reviewed glossary). A machine draft must be
ticked as reviewed before it can be saved, and text with corrupted characters is rejected. To audit the whole bank:
`php artisan sinhala:audit`. The Gemini model is configurable (`GEMINI_MODEL`, `GEMINI_TRANSLATION_MODEL`; default `gemini-flash-latest`).

Never hand-write new Sinhala. Reuse the verified corpus, and run `python tools/validate_sinhala.py --all`
after any change to seeders or `frontend/src/locales/si`.

## Known limitations

* Student sign-in is Google-only, so there is no password login for students in development.
* Time-aware response features did not improve the model in the ablation study and are not in the live model.
* The visual-question bank has no stored generation metadata for about 94% of image questions.
* Target population differs from the public training data (UK/Portuguese students vs Sri Lankan candidates).
