# TN Performance Advisor

TN Performance Advisor turns a sanitised Query Monitor capture into a prioritised performance action plan using the native WordPress 7 AI Client and OpenAI connector.

## Requirements

- WordPress 7.0 or later
- PHP 7.4 or later
- Query Monitor
- AI Provider for OpenAI
- OpenAI configured under **Settings > Connectors**

## Installation

Download `tn-performance-advisor.zip` from the latest release and upload it through **Plugins > Add New > Upload Plugin**.

Missing integrations do not block activation. Performance Advisor remains dormant until Query Monitor, the WordPress AI Client, and AI Provider for OpenAI are available.

## Usage

1. Configure OpenAI under **Settings > Connectors**.
2. While logged in with an `alphasys.com.au` or `techn.com.au` email address, visit the front-end page or wp-admin screen to assess.
3. Select **Analyse Performance** from the admin bar while viewing the target screen.
4. Review the assigned technical work order, exact change, expected improvement, implementation steps, verification, and rollback under **Settings > Performance Advisor**.
5. Select **Next recommendation** to request the next distinct improvement from the same capture. Previously shown recommendations are excluded from later responses.

The Analyse Performance action remains available on the Performance Advisor page and analyses the most recently captured eligible request.

## Updates

The active plugin exposes **GitHub** and **Check for updates** links on the WordPress Plugins screen. Releases are discovered from `update.json` first, with GitHub release fallbacks.

## Privacy

The plugin excludes request headers, bodies, cookies, credentials, file paths, query-string values, and SQL values from AI input. OpenAI responses are requested with provider storage disabled.
