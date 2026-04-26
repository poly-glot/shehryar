# k6 Cloud spike test for shehryar.dev/chatapp Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build a manually-runnable k6 Cloud spike test that hits three production
`/chatapp` endpoints (`getAllUsers.php`, `getUserByEmail.php`, `login.php`)
with 60 concurrent VUs each in parallel for ~3 minutes from London.

**Architecture:** A single k6 JavaScript test script under `loadtest/` that
defines three parallel scenarios — one per endpoint — sharing a common ramp
shape and threshold definitions. Cloud configuration (project ID, load
zone) is baked into the script. Test credentials for the `login.php`
endpoint flow in via env vars; the k6 Cloud auth token is provisioned
once via `k6 cloud login` and never enters the repo. There is no CI
integration; the test is invoked manually from a developer machine.

**Tech Stack:** k6 (Grafana), Grafana Cloud k6 (project `7298441`).

**Spec:** `docs/superpowers/specs/2026-04-26-k6-cloud-spike-test-design.md`

---

## File Structure

| Path                     | Responsibility                                                                       | New/Modified |
|--------------------------|--------------------------------------------------------------------------------------|--------------|
| `loadtest/k6.config.js`  | The complete test script: scenarios, ramp shape, thresholds, cloud options, checks. | New          |
| `loadtest/README.md`     | One-time setup, env vars, run command, dashboard pointer.                            | New          |
| `.gitignore`             | Append `k6.md` so the local cloud token doesn't get committed.                       | Modified     |

The test script is a single file because the surface is small (≈100 lines),
and splitting it across multiple modules would obscure the scenario layout
without buying anything.

---

## Task 1: Gitignore the k6 cloud token

**Files:**
- Modify: `.gitignore` (append one line)

- [ ] **Step 1: Append `k6.md` to `.gitignore`**

The file `k6.md` in the repo root contains the live k6 Cloud auth token
and should never be committed. Open `.gitignore` and append the following
line at the end:

```
k6.md
```

The full updated `.gitignore` should end with:

```
.ddev/**
!.ddev/config.yaml
dist/
firebase-debug.log
k6.md
```

- [ ] **Step 2: Verify `k6.md` is now ignored**

Run:

```bash
git check-ignore -v k6.md
```

Expected: prints `.gitignore:<line>:k6.md	k6.md` and exits 0. If it exits
1 without output, the ignore rule didn't take.

- [ ] **Step 3: Commit**

```bash
git add .gitignore
git commit -m "chore: gitignore k6 cloud token"
```

---

## Task 2: Write minimal script with shared config (no scenarios yet)

**Files:**
- Create: `loadtest/k6.config.js`

This task lays down the shared scaffolding — base URL, env-var validation,
shared scenario template, cloud options, thresholds — but leaves the
`scenarios` map empty. Subsequent tasks fill in one scenario at a time so
each can be validated independently.

- [ ] **Step 1: Create the `loadtest/` directory and the script with the scaffold**

```bash
mkdir -p loadtest
```

Then write the following exact content to `loadtest/k6.config.js`:

```js
import http from 'k6/http';
import { check } from 'k6';

const BASE = 'https://shehryar.dev/chatapp';

const CHATAPP_USER_EMAIL = __ENV.CHATAPP_USER_EMAIL;
const CHATAPP_USER_PASSWORD = __ENV.CHATAPP_USER_PASSWORD;

if (!CHATAPP_USER_EMAIL || !CHATAPP_USER_PASSWORD) {
  throw new Error(
    'Set CHATAPP_USER_EMAIL and CHATAPP_USER_PASSWORD env vars before running.'
  );
}

const STAGES = [
  { duration: '30s', target: 60 },
  { duration: '2m', target: 60 },
  { duration: '30s', target: 0 },
];

const baseScenario = {
  executor: 'ramping-vus',
  startVUs: 0,
  stages: STAGES,
  gracefulRampDown: '30s',
};

export const options = {
  cloud: {
    projectID: 7298441,
    name: 'shehryar.dev /chatapp spike',
    distribution: {
      london: { loadZone: 'amazon:eu:london', percent: 100 },
    },
  },
  scenarios: {
    // Filled in by subsequent tasks.
  },
  thresholds: {
    http_req_failed: ['rate<0.01'],
    'http_req_duration{endpoint:getAllUsers}': ['p(95)<500'],
    'http_req_duration{endpoint:getUserByEmail}': ['p(95)<500'],
    'http_req_duration{endpoint:login}': ['p(95)<1500'],
  },
};

const JSON_HEADERS = { 'Content-Type': 'application/json' };

function assertOk(name, res) {
  check(res, {
    [`${name}: status is 200`]: (r) => r.status === 200,
    [`${name}: body has results`]: (r) => {
      try {
        return 'results' in r.json();
      } catch (_) {
        return false;
      }
    },
  });
}
```

- [ ] **Step 2: Verify the env-var guard fires when unset**

Run:

```bash
unset CHATAPP_USER_EMAIL CHATAPP_USER_PASSWORD
k6 inspect loadtest/k6.config.js
```

Expected: exits non-zero with an error containing `Set CHATAPP_USER_EMAIL and CHATAPP_USER_PASSWORD env vars before running.`

This proves the guard runs at module load and will block accidental runs
without credentials.

- [ ] **Step 3: Verify the script parses with env vars set**

Run:

```bash
k6 inspect \
  -e CHATAPP_USER_EMAIL=sharyarahmed4567@gmail.com \
  -e CHATAPP_USER_PASSWORD=Thomas \
  loadtest/k6.config.js
```

(k6 v1.7+ does not auto-import shell env vars; use `-e KEY=VAL` flags.)

Expected: exits 0 and prints a JSON dump of `options` showing
`cloud.projectID: 7298441`, the threshold definitions, and an empty
`scenarios: {}` block.

- [ ] **Step 4: Commit**

```bash
git add loadtest/k6.config.js
git commit -m "feat(loadtest): scaffold k6 script with cloud config and thresholds"
```

---

## Task 3: Add `getAllUsers` scenario

**Files:**
- Modify: `loadtest/k6.config.js`

Adds the first scenario — a GET against `getAllUsers.php` with a per-request
cache-buster query param so Firebase's CDN doesn't shield the origin.

- [ ] **Step 1: Add the scenario entry to `options.scenarios`**

In `loadtest/k6.config.js`, replace the `scenarios:` block with:

```js
  scenarios: {
    getAllUsers: {
      ...baseScenario,
      exec: 'getAllUsers',
      tags: { endpoint: 'getAllUsers' },
    },
  },
```

The scenario-level `tags: { endpoint: 'getAllUsers' }` is what makes the
threshold selector `http_req_duration{endpoint:getAllUsers}` match.

- [ ] **Step 2: Add the exported scenario function**

Append to the end of `loadtest/k6.config.js`:

```js
export function getAllUsers() {
  const url = `${BASE}/getAllUsers.php?_=${__VU}-${__ITER}`;
  const res = http.get(url);
  assertOk('getAllUsers', res);
}
```

The `?_=${__VU}-${__ITER}` segment is unique per VU per iteration, so every
request has a distinct URL and the CDN serves none of them from cache.

- [ ] **Step 3: Sanity-check the endpoint by hand**

Run:

```bash
curl -sS -o /dev/null -w '%{http_code}\n' "https://shehryar.dev/chatapp/getAllUsers.php?_=local-smoke"
curl -sS "https://shehryar.dev/chatapp/getAllUsers.php?_=local-smoke" | head -c 200
```

Expected: first command prints `200`. Second command prints a JSON snippet
beginning with `{"results":[`. Confirms the endpoint and the cache-buster
query work.

- [ ] **Step 4: Verify the script still parses cleanly**

Run:

```bash
k6 inspect \
  -e CHATAPP_USER_EMAIL=sharyarahmed4567@gmail.com \
  -e CHATAPP_USER_PASSWORD=Thomas \
  loadtest/k6.config.js
```

Expected: exits 0 and the `scenarios` block in the dumped options now
contains a `getAllUsers` entry with `executor: ramping-vus`, the three
stages, and the `endpoint: getAllUsers` tag.

- [ ] **Step 5: Commit**

```bash
git add loadtest/k6.config.js
git commit -m "feat(loadtest): add getAllUsers scenario with CDN cache-buster"
```

---

## Task 4: Add `getUserByEmail` scenario

**Files:**
- Modify: `loadtest/k6.config.js`

- [ ] **Step 1: Add the scenario entry**

In `loadtest/k6.config.js`, extend `options.scenarios` to include
`getUserByEmail` alongside the existing `getAllUsers`:

```js
  scenarios: {
    getAllUsers: {
      ...baseScenario,
      exec: 'getAllUsers',
      tags: { endpoint: 'getAllUsers' },
    },
    getUserByEmail: {
      ...baseScenario,
      exec: 'getUserByEmail',
      tags: { endpoint: 'getUserByEmail' },
    },
  },
```

Both scenarios use the same `baseScenario` template, so they ramp and hold
in lockstep.

- [ ] **Step 2: Add the exported scenario function**

Append to the end of `loadtest/k6.config.js`:

```js
export function getUserByEmail() {
  const res = http.post(
    `${BASE}/getUserByEmail.php`,
    JSON.stringify({ email: 'sharyarahmed4567@gmail.com' }),
    { headers: JSON_HEADERS }
  );
  assertOk('getUserByEmail', res);
}
```

The lookup email is hardcoded — it's a known existing user (the same one
used for `login`), so every request returns the same row. The spec
explicitly accepts this: load shape uniformity is the goal, not
realistic data variety.

- [ ] **Step 3: Sanity-check the endpoint by hand**

Run:

```bash
curl -sS -X POST https://shehryar.dev/chatapp/getUserByEmail.php \
  -H 'Content-Type: application/json' \
  -d '{"email":"sharyarahmed4567@gmail.com"}' | head -c 200
```

Expected: prints a JSON snippet beginning with `{"results":{`. If you see
`404` or an error, the user doesn't exist or the endpoint is broken — fix
before proceeding.

- [ ] **Step 4: Verify the script still parses cleanly**

Run:

```bash
k6 inspect \
  -e CHATAPP_USER_EMAIL=sharyarahmed4567@gmail.com \
  -e CHATAPP_USER_PASSWORD=Thomas \
  loadtest/k6.config.js
```

Expected: exits 0 and the dumped `scenarios` block contains both
`getAllUsers` and `getUserByEmail`.

- [ ] **Step 5: Commit**

```bash
git add loadtest/k6.config.js
git commit -m "feat(loadtest): add getUserByEmail scenario"
```

---

## Task 5: Add `login` scenario

**Files:**
- Modify: `loadtest/k6.config.js`

The login scenario reads the credentials from the env vars validated in
Task 2 and POSTs them to `login.php`. Each VU re-authenticates as the
same shared test user every iteration.

- [ ] **Step 1: Add the scenario entry**

In `loadtest/k6.config.js`, extend `options.scenarios` so all three
scenarios are present:

```js
  scenarios: {
    getAllUsers: {
      ...baseScenario,
      exec: 'getAllUsers',
      tags: { endpoint: 'getAllUsers' },
    },
    getUserByEmail: {
      ...baseScenario,
      exec: 'getUserByEmail',
      tags: { endpoint: 'getUserByEmail' },
    },
    login: {
      ...baseScenario,
      exec: 'login',
      tags: { endpoint: 'login' },
    },
  },
```

- [ ] **Step 2: Add the exported scenario function**

Append to the end of `loadtest/k6.config.js`:

```js
export function login() {
  const res = http.post(
    `${BASE}/login.php`,
    JSON.stringify({
      Email: CHATAPP_USER_EMAIL,
      Password: CHATAPP_USER_PASSWORD,
    }),
    { headers: JSON_HEADERS }
  );
  assertOk('login', res);
}
```

The body uses the capitalised `Email` / `Password` keys — that's the
contract `login.php` expects (see `src/chatapp/login.php` and the README
smoke-test example). Lower-case keys would 401.

- [ ] **Step 3: Sanity-check the endpoint by hand**

Run:

```bash
curl -sS -X POST https://shehryar.dev/chatapp/login.php \
  -H 'Content-Type: application/json' \
  -d '{"Email":"sharyarahmed4567@gmail.com","Password":"Thomas"}' \
  | head -c 200
```

Expected: prints a JSON snippet containing `"results":{"Message":"success"`.
A 401 here means the credentials are wrong — stop and verify before
proceeding (k6 will measure the failure path otherwise).

- [ ] **Step 4: Verify the script parses with all three scenarios**

Run:

```bash
k6 inspect \
  -e CHATAPP_USER_EMAIL=sharyarahmed4567@gmail.com \
  -e CHATAPP_USER_PASSWORD=Thomas \
  loadtest/k6.config.js
```

Expected: exits 0 and the dumped `scenarios` block contains
`getAllUsers`, `getUserByEmail`, and `login`, each with the same `stages`
and the matching `endpoint` tag.

- [ ] **Step 5: Commit**

```bash
git add loadtest/k6.config.js
git commit -m "feat(loadtest): add login scenario with env-var creds"
```

---

## Task 6: Write the runbook

**Files:**
- Create: `loadtest/README.md`

A short operator-facing doc so anyone — including future-you — can run the
test without re-reading the spec.

- [ ] **Step 1: Create `loadtest/README.md`**

Write the following exact content to `loadtest/README.md`:

````markdown
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
`amazon:eu:london`.

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

(k6 v1.7+ does not auto-import shell env vars; use `-e KEY=VAL` flags.)

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
````

- [ ] **Step 2: Commit**

```bash
git add loadtest/README.md
git commit -m "docs(loadtest): runbook for the k6 cloud spike test"
```

---

## Task 7: End-to-end cloud run

**Files:** none modified.

This task verifies the assembled script actually executes end-to-end on
k6 Cloud against production. It uses real VUh from the Grafana Cloud k6
account and incurs real Cloud Run cost. Run during a low-traffic window.

- [ ] **Step 1: Confirm `k6 cloud login` has been done on this machine**

Run:

```bash
k6 cloud login
```

Expected: prints either `Logged in successfully` (already authed) or
prompts for a token. If it prompts, paste the token from `k6.md` and
re-run.

- [ ] **Step 2: Kick off the cloud run**

Run:

```bash
k6 cloud run \
  -e CHATAPP_USER_EMAIL=sharyarahmed4567@gmail.com \
  -e CHATAPP_USER_PASSWORD='Thomas' \
  loadtest/k6.config.js
```

Expected: the CLI prints a `https://...grafana.net/...` dashboard URL
within seconds, then streams progress (VU count, RPS, p95) for ~3
minutes. Open the URL in a browser to watch live charts.

- [ ] **Step 3: Verify the run completed and the thresholds were evaluated**

After the test finishes (~3.5 min), the CLI prints a summary table with
threshold pass/fail markers (`✓` or `✗`) for each metric defined in
`options.thresholds`.

Acceptance for this task is *not* "all thresholds pass" — that's a
property of the production system, not the test script. Acceptance is:

- The run reached the steady-state hold phase without errors in the k6
  runner itself (script crash, malformed cloud config, missing creds).
- The summary lists all four thresholds (`http_req_failed` plus the
  three per-endpoint p(95) metrics).
- Each scenario shows ≥ 1 VU iteration in the per-scenario summary.

If any of those is missing, the script is broken — go back and fix
before declaring done.

- [ ] **Step 4: Note the dashboard URL**

Record the Grafana Cloud k6 run URL printed in Step 2 — it's the
permanent home of the result and the artifact you'd share with anyone
asking "what did the spike look like."

No commit for this task; it's a verification step.

---

## Done criteria

- All seven tasks above are checked.
- `loadtest/k6.config.js` and `loadtest/README.md` exist and are committed.
- `k6.md` is in `.gitignore`.
- `git status` is clean.
- One successful end-to-end `k6 cloud run` has executed and a dashboard
  URL has been recorded.
