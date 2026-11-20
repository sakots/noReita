#!/usr/bin/env bash
set -euo pipefail

usage() {
  printf 'Usage: bash %s [--apply] KEY_NAME SOURCE_DIR HOST:/DEST/ [HOST:/DEST/ ...]\n' "$0"
  printf 'KEY_NAME is a private key in ~/.ssh/. Without --apply, preview changes only.\n'
}

if [[ ${1:-} == --help ]]; then usage; exit 0; fi
mode=(--dry-run)
if [[ ${1:-} == --apply ]]; then mode=(); shift; fi
if (( $# < 3 )); then usage >&2; exit 2; fi

key_name=$1
source_dir=$2
shift 2
key="$HOME/.ssh/$key_name"
if [[ ! $key_name =~ ^[a-zA-Z0-9_][a-zA-Z0-9_.-]*$ || $key == *\"* || $key == *$'\n'* || ! -f $key || ! -r $key ]]; then
  printf 'Specify a readable private key by filename in ~/.ssh/.\n' >&2
  exit 2
fi
if [[ ! -d $source_dir || ! -f $source_dir/index.php || ! -f $source_dir/vendor/autoload.php ]]; then
  printf 'Specify the extracted noReita application directory (with index.php and vendor/).\n' >&2
  exit 2
fi
source_dir=$(cd -- "$source_dir" && pwd -P)
for destination in "$@"; do
  if [[ ! $destination =~ ^([a-zA-Z0-9_][a-zA-Z0-9_.-]*@)?[a-zA-Z0-9_][a-zA-Z0-9_.-]*:/[^/[:cntrl:]][^[:cntrl:]]*$ \
    || ${destination#*:}/ == */../* || ${destination#*:}/ == */./* ]]; then
    printf 'Expected HOST:/absolute/path/ (not the remote root): %s\n' "$destination" >&2
    exit 2
  fi
done

ssh_command="ssh -o BatchMode=yes -o StrictHostKeyChecking=yes -o IdentitiesOnly=yes -i \"$key\""
filters=(--exclude='/config.local.php*' --exclude='/debug.local.php*' --exclude='*.db' --exclude='*.db-*')
for directory in img thumbnail tmp cache session backup errorlog auditlog; do
  filters+=(--include="/$directory/.htaccess" --exclude="/$directory/*")
done

failed=0
for destination in "$@"; do
  printf '\n==> %s\n' "$destination"
  if ! rsync -rt -s --itemize-changes "${mode[@]}" "${filters[@]}" \
    -e "$ssh_command" -- "$source_dir/" "$destination"; then
    printf 'Upload failed: %s\n' "$destination" >&2
    failed=1
  fi
done
exit "$failed"
