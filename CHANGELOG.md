# Changelog

All notable changes to TN Performance Advisor are recorded here.

## 0.1.3 - 2026-08-25

- Limited each analysis to exactly one highest-value recommendation.
- Prevented REST, JSON, feed, embed, trackback, and non-HTML background requests from replacing the intended page capture.
- Invalidated older captures and recommendation sets so stale REST analyses are not displayed after updating.
- Rewrote the AI instructions for non-technical site owners with exact menu paths, plain language, and copy-and-paste briefs when developer or host assistance is required.

## 0.1.2 - 2026-08-25

- Added native WordPress updates from public GitHub releases.
- Added GitHub and nonce-protected Check for updates plugin-row links.
- Added manifest-first release discovery with redirect and API fallbacks.
- Kept the updater active when performance integrations are unavailable.
- Made missing Query Monitor or AI integrations fail silently.

## 0.1.1 - 2026-08-25

- Removed hard WordPress plugin dependencies so missing integrations no longer block activation.

## 0.1.0 - 2026-08-25

- Added the initial Performance Advisor settings page, Query Monitor capture, sanitisation, and native WordPress AI Client analysis.
