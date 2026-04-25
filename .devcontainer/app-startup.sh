#!/usr/bin/env bash
# Container PID-1 script for the dev `app` service. Runs every container
# start, regardless of IDE (IntelliJ, VS Code, plain `docker compose up`).
#
# Order of operations:
#   1. Wait for mysql to accept connections.
#   2. Apply pending migrations (idempotent — script no-ops if applied).
#   3. Start the PHP dev server in the background on :8080.
#   4. exec sleep infinity so the IDE can attach and the container stays up.
#
# IDE-driven user setup (aliases, claude symlink, git config) lives in
# post-create.sh and runs on first attach.

set -u

echo "Waiting for mysql ..."
until mysqladmin ping -hmysql -ushehryar -pshehryar --silent 2>/dev/null; do
    sleep 1
done

echo "Running migrations ..."
php /workspace/bin/migrate.php || echo "Migration failed — continuing so the container stays up."

echo "Starting PHP dev server on :8080 (logs: /tmp/serve.log) ..."
php -S 0.0.0.0:8080 -t /workspace/src > /tmp/serve.log 2>&1 &

exec sleep infinity
