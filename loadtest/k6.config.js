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

// Placeholder — replaced by scenario default exports in subsequent tasks.
export default function () {}

