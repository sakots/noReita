#!/usr/bin/env bash

set -euo pipefail

if ! command -v php >/dev/null 2>&1; then
  echo "php command not found" >&2
  exit 1
fi

php tests/smoke.php

