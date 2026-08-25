# TN Performance Advisor

Author: Techn

Version: 0.1.6

Status: MVP

## Purpose

TN Performance Advisor captures sanitised diagnostics from Query Monitor and uses the native WordPress 7 AI Client to produce a prioritised, evidence-based performance action plan.

## Key Features

- Adds **Settings > Performance Advisor** for approved users.
- Captures the latest front-end page or normal wp-admin screen viewed by an approved administrator.
- Uses Query Monitor for timing, query, HTTP, PHP error, asset, cache, and environment data.
- Removes query-string values, SQL values, request headers, bodies, cookies, credentials, and file paths before storage or transmission.
- Uses the OpenAI provider configured under **Settings > Connectors**.
- Requests foreground OpenAI responses with provider storage disabled.
- Requests strict structured output and renders explicit remediation and verification steps.
- Returns one highest-value evidence-backed improvement, or clearly reports **Optimised** when the supplied data supports no worthwhile change.
- Keeps measurement and diagnostic follow-ups separate from performance recommendations.
- Captures normal front-end and wp-admin HTML document requests, excluding REST and background traffic.
- Presents plain-language steps for non-technical WordPress site owners, with specialist work converted into a copy-and-paste developer or host request.
- Shows **What this means**, **What you should do**, and the expected improvement before technical evidence.
- Adds Analyse Performance throughout the front-end and wp-admin admin bar, including on the Performance Advisor page, and opens the results page after analysis.
- Preserves the exact capture used for each displayed analysis.
- Restricts captures, settings, and analysis actions to users with an `alphasys.com.au` or `techn.com.au` email address.
- Delivers updates from public GitHub releases through the native WordPress Plugins screen.

## Requirements

- WordPress 7.0 or later.
- PHP 7.4 or later.
- Query Monitor.
- AI Provider for OpenAI.
- An OpenAI API key configured under **Settings > Connectors**.

## Installation

1. Install and activate Query Monitor.
2. Install and activate AI Provider for OpenAI.
3. Upload and activate TN Performance Advisor.
4. Add the OpenAI API key under **Settings > Connectors**.
5. While logged in with an approved email address, visit the front-end page or wp-admin screen you want to assess.
6. Select **Analyse Performance** in the admin bar.
7. Review the recommendation or Optimised result on **Settings > Performance Advisor**.

## Folder Structure

```text
tn-performance-advisor/
├── tn-performance-advisor.php
├── readme.md
├── functions/
│   ├── admin.php
│   ├── ai.php
│   ├── assets.php
│   ├── github-updater.php
│   ├── helpers.php
│   └── query-monitor.php
├── styles/
│   └── tn-performance-advisor.css
└── templates/
    └── options-page.php
```

## Important Notes

- Analysis is manual and administrator-only.
- Feature access is limited to users whose email domain is exactly `alphasys.com.au` or `techn.com.au`.
- The plugin never reads or stores the OpenAI key itself.
- If Query Monitor, the WordPress AI Client, or AI Provider for OpenAI is unavailable, the plugin remains inactive without displaying an error or blocking WordPress.
- A capture represents one WordPress request, not aggregate production traffic or browser Core Web Vitals.
- Captures and recommendations are stored in the current administrator's user metadata until cleared or replaced.
- AI recommendations should be reviewed and tested on staging before production changes are made.

## Future Considerations

The MVP intentionally omits scheduled analysis, multi-page history, automatic code changes, and third-party analytics integrations.
