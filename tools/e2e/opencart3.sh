#!/usr/bin/env bash
set -Eeuo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=tools/e2e/shared.sh
source "$SCRIPT_DIR/shared.sh"

e2e_main "3" "3.0.5.0"
