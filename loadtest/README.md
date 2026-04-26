# /chatapp spike load test

Manual k6 Cloud spike test against the production `/chatapp` endpoints.

## What it does

Runs three scenarios sequentially against `https://shehryar.dev/chatapp`
for ~11 minutes total (3 × ~3 min, with a 1-min gap between each):

- `getAllUsers` — GET `/getAllUsers.php` with a per-request cache-buster so Firebase's CDN doesn't shield the origin. (starts at 0:00)
- `getUserByEmail` — POST `/getUserByEmail.php` for a known user. (starts at 4:00)
- `login` — POST `/login.php` with shared test credentials (Argon2id verify). (starts at 8:00)

Each scenario ramps 0 → 60 VUs over 30s, holds at 60 for 2 minutes, then
ramps down. Peak concurrent load: 60 VUs from `amazon:gb:london`.

Sequential (not parallel) because the Grafana k6 project caps at 100 concurrent VUs — three parallel scenarios 
at 60 VUs each would breach the cap.

The test fails (exits non-zero) on:
- `http_req_failed > 1%` overall, or
- `http_req_duration` p(95) above 500ms (GETs) / 1500ms (login).

## One-time setup

Auth the k6 CLI to your Grafana Cloud k6 account once per machine:

```bash
k6 cloud login --token <YOUR_TOKEN>
```

## Running the test

```bash
k6 cloud run \
  -e CHATAPP_USER_EMAIL=sharyarahmed4567@gmail.com \
  -e CHATAPP_USER_PASSWORD='Thomas' \
  loadtest/k6.config.js
```


## Caveats

- This hits production. Cloud Run autoscales briefly; expect at most single-digit dollars of compute per run.
- OCI MySQL HeatWave Always Free is the likely bottleneck, if the test saturates it, all three endpoints slow together.
- All 60 login VUs share one account; per-user MySQL caching may make numbers look better than reality.
