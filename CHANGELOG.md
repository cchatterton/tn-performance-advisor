# Changelog

All notable changes to TN Performance Advisor are recorded here.

## 0.1.9 - 2026-08-26

- Adds a Next recommendation button that recalls OpenAI using the same saved Query Monitor capture.
- Stores a compact history of recommendations already shown for that capture and excludes them from later responses.
- Numbers sequential recommendations and finishes with Optimised when no further distinct, evidence-backed improvement remains.

## 0.1.8 - 2026-08-26

- Replaces the obsolete front-end-only no-capture warning with instructions for the current front-end and wp-admin admin-bar workflow.
- Clarifies that older captures invalidated by an update must be replaced by visiting the target screen and selecting Analyse Performance.

## 0.1.7 - 2026-08-26

- Recasts each recommendation as a ready-to-execute work order assigned to a developer, systems administrator, host, or WordPress administrator.
- Requires an exact change, direct implementation steps, verification steps, and rollback steps instead of asking the implementer to create their own plan.
- Captures the responsible Query Monitor component and caller for remote requests when available.
- Rejects handoff and investigation language as implementation steps.

## 0.1.6 - 2026-08-26

- Shows Analyse Performance throughout wp-admin, including on the Performance Advisor results page.
- Adds plain-language What this means, What you should do, and Expected improvement sections before technical evidence.
- Prevents the advisor from claiming repeated remote requests are duplicates when the sanitised capture does not prove it.
- Invalidates older result shapes so existing unclear recommendations are not retained after updating.

## 0.1.5 - 2026-08-26

- Added capture and admin-bar analysis support for normal wp-admin screens.
- Continued to exclude REST, JSON, AJAX, cron, feeds, embeds, trackbacks, admin-post actions, and the Performance Advisor results screen.
- Restricted Performance Advisor menu visibility, captures, and analysis actions to users with an alphasys.com.au or techn.com.au email domain.
- Returns one concrete, evidence-backed performance improvement, or Optimised when the supplied capture supports no worthwhile change.
- Keeps measurement, tracing, and investigation suggestions out of the recommendation and in the separate next-capture note.

## 0.1.4 - 2026-08-25

- Moved Analyse Performance from the settings page to the front-end WordPress admin bar.
- Continued to direct administrators to Settings > Performance Advisor after analysis.
- Stored the exact sanitised capture with its analysis so the results page cannot report success while showing no capture.

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
