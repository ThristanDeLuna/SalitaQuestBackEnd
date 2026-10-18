# Salita Quest: DECISIONS

Last updated: Oct 18, 2026 (first version, written from Member C's chats).
Rule: add a decision here once it is settled. If a chat's advice conflicts with this file, flag it.
Never put secrets (keys, passwords, DB credentials) in this file or in any chat.

## Who is working on what

| Member | Slice | Status on Oct 18 |
|---|---|---|
| A | Google sign-in, JWT, roles, deployment, base API wiring, `api_logs` (proposed) | Not reported here yet |
| B | Regions, words, map, admin pages, ERD | Not reported here yet |
| C | Audio (Cloudinary), games, scoring, stamps, passport, streak | See "Member C" below |
| D | Submissions, approvals, emails (Brevo), translation (MyMemory) | Reviewing PR #1 (buddy review for C) |

Keeper of this file: to be confirmed (suggested: A).

## Settled (from the project handoff, Oct 17)

- Architecture: React (Vite) on Vercel + LavaLust JSON API on Render + MySQL on Aiven (SSL). Free tiers only.
- Two repos only: `SalitaQuestBackEnd` (docs in `/docs`) and `SalitaQuestFrontEnd`.
- Branches + pull requests. Nobody pushes to `main`. Buddy review: A with B, C with D.
- Auth: Google ID token from React -> `POST /auth/google` -> API verifies -> API issues its own JWT, sent as `Authorization: Bearer`. (Recommended design, team confirmation pending.)
- Audio on Cloudinary, never local disk. Emails through the Brevo HTTP API, not SMTP. No cron or scheduler.
- Five integrations: Google OAuth, Leaflet + OpenStreetMap, Cloudinary, Brevo, MyMemory. API-side calls are logged in `api_logs`.
- Scope cuts: no flashcards, no third game, no welcome or streak emails, about 10 words per region, same concepts in all three languages.
- Migration number ranges: A 003-009, B 010-019, C 020-029, D 030-039.
- Feature freeze Nov 8. Defense planned for Nov 16 (could fall Nov 16-20).

## Verified in the repo (LavaLust 4.7.0, per the repo changelog)

- `app/config/autoload.php` autoloads nothing. Libraries load per controller.
- `$this->call->library('game_rules')` loads `app/libraries/Game_rules.php` and `$this->game_rules->score_round(...)` works inside a real controller. Tested Oct 17 on PHP 8.3.28.
- LavaLust has a CLI: `php lava serve` (default port 3000), `make:controller|model|library|...`, `route:list`, `env:check`, `key:generate`, `jwt:generate` (fills `JWT_SECRET` and `REFRESH_TOKEN_KEY` in `.env`).
- The CLI has NO `migration` command in this repo. Migrations run through a controller instead (see "Migration runner").
- The `Api` library (CORS, JWT, refresh tokens, rate limit, `respond()`, `respond_error()`) exists. Changelog 4.2.5 fixed it stripping the Authorization header on render.com.
- Copy `.env.example` to `.env`. `.env` must stay out of Git (check with `git check-ignore .env`).

## Migration runner (proposed, needs one owner to commit it)

Used locally by Member C, not committed yet:
- `app/controllers/MigrationController.php`, CLI-only (refuses to run from a browser), with `migrate`, `dry_run`, `status`, `preview` and `rollback`. No `fresh`, `reset` or `rollback-all`.
- Routes `db/migrate`, `db/migrate/dry-run`, `db/status`, `db/preview`, `db/rollback`, run with `php public/index.php db/status`.
- `app/config/migration.php` with `migration_enabled = true` and `migration_path = APP_DIR.'migrations/'`.
- Rule: never run `rollback`, `refresh`, `reset` or `fresh` against the shared Aiven database. Rollback reverts the whole last batch, which could include other members' migrations. Test on a local MySQL first.

## Member C

Decided:
- Tables (migration `020_create_game_tables.php`, no foreign keys yet): `audio`, `game_attempts`, `stamps`, `streaks`.
- `audio` unique on `word_id`. `stamps` unique on `(user_id, region_code)`. `game_attempts` indexed on `(user_id, region_code)`. `streaks.user_id` is the primary key.
- `game_attempts.game` is VARCHAR (not ENUM). The controller accepts only `matching` or `listening`.
- Timestamps use DATETIME with default CURRENT_TIMESTAMP, matching the style of the existing migrations.
- Rules in `Game_rules`: 10 points per correct answer, matching 4 pairs (max 40), listening 5 questions (max 50), pass at 60% (integer maths), stamp needs a passing matching AND a passing listening attempt in the region, failed attempts still count for the streak, streak and Word of the Day use dates passed in (Asia/Manila).
- Server holds the answer key and does all scoring and stamp awarding.

Status:
- DONE: `Game_rules` and 34 passing tests. PR #1 `c/game-rules` is open, waiting for D's review.
- DONE: loader check (see above).
- IN PROGRESS: migration `020` is written. Running it on a local Laragon MySQL (`salita_dev`) is not yet confirmed.
- NEXT: `Cloudinary_lib` and a test script (needs a Cloudinary key in `.env` and one test recording), then the game endpoints, then the React game screens.

## Conflicts and open items for other members

1. **A: the repo's `001_create_users_table.php` is LavaLust's stock sample** (`username`, `password`, `is_active`, roles `admin/moderator/user`). Our Database Schema says Google login needs `google_id`, `email`, `name`, role `learner/contributor/admin`, and no password. One of A's migrations (003-009) must replace or alter it before login is built.
2. **Who owns `api_logs`?** Not assigned. Suggested: A creates it first (columns are in the Database Schema). Until it exists, Member C's `Cloudinary_lib` falls back to `error_log` for logging.
3. **Who creates and owns the Aiven database and shares access?** Not assigned. Suggested: A. Credentials go through a private channel, never a chat.
4. **Generator folder case:** LavaLust docs say the generators write to `app/Controllers`, `app/Models`, `app/Libraries` (capital letters), but this repo uses lowercase folders. Render runs Linux, where the case matters. After using a generator, check where the file landed and move it to the lowercase folder if needed.
5. **Dockerfile uses PHP 8.5.** Not yet built on Render. Everyone's code was only tested on PHP 8.3.
6. **Pass mark 60%** was the team's own guess and still needs a team confirmation.
7. **SDLC worksheet** still says about 15 words per region and lists flashcards. Update it to about 10 words per region, no flashcards, and the schedule dates Oct 16, Oct 23, Nov 8, Nov 12, Nov 14.
8. **Professor's weekly check dates are still unknown.** Ask what is checked each week (demo, documents or code). The "Checking 1" sheet asks for a site map, wireframes, ERD and a running LavaLust project with routes.

## Not verified yet

- Whether dbforge `add_key` accepts an array for composite keys (used in migration `020`; check with `SHOW INDEX FROM stamps;` after running it).
- Cloudinary: audio under the "video" resource type, how credits are counted, the unit of the "500 admin API limit".
- Google ID token verification endpoint behavior (read Google's docs before building it).
- Whether `db_name` in Member C's local `.env` is exactly `salita_dev` (masked output looked 2 characters short).
