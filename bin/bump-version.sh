#!/usr/bin/env bash
# Bump the plugin version so WordPress sites see an update after you push to main.
# Usage: bin/bump-version.sh [patch|minor|major|X.Y.Z]   (default: patch)
set -euo pipefail
cd "$(dirname "$0")/.."
current=$(sed -n 's/^ \* Version:[[:space:]]*\([0-9.]*\).*/\1/p' college-placements.php)
IFS=. read -r major minor patch <<<"$current"
case "${1:-patch}" in
  patch) new="$major.$minor.$((patch + 1))" ;;
  minor) new="$major.$((minor + 1)).0" ;;
  major) new="$((major + 1)).0.0" ;;
  *) new="$1" ;;
esac
sed -i.bak -E "s/^( \* Version:[[:space:]]*)[0-9.]+/\1$new/" college-placements.php
sed -i.bak -E "s/define\( 'CPL_VERSION', '[0-9.]+' \)/define( 'CPL_VERSION', '$new' )/" college-placements.php
sed -i.bak -E "s/^(Stable tag:[[:space:]]*)[0-9.]+/\1$new/" readme.txt
rm -f college-placements.php.bak readme.txt.bak
echo "Version $current -> $new"
