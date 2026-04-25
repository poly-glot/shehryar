# shehryar

Shehryar's personal site plus an archived **chatapp** REST backend
(nginx + PHP-FPM + MySQL), served end-to-end from a single Cloud Run
service. Firebase Hosting is used only as a thin proxy so the custom
domain and CDN edge caching still apply.

Production: <https://shehryar.junaid.guru>
Firebase default: <https://shehryar.web.app>

## Architecture

![Architecture](diagrams/01-architecture-overview.svg)

- Single container (`Dockerfile`) runs **nginx** + **PHP-FPM** via supervisord.
- nginx long-caches versioned assets (`Cache-Control: public, max-age=31536000, immutable`)
  and short-caches HTML with a large `s-maxage` so Firebase's CDN holds it (`public, max-age=300, s-maxage=86400, stale-while-revalidate=604800`).
- PHP execution is restricted to `/chatapp/*.php` in the nginx config.
- `firebase.json` rewrites `**` to the Cloud Run service — the static site *is* served by Cloud Run, the CDN just caches it.
- MySQL connection details come from Secret Manager (`db-host`,
  `shehryar-db-user`, `shehryar-db-pass`, `shehryar-db-name`).

## Repo layout

```
Dockerfile           # multi-stage: base → dev (devcontainer) + prod (Cloud Run)
docker/              # nginx.conf, php-fpm.conf, supervisord.conf
src/                 # application source
  index.html         # static landing
  style.css, reset.css, images/
  chatapp/           # PHP REST API
    *.php            # endpoints (login, registerUser, addMessage, ...)
    health.php       # no-DB health check (Cloud Run startup probe)
    inc/             # bootstrap, Database, Http, Auth, Config
db/migrations/       # forward-only SQL migrations (NNNN_<slug>.sql)
bin/migrate.php      # migration runner — used in CI and via the `migrate` alias
firebase.json        # hosting → run rewrite
.github/workflows/   # build + push + migrate + deploy
```

## Local development

### Devcontainer (recommended)

Open the repo in VS Code → **Reopen in Container** (requires Docker Desktop).
The devcontainer spins up two services via `.devcontainer/docker-compose.yml`:

- `app` — PHP 8.3 dev image with Claude Code, GitHub CLI, zsh, and the
  `pdo_mysql` extension pre-installed. Runs `sleep infinity`; you start the
  web server yourself with the `serve` alias. `~/.claude` on the host is
  mounted in so Claude login + memory survive rebuilds.
- `mysql` — MySQL 9.6 (tracks OCI HeatWave Always Free; the free tier can't
  be version-pinned and rolls forward on maintenance cycles, so the tag here
  should be bumped whenever prod drifts). Boots empty. Reachable from the
  app as `mysql:3306`, and from the host at `localhost:3306` (user
  `shehryar` / password `shehryar`, database `rn_chatapp`).

DB credentials are injected as env vars on `app` (`DB_HOST=mysql`, `DB_USER`,
`DB_PASS`, `DB_NAME`), so `src/chatapp/inc/config.php` picks them up without
further setup. On first boot, `post-create.sh` waits for mysql then runs
`php bin/migrate.php` to apply `db/migrations/*.sql` (schema + dev seed
data). To wipe and re-migrate, `docker compose down -v` then reopen.

Inside the container, the `post-create.sh` script wires up a few aliases:

| Alias        | What it does                                             |
|--------------|----------------------------------------------------------|
| `serve`      | `php -S 0.0.0.0:8080 -t /workspace/src` — PHP dev server |
| `db`         | MySQL shell on `rn_chatapp` as the `shehryar` user       |
| `db-root`    | MySQL shell as root                                      |
| `migrate`    | Apply pending `db/migrations/*.sql`                      |
| `claude`     | Claude Code (`--dangerously-skip-permissions`)           |
| `prod-build` | `docker build --target prod` the Cloud Run image         |
| `prod-run`   | Run the Cloud Run image on `:8081` against the dev mysql |

`serve` uses PHP's built-in server, which is enough for local dev but does
*not* mirror the prod nginx routing (PHP execution restricted to
`/chatapp/*.php`, caching headers, etc.). Use `prod-build` + `prod-run` when
you need prod parity.

### Running the image directly

Useful if you want to exercise the Cloud Run image against a remote DB:

```bash
docker build -t shehryar-api .
docker run --rm -p 8080:8080 \
  -e DB_HOST=... -e DB_USER=... -e DB_PASS=... -e DB_NAME=rn_chatapp \
  shehryar-api
# open http://localhost:8080
```

## Chatapp API

All endpoints live under `/chatapp/*.php`. Bodies are JSON; mutating endpoints
require `POST`. Smoke-test against the devcontainer (`serve` on `:8080`):

```bash
BASE=http://localhost:8080/chatapp

# Liveness probe — no DB touch, used by Cloud Run startup check.
curl -sS $BASE/health.php
# → {"status":"ok"}

# List all users.
curl -sS $BASE/getAllUsers.php
# → {"results":[{user_id,unique_id,...,imageUri}, ...]}

# Lookup a single user by email.
curl -sS -X POST $BASE/getUserByEmail.php -H 'Content-Type: application/json' \
  -d '{"email":"sharyarahmed4567@gmail.com"}'
# → 200 {results:{...,imageUri}}; 404 if missing.

# Login — Argon2id verify, with legacy plaintext fallback that rehashes on success.
curl -sS -X POST $BASE/login.php -H 'Content-Type: application/json' \
  -d '{"Email":"sharyarahmed4567@gmail.com","Password":"Thomas"}'
# → 200 {results:{Message:"success",UniqueID,FirstName,...}}; 401 on bad creds.

# Register — validates email, rejects duplicates, Argon2id hash, returns generated unique_id.
curl -sS -X POST $BASE/registerUser.php -H 'Content-Type: application/json' \
  -d '{"Fname":"A","Lname":"B","Email":"a@b.com","Password":"x","Image":"https://x/y.jpg"}'
# → 201 {success:true,unique_id}; 400 invalid email; 409 duplicate.

# Contacts the given user has chatted with, most-recent first.
curl -sS -X POST $BASE/getContactsOfUser.php -H 'Content-Type: application/json' \
  -d '{"unique_id":"1134750038"}'
# → {"results":[{user_id,unique_id,...,last_contact_id,imageUri}, ...]}

# Send a message; auto-creates the contacts row on first message between the pair.
curl -sS -X POST $BASE/addMessage.php -H 'Content-Type: application/json' \
  -d '{"sender_id":"1134750038","receiver_id":"288862539","msg":"hi"}'
# → {"success":"Successfully sent message"}

# Full chat history between two users, oldest-first.
curl -sS -X POST $BASE/getChat.php -H 'Content-Type: application/json' \
  -d '{"first_user_id":"1134750038","second_user_id":"288862539"}'
# → {"results":[{id,message,sender,receiver,sent_at}, ...]}
```

## Deployment

Push to `main` — GitHub Actions builds the image, **runs `bin/migrate.php`
against OCI MySQL using credentials fetched from GCP Secret Manager**,
deploys the Cloud Run revision, and re-publishes the Firebase Hosting
rewrite config. A failed migration aborts the deploy before the new
revision is rolled out.

### Required GitHub secrets

Only WIF auth lives in GitHub. The DB credentials are read at deploy time
from GCP Secret Manager (`db-host`, `shehryar-db-{user,pass,name}`) — the
CI/CD service account is granted `roles/secretmanager.secretAccessor` on
those four secrets in the `firebase-cloud` terraform repo.

| Secret         | Source                                                                        |
|----------------|-------------------------------------------------------------------------------|
| `WIF_PROVIDER` | `terraform -chdir=firebase-cloud/terraform output -raw shehryar_wif_provider` |
| `GCP_SA_EMAIL` | `terraform -chdir=firebase-cloud/terraform output -raw shehryar_gcp_sa_email` |

### Migrations

Forward-only SQL files in `db/migrations/`, named `NNNN_<slug>.sql`.
`bin/migrate.php` applies pending files in lexicographic order, tracking
applied versions in a `schema_migrations` table. Reuses the app's
`Config` class so the env-var contract (`DB_HOST` / `DB_USER` /
`DB_PASS` / `DB_NAME`) is identical to the running app.

To add a migration: create the next-numbered file in `db/migrations/`,
run `migrate` in the devcontainer to apply locally, commit. CI applies
it on the next deploy.

**Caveat for destructive migrations** (DROP COLUMN, etc.): they apply
*before* the new revision rolls out, so the previous revision will fault
the moment the migration commits. For destructive changes, do it in two
deploys — first deploy stops reading the column, second deploy drops it.
