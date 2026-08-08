#!/usr/bin/env bash
# Canonical copy — apps/*/deploy/docker/deploy-update.sh should match.
# Pull monorepo + rebuild one Bproo app stack.
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# When run from deployment/docker, APP_ROOT is not meaningful; prefer apps/*/ copy.
echo "Use apps/<app>/deploy/docker/deploy-update.sh instead of this file."
echo "Example: cd apps/erp && TENANT_CODE=demo bash deploy/docker/deploy-update.sh"
exit 1
