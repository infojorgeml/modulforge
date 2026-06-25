#!/usr/bin/env bash
#
# build-release.sh — Build a clean, distributable DevTools plugin zip.
#
# Exports the committed plugin (HEAD) into releases/dev-tools-<version>.zip with
# a dev-tools/ root folder and without development-only files. The archive is
# built with the standard `zip` tool from a clean staging copy, so it is a plain,
# widely-compatible zip (no VCS metadata, no macOS resource forks/__MACOSX).
#
# Usage (from anywhere):
#   ./build-release.sh
#
set -euo pipefail

PLUGIN_SLUG="dev-tools"
MAIN_FILE="dev-tools.php"

# Resolve the plugin directory (where this script lives) so it works from any cwd.
PLUGIN_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$PLUGIN_DIR"

# Requirements.
if ! git rev-parse --is-inside-work-tree >/dev/null 2>&1; then
    echo "Error: $PLUGIN_DIR is not a git repository." >&2
    exit 1
fi
if ! command -v zip >/dev/null 2>&1; then
    echo "Error: the 'zip' command is required but was not found." >&2
    exit 1
fi

# Read the version from the plugin header (e.g. "Version: 2.1.3").
VERSION="$(grep -m1 -iE '^[[:space:]]*Version:' "$MAIN_FILE" | sed -E 's/.*[Vv]ersion:[[:space:]]*//' | tr -d '[:space:]\r')"
if [ -z "$VERSION" ]; then
    echo "Error: could not read the Version header from $MAIN_FILE." >&2
    exit 1
fi

# The zip is built from the last commit (HEAD), so warn about uncommitted work.
if [ -n "$(git status --porcelain)" ]; then
    echo "Warning: uncommitted changes detected — the zip is built from the last commit (HEAD), not your working tree."
fi

RELEASES_DIR="$PLUGIN_DIR/releases"
OUT="$RELEASES_DIR/${PLUGIN_SLUG}-${VERSION}.zip"
mkdir -p "$RELEASES_DIR"
rm -f "$OUT"

# Stage a clean export of HEAD (tracked files only — no .git, no untracked junk).
STAGING="$(mktemp -d)"
trap 'rm -rf "$STAGING"' EXIT
git archive --format=tar --prefix="${PLUGIN_SLUG}/" HEAD | tar -x -C "$STAGING"

# Remove tracked-but-dev-only files that are not needed at runtime.
ROOT="$STAGING/$PLUGIN_SLUG"
rm -f  "$ROOT/.gitignore" \
       "$ROOT/build-release.sh" \
       "$ROOT/comment-pins/.gitignore" \
       "$ROOT/comment-pins/package.json" \
       "$ROOT/comment-pins/package-lock.json" \
       "$ROOT/comment-pins/eslint.config.cjs"
rm -rf "$ROOT/comment-pins/src"

# Build a plain, standard zip (-X drops extra macOS attributes; -r recurses).
( cd "$STAGING" && zip -r -X "$OUT" "$PLUGIN_SLUG" >/dev/null )

echo "✓ Built: ${OUT} ($(du -h "$OUT" | cut -f1 | tr -d '[:space:]'))"
