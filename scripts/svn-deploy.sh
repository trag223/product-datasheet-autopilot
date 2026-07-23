#!/usr/bin/env sh
set -eu

# Usage: SVN_URL=https://plugins.svn.wordpress.org/slug SVN_USERNAME=you ./scripts/svn-deploy.sh 1.0.0
# This stages the already-reviewed free package source. It never runs from CI
# without an explicitly protected release environment.
VERSION="${1:?Usage: svn-deploy.sh VERSION}"
ROOT="$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)"
WORK="${ROOT}/dist/svn-work"

test -n "${SVN_URL:-}" || { echo "SVN_URL is required" >&2; exit 2; }
test -n "${SVN_USERNAME:-}" || { echo "SVN_USERNAME is required" >&2; exit 2; }
rm -rf "$WORK"
svn checkout --username "$SVN_USERNAME" "$SVN_URL" "$WORK"
php "$ROOT/scripts/build.php" free
rsync -a --delete --exclude .svn "$ROOT/dist/build/free/product-datasheet-autopilot/" "$WORK/trunk/"
svn add --force "$WORK/trunk" "$WORK/assets"
svn copy "$WORK/trunk" "$WORK/tags/$VERSION" -m "Release $VERSION"
svn commit "$WORK" -m "Release Product Datasheet Autopilot $VERSION"
