#!/usr/bin/env bash
# Download the latest noReita GitHub Release and update an existing installation.
# Usage: ./update-from-github.sh [path/to/noreita]

set -euo pipefail

REPOSITORY='sakots/noReita'
TARGET_DIRECTORY="${1:-"$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)/noreita"}"

require_command() {
  command -v "$1" >/dev/null 2>&1 || {
    printf 'Required command is not available: %s\n' "$1" >&2
    exit 1
  }
}

for command in curl php unzip tar; do require_command "$command"; done

if [ ! -f "$TARGET_DIRECTORY/index.php" ]; then
  printf 'noReita installation was not found: %s\n' "$TARGET_DIRECTORY" >&2
  printf 'Pass the directory containing index.php as the first argument.\n' >&2
  exit 1
fi

WORK_DIRECTORY="$(mktemp -d "${TMPDIR:-/tmp}/noreita-update.XXXXXX")"
cleanup() { rm -rf -- "$WORK_DIRECTORY"; }
trap cleanup EXIT HUP INT TERM

METADATA="$WORK_DIRECTORY/release.json"
curl --fail --location --silent --show-error \
  --header 'Accept: application/vnd.github+json' \
  --header 'User-Agent: noReita-release-updater' \
  "https://api.github.com/repos/$REPOSITORY/releases/latest" > "$METADATA"

RELEASE_DATA="$({
  php -r '
    $release = json_decode(file_get_contents($argv[1]), true, 512, JSON_THROW_ON_ERROR);
    foreach ($release["assets"] ?? [] as $asset) {
      if (is_array($asset) && isset($asset["name"], $asset["browser_download_url"], $asset["digest"])
        && is_string($asset["name"]) && str_ends_with($asset["name"], ".zip")) {
        echo ($release["tag_name"] ?? "unknown") . "|" . $asset["browser_download_url"] . "|" . $asset["digest"];
        exit(0);
      }
    }
    fwrite(STDERR, "The latest release does not contain a ZIP asset.\n");
    exit(2);
  ' "$METADATA"
})"
IFS='|' read -r RELEASE_TAG DOWNLOAD_URL RELEASE_DIGEST <<EOF
$RELEASE_DATA
EOF

if [ -z "$DOWNLOAD_URL" ] || [ "${RELEASE_DIGEST#sha256:}" = "$RELEASE_DIGEST" ]; then
  printf 'The release metadata is incomplete.\n' >&2
  exit 1
fi

ARCHIVE="$WORK_DIRECTORY/noreita.zip"
printf 'Downloading noReita %s…\n' "$RELEASE_TAG"
curl --fail --location --silent --show-error --output "$ARCHIVE" "$DOWNLOAD_URL"

ACTUAL_DIGEST="$(php -r 'echo hash_file("sha256", $argv[1]);' "$ARCHIVE")"
EXPECTED_DIGEST="${RELEASE_DIGEST#sha256:}"
if [ "$ACTUAL_DIGEST" != "$EXPECTED_DIGEST" ]; then
  printf 'SHA-256 verification failed. The archive was not installed.\n' >&2
  exit 1
fi

EXTRACT_DIRECTORY="$WORK_DIRECTORY/extracted"
mkdir "$EXTRACT_DIRECTORY"
unzip -q "$ARCHIVE" -d "$EXTRACT_DIRECTORY"
SOURCE_INDEX="$(find "$EXTRACT_DIRECTORY" -type f -path '*/noreita/index.php' -print -quit)"
if [ -z "$SOURCE_INDEX" ]; then
  printf 'The release archive does not contain noreita/index.php.\n' >&2
  exit 1
fi
SOURCE_DIRECTORY="${SOURCE_INDEX%/index.php}"

# Do not delete destination files. Runtime data and local configuration remain untouched.
tar -C "$SOURCE_DIRECTORY" \
  --exclude='./config.local.php' --exclude='./*.db' --exclude='./*.db-*' \
  --exclude='./img' --exclude='./thumbnail' --exclude='./temp' --exclude='./session' \
  --exclude='./cache' --exclude='./backup' --exclude='./errorlog' --exclude='./auditlog' \
  -cf - . | tar -C "$TARGET_DIRECTORY" -xf -

printf 'Updated %s to %s. Local configuration and runtime data were preserved.\n' "$TARGET_DIRECTORY" "$RELEASE_TAG"
