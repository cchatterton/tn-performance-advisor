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
2. While logged in as an administrator, visit the front-end page to assess.
3. Open **Settings > Performance Advisor**.
4. Select **Analyse Performance**.

## Updates

The active plugin exposes **GitHub** and **Check for updates** links on the WordPress Plugins screen. Releases are discovered from `update.json` first, with GitHub release fallbacks.

## Privacy

The plugin excludes request headers, bodies, cookies, credentials, file paths, query-string values, and SQL values from AI input. OpenAI responses are requested with provider storage disabled.
