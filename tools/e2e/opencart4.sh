#!/usr/bin/env bash
set -Eeuo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=tools/e2e/shared.sh
source "$SCRIPT_DIR/shared.sh"

e2e_main "4" "4.1.0.3"
