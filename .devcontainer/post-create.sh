#!/usr/bin/env bash
set -euo pipefail

echo "=== Shehryar Dev Container Setup ==="

# ============================================================
# Claude Code config — symlink .claude.json from persisted volume
# so login + memory survive rebuilds.
# ============================================================
if [ -f ~/.claude/.claude.json ] && [ ! -e ~/.claude.json ]; then
    ln -s ~/.claude/.claude.json ~/.claude.json
fi

# ============================================================
# Git
# ============================================================
git config --global --add safe.directory /workspace
git config --global init.defaultBranch main
git config --global core.autocrlf input

# Note: mysql wait + migrations + PHP dev server are now started by
# .devcontainer/app-startup.sh as the container's PID-1 command, so they
# run on every container start regardless of which IDE attached.

# ============================================================
# Shell aliases (guarded so re-runs don't duplicate the block)
# ============================================================
if ! grep -q '# >>> shehryar aliases >>>' ~/.zshrc 2>/dev/null; then
cat >> ~/.zshrc << 'ALIASES'

# >>> shehryar aliases >>>
# Claude
alias claude="claude --dangerously-skip-permissions"

# PHP dev server — serves src/ on :8080, matches the prod URL layout.
alias serve="php -S 0.0.0.0:8080 -t /workspace/src"

# MySQL shell against the devcontainer mysql service.
alias db="mysql -hmysql -ushehryar -pshehryar rn_chatapp"
alias db-root="mysql -hmysql -uroot -proot"

# Apply pending DB migrations against the dev mysql.
alias migrate="php /workspace/bin/migrate.php"

# Rebuild + run the production Cloud Run image locally against the dev mysql.
alias prod-build="(cd /workspace && docker build --target prod -t shehryar-api .)"
alias prod-run="docker run --rm -p 8081:8080 --network shehryar_devcontainer_default \
  -e DB_HOST=mysql -e DB_USER=shehryar -e DB_PASS=shehryar -e DB_NAME=rn_chatapp \
  shehryar-api"

# Git shortcuts
alias gs="git status"
alias gd="git diff"
alias gl="git log --oneline -20"
# <<< shehryar aliases <<<

ALIASES
fi

[ -f ~/.bashrc ] && ! grep -q 'exec zsh' ~/.bashrc && echo '[ -t 1 ] && exec zsh' >> ~/.bashrc

# ============================================================
# Verify
# ============================================================
echo ""
echo "=== Installed ==="
php --version | head -1
mysql --version
git --version
claude --version 2>/dev/null || echo "claude: installed"

echo ""
echo "=== Setup complete ==="
echo ""
echo "PHP dev server is running on :8080 (logs: /tmp/serve.log)."
echo ""
echo "Quick start:"
echo "  serve            -> Re-start PHP dev server on :8080 if needed"
echo "  db               -> MySQL shell on rn_chatapp"
echo "  migrate          -> Apply pending db/migrations/*.sql"
echo "  claude           -> Claude Code"
echo "  prod-build       -> Build the real Cloud Run image"
echo "  prod-run         -> Run it on :8081 against the dev mysql"
