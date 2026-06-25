#!/usr/bin/env bash
#
# build-release.sh — Build a clean, distributable Suite DevTools plugin zip.
#
# Exports the committed plugin (HEAD) into:
#   releases/suite-devtools.zip            (named after the slug — for WordPress.org)
#   releases/suite-devtools-<version>.zip  (versioned copy for your own archive)
#
# The root folder inside the zip is the slug (suite-devtools/). The archive is
# built with the standard `zip` tool from a clean staging copy, so it is a plain,
# widely-compatible zip (no VCS metadata, no macOS resource forks/__MACOSX).
#
# The Comment Pins block ships BOTH its compiled build/ and its source src/
# (+ package.json / build config) so the package is self-contained and meets the
# WordPress.org "source of compiled files must be available" requirement.
#
# Usage (from anywhere):
#   ./build-release.sh
#
set -euo pipefail

PLUGIN_SLUG="suite-devtools"
MAIN_FILE="suite-devtools.php"

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

# Read the version from the plugin header (e.g. "Version: 2.2.0").
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
OUT_SUBMIT="$RELEASES_DIR/${PLUGIN_SLUG}.zip"
OUT_VERSIONED="$RELEASES_DIR/${PLUGIN_SLUG}-${VERSION}.zip"
mkdir -p "$RELEASES_DIR"
rm -f "$OUT_SUBMIT" "$OUT_VERSIONED"

# Stage a clean export of HEAD (tracked files only — no .git, no untracked junk
# such as node_modules, which is gitignored and therefore absent from HEAD).
STAGING="$(mktemp -d)"
trap 'rm -rf "$STAGING"' EXIT
git archive --format=tar --prefix="${PLUGIN_SLUG}/" HEAD | tar -x -C "$STAGING"

# Remove only VCS / build-tooling files that should never ship. Keep the
# comment-pins block SOURCE (src/, package.json, build config) for compliance.
ROOT="$STAGING/$PLUGIN_SLUG"
rm -f "$ROOT/.gitignore" \
      "$ROOT/build-release.sh" \
      "$ROOT/comment-pins/.gitignore"

# Build a plain, standard zip (-X drops extra macOS attributes; -r recurses).
( cd "$STAGING" && zip -r -X "$OUT_SUBMIT" "$PLUGIN_SLUG" >/dev/null )
cp "$OUT_SUBMIT" "$OUT_VERSIONED"

echo "✓ WordPress.org ZIP: ${OUT_SUBMIT} ($(du -h "$OUT_SUBMIT" | cut -f1 | tr -d '[:space:]'))"
echo "✓ Versioned copy:    ${OUT_VERSIONED}"
