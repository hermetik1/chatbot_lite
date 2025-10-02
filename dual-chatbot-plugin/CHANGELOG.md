# Changelog

All notable changes to this project will be documented in this file.

The format is based on Keep a Changelog and adheres to Semantic Versioning.

## Unreleased
- Add Rollup bundling + packaging pipeline
- Add PostCSS + cssnano CSS minification
- Add GitHub Action to build and verify ZIP on tags
- Harden REST routes and client parsing

## x.y.z — Security & Compliance
- Enforced prepared statements (WPCS + CI gate).
- Added analytics opt-in gate; no writes when disabled.
- Retention cleanup now transactional with error logging.
- Added safe helpers for LIKE/ORDER BY/IN.
- A11y: focus-visible rings across all interactive elements.
- i18n: lazy translations with context for settings tabs; German PO/MO included.
