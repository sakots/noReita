#!/usr/bin/env bash

set -euo pipefail

if ! command -v php >/dev/null 2>&1; then
  echo "php command not found" >&2
  exit 1
fi

mapfile -d '' php_candidates < <(git ls-files -z --cached --others --exclude-standard -- '*.php')
php_files=()
for php_file in "${php_candidates[@]}"; do
  [[ -f "$php_file" ]] && php_files+=("$php_file")
done

if ((${#php_files[@]} == 0)); then
  echo "No tracked PHP files found." >&2
  exit 1
fi

echo "Linting ${#php_files[@]} PHP files with PHP $(php -r 'echo PHP_VERSION;')"

for php_file in "${php_files[@]}"; do
  php -l "$php_file"
done
