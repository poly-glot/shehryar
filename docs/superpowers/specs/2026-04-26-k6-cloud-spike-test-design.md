# k6 Cloud spike test for shehryar.dev/chatapp

**Date:** 2026-04-26
**Status:** Draft — pending user review

## Goal

Run a short, repeatable spike load test against three production endpoints
on `https://shehryar.dev/chatapp` from k6 Cloud. Capture per-endpoint
latency and error rate, with thresholds that fail the test on regression.

In scope: a single manually-invoked test definition.

Out of scope: CI integration, multi-region distribution, soak/endurance
runs, test-data setup/teardown automation, dashboard automation.

## Endpoints under test

| Scenario         | Method | URL                                                   | Body                                                                |
|------------------|--------|-------------------------------------------------------|---------------------------------------------------------------------|
| `getAllUsers`    | GET    | `/chatapp/getAllUsers.php?_=<vu>-<iter>`              | none — query param is a per-request cache-buster, built from k6's `__VU` and `__ITER` |
| `getUserByEmail` | POST   | `/chatapp/getUserByEmail.php`                         | `{"email":"sharyarahmed4567@gmail.com"}`                            |
| `login`          | POST   | `/chatapp/login.php`                                  | `{"Email": $CHATAPP_USER_EMAIL, "Password": $CHATAPP_USER_PASSWORD}` |

The cache-buster on `getAllUsers` is required because Firebase Hosting
sits in front of Cloud Run as a CDN and aggressively caches GETs. Without
it, 60 VUs would mostly hit the edge cache and origin would see ~nothing.

## Load shape

Three scenarios run **in parallel**. Each scenario uses the same ramp:

```
30s ramp 0 → 60 VUs
2 min hold at 60 VUs
30s ramp 60 → 0 VUs
```

Total wall-clock: ~3 minutes. Peak effective load: 180 concurrent VUs
(60 per endpoint × 3) hitting prod simultaneously.

Each scenario tags its requests with `endpoint:<name>` so the Grafana
dashboard breaks down latency/errors per endpoint.

## Per-request checks

Every request asserts:

- HTTP status is `200`
- Response body parses as JSON and contains a `results` key

Failed checks increment the `checks` metric (not `http_req_failed`, which
only counts HTTP-level failures). The thresholds below include both, so a
500/network failure OR a malformed-body assertion failure breaches the
test.

## Thresholds (test exits non-zero on breach)

| Metric                                                | Threshold     | Reason                                           |
|-------------------------------------------------------|---------------|--------------------------------------------------|
| `http_req_failed`                                     | `rate < 0.01` | HTTP-level failures (non-2xx, network errors); allow 1% transient |
| `checks`                                              | `rate > 0.99` | Application-level assertions (status 200 + JSON `results`) — catches HTTP 200 with a failure body |
| `http_req_duration{endpoint:getAllUsers}` p(95)       | `< 500ms`     | Simple SELECT, light payload                     |
| `http_req_duration{endpoint:getUserByEmail}` p(95)    | `< 500ms`     | Single-row indexed lookup                        |
| `http_req_duration{endpoint:login}` p(95)             | `< 1500ms`    | Looser — Argon2id verify is CPU-bound on the server |

## Configuration

### k6 Cloud project

Cloud runs are bound to the existing project `7298441` via
`options.cloud.projectID` in the script. The cloud token is provisioned
once per machine via `k6 cloud login --token <token>`; after that it
lives in `~/.config/loadimpact/config.json` and isn't referenced from
the repo.

### Load region

Single region: **`amazon:eu:london`**.

The Cloud Run service is hosted in London. Running load from the same
region eliminates ~80–100ms of transatlantic RTT that would otherwise
distort p(95) measurements (especially material for the GET thresholds
where it would consume ~20% of the latency budget).

### Test credentials

The chatapp `login.php` endpoint requires a real account with a known
password (Argon2id verify cannot be tested with fake creds without
measuring only the failure path). A single shared user is used for all
60 login VUs:

- Email: `sharyarahmed4567@gmail.com`
- Password: `Thomas`

These are passed in via env vars and never committed:

- `CHATAPP_USER_EMAIL`
- `CHATAPP_USER_PASSWORD`

The script throws on startup if either is unset.

## Files added

| Path                       | Purpose                                                                       |
|----------------------------|-------------------------------------------------------------------------------|
| `loadtest/k6.config.js`    | The k6 test script (scenarios, thresholds, cloud options).                    |
| `loadtest/README.md`       | One-time `k6 cloud login`, env vars, run command, dashboard link.             |
| `.gitignore` (append)      | Add `k6.md` so the local cloud token doesn't get committed.                   |

No changes to `src/`, `db/`, `docker/`, or CI workflows.

## How to run

One-time:

```bash
k6 cloud login --token <token from k6.md>
```

Each run:

```bash
k6 cloud run \
  -e CHATAPP_USER_EMAIL=sharyarahmed4567@gmail.com \
  -e CHATAPP_USER_PASSWORD='Thomas' \
  loadtest/k6.config.js
```

Results stream to the Grafana k6 Cloud dashboard for project `7298441`.
The CLI prints the run URL on start.

## Risks and considerations

- **Cloud Run autoscaling cost.** A 3-minute spike at 60 VUs against a
  CPU-heavy endpoint (login) will scale instances up briefly. Cost is
  expected to be small (single-digit dollars at most for one run) but
  worth flagging before each invocation.
- **OCI MySQL HeatWave Always Free is the likely bottleneck.** The
  database tier has fixed CPU/memory and is not elastic. If the test
  saturates it, all three endpoints will slow together regardless of
  their individual capacity.
- **Single test user.** All 60 login VUs authenticate as the same
  account. If MySQL caches per-user state, results will look better than
  reality. Acceptable for a first run; revisit if numbers look suspicious.
- **Origin will see real load.** This is a production test. Run
  during low-traffic windows where possible.

## Open follow-ups (not in this spec)

- Wire the test into CI (e.g. nightly run, post-deploy smoke).
- Seed a dedicated `loadtest@` user pool to remove the single-user
  caveat.
- Add a soak variant for catching MySQL connection-pool leaks.
