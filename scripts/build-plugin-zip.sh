#!/usr/bin/env bash
set -euo pipefail

PLUGIN_SLUG="tn-performance-advisor"
DIST_DIR="dist"

mkdir -p "$DIST_DIR"

if [[ -d "$DIST_DIR/$PLUGIN_SLUG" ]]; then
	find "$DIST_DIR/$PLUGIN_SLUG" -depth -delete
fi

if [[ -f "$DIST_DIR/$PLUGIN_SLUG.zip" ]]; then
	find "$DIST_DIR" -maxdepth 1 -type f -name "$PLUGIN_SLUG.zip" -delete
fi

if [[ -f "$PLUGIN_SLUG.zip" ]]; then
	find . -maxdepth 1 -type f -name "$PLUGIN_SLUG.zip" -delete
fi

cp -R "$PLUGIN_SLUG" "$DIST_DIR/$PLUGIN_SLUG"
find "$DIST_DIR/$PLUGIN_SLUG" -name ".DS_Store" -delete
find "$DIST_DIR/$PLUGIN_SLUG" -type d -name "node_modules" -prune -exec find {} -depth -delete \;

(
	cd "$DIST_DIR"
	zip -qr "$PLUGIN_SLUG.zip" "$PLUGIN_SLUG"
)

cp "$DIST_DIR/$PLUGIN_SLUG.zip" "$PLUGIN_SLUG.zip"
