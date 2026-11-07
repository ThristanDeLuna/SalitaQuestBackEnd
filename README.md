# Salita Quest Backend

Salita Quest is a language-learning app for three Philippine languages: Tagalog, Cebuano (Bisaya) and Hiligaynon. Visitors browse a map and word lists. Learners play Matching and Listening games and earn stars, stamps and streaks. Contributors suggest words, and Admins review them and manage regions, words and audio.

This repo is the JSON API.

## Stack

- **API:** LavaLust 4.7.0 (PHP `>=8.1` in `composer.json`; the `Dockerfile` defaults to PHP 8.5)
- **Front end:** React, in a separate repo (not verified)
- **Database:** MySQL on Aiven
- **Audio:** Cloudinary (`cloudinary/cloudinary_php`, installed with Composer)
- **Hosting:** Render, built from the `Dockerfile` (Render settings not verified)
- **Other services:** Google sign-in, Brevo email, MyMemory translation hints

## Local setup

```bash
composer install
cp .env.example .env
php lava serve
```

1. Fill in the variables listed in `.env.example`. Never commit `.env`, or any real value.
2. `php lava serve` starts the API at `http://127.0.0.1:3000`. Use `php lava serve --port=8080` for another port.
3. `php lava jwt:generate` can create `JWT_SECRET` and `REFRESH_TOKEN_KEY` and save them to `.env`.

These are the 22 variable names the code reads. `.env.example` lists the same names plus `APP_NAME`, which the code does not read through `getenv()`:

- **App and tokens:** `APP_ENV`, `APP_KEY`, `JWT_SECRET`, `REFRESH_TOKEN_KEY`
- **Database:** `DB_DRIVER`, `DB_HOST`, `DB_PORT`, `DB_USER`, `DB_PASSWORD`, `DB_NAME`, `DB_CHARSET`, `DB_PREFIX`, `DB_SSL_CA`
- **Cloudinary:** `CLOUDINARY_CLOUD_NAME`, `CLOUDINARY_API_KEY`, `CLOUDINARY_API_SECRET`, `CLOUDINARY_FOLDER`
- **Other services:** `GOOGLE_CLIENT_ID`, `BREVO_API_KEY`, `BREVO_SENDER_EMAIL`, `BREVO_SENDER_NAME`, `MYMEMORY_EMAIL`

## Tests

Plain PHP scripts, with no database and no network. Run them from the project root:

```bash
php tests/game_rules_test.php
php tests/round_builder_test.php
php tests/integration_libs_test.php
```

## Database and seed data

- **Migrations:** `app/migrations/`, run through the command-line-only routes `db/migrate`, `db/status` and `db/rollback` (exact command not verified). Never run `db/rollback` on the shared Aiven database.
- **Seed data:** `docs/seed_regions_and_draft_words.sql`. Run it after migrations 010 and 011 have created `regions` and `words`.

## API routes

From `app/config/routes.php`:

| Area | Routes |
|---|---|
| Home | `GET /` |
| Auth | `POST /auth/google`, `POST /auth/refresh`, `POST /auth/logout`, `GET /auth/me` |
| Dev login | `POST /auth/dev-login` (only when `APP_ENV` is `development`) |
| Regions | `GET /api/regions`, `GET /api/regions/{code}`, `GET /api/regions/{code}/words` |
| Matching | `GET /api/games/matching/{region}`, `POST /api/games/matching/{region}/submit` |
| Listening | `GET /api/games/listening/{region}`, `POST /api/games/listening/{region}/submit` |
| Passport | `GET /api/me/summary` |
| Submissions | `GET /api/submissions/suggest`, `GET /api/submissions`, `POST /api/submissions`, `DELETE /api/submissions/{id}` |
| Admin submissions | `GET /api/admin/submissions`, `POST /api/admin/submissions/{id}/approve`, `POST /api/admin/submissions/{id}/reject` |
| Admin audio | `GET /api/admin/audio`, `POST /api/admin/audio/{word_id}`, `DELETE /api/admin/audio/{word_id}` |
| Admin words | `GET /api/admin/words`, `POST /api/admin/words`, `PUT /api/admin/words/{id}`, `DELETE /api/admin/words/{id}` |
| Admin regions | `POST /api/admin/regions`, `PUT /api/admin/regions/{code}`, `DELETE /api/admin/regions/{code}` |
| Migrations (CLI only) | `GET db/migrate`, `GET db/status`, `GET db/rollback` |
