/* global __ENV, __VU, __ITER */
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
      london: { loadZone: 'amazon:gb:london', percent: 100 },
    },
  },
  // Drop `url` from the default system tags. The getAllUsers cache-buster
  // makes every URL unique, which would explode time-series cardinality
  // (~5k unique URLs × 10 HTTP metrics = >40k series, breaching the cloud
  // project's 40k limit). Metrics still group by the explicit `name` tag.
  systemTags: [
    'proto',
    'subproto',
    'status',
    'method',
    'name',
    'group',
    'check',
    'error',
    'error_code',
    'tls_version',
    'scenario',
    'service',
    'expected_response',
  ],
  scenarios: {
    getAllUsers: {
      ...baseScenario,
      startTime: '0s',
      exec: 'getAllUsers',
      tags: { endpoint: 'getAllUsers' },
    },
    getUserByEmail: {
      ...baseScenario,
      startTime: '4m',
      exec: 'getUserByEmail',
      tags: { endpoint: 'getUserByEmail' },
    },
    login: {
      ...baseScenario,
      startTime: '8m',
      exec: 'login',
      tags: { endpoint: 'login' },
    },
  },
  thresholds: {
    http_req_failed: ['rate<0.01'],
    checks: ['rate>0.99'],
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

export function getAllUsers() {
  const url = `${BASE}/getAllUsers.php?_=${__VU}-${__ITER}`;
  const res = http.get(url, { tags: { name: '/chatapp/getAllUsers.php' } });
  assertOk('getAllUsers', res);
}

export function getUserByEmail() {
  const res = http.post(
    `${BASE}/getUserByEmail.php`,
    JSON.stringify({ email: 'sharyarahmed4567@gmail.com' }),
    { headers: JSON_HEADERS }
  );
  assertOk('getUserByEmail', res);
}

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
