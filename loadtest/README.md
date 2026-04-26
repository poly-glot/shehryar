# /chatapp spike load test

Manual k6 Cloud spike test against the production `/chatapp` endpoints.
See `docs/superpowers/specs/2026-04-26-k6-cloud-spike-test-design.md`
for the design rationale.

## What it does

Runs three scenarios in parallel against `https://shehryar.dev/chatapp`
for ~3 minutes:

- `getAllUsers` — GET `/getAllUsers.php` with a per-request cache-buster
  so Firebase's CDN doesn't shield the origin.
- `getUserByEmail` — POST `/getUserByEmail.php` for a known user.
- `login` — POST `/login.php` with shared test credentials (Argon2id verify).

Each scenario ramps 0 → 60 VUs over 30s, holds at 60 for 2 minutes, then
ramps down. Peak load: 180 effective concurrent VUs from
`amazon:gb:london`.

The test fails (exits non-zero) on:
- `http_req_failed > 1%` overall, or
- `http_req_duration` p(95) above 500ms (GETs) / 1500ms (login).

## One-time setup

Auth the k6 CLI to your Grafana Cloud k6 account once per machine:

```bash
k6 cloud login --token <YOUR_TOKEN>
```

The token is stored in `~/.config/loadimpact/config.json` and isn't
referenced from the repo. The repo-root file `k6.md` (gitignored) holds
your token for convenience.

## Running the test

```bash
k6 cloud run \
  -e CHATAPP_USER_EMAIL=sharyarahmed4567@gmail.com \
  -e CHATAPP_USER_PASSWORD='Thomas' \
  loadtest/k6.config.js
```

(k6 v1.7+ does not auto-import shell env vars; use `-e KEY=VAL` flags.
Setting `CHATAPP_USER_EMAIL=... k6 ...` as a shell prefix will NOT work
— the script's startup guard will fire because `__ENV` won't see it.)

The CLI prints a Grafana Cloud k6 dashboard URL on start. Results stream
there in real time and are persisted under project `7298441`.

The script throws at startup if either env var is missing.

## Caveats

- This hits production. Cloud Run autoscales briefly; expect at most
  single-digit dollars of compute per run.
- OCI MySQL HeatWave Always Free is the likely bottleneck — if the test
  saturates it, all three endpoints slow together.
- All 60 login VUs share one account; per-user MySQL caching may make
  numbers look better than reality.
