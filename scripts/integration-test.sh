#!/usr/bin/env bash

set -euo pipefail

PHP_BIN="${PHP_BIN:-php}"

if ! command -v "$PHP_BIN" >/dev/null 2>&1; then
  echo "PHP command not found: ${PHP_BIN}" >&2
  exit 1
fi

"$PHP_BIN" tests/integration.php
