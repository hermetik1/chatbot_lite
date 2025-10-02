# Developer Guide

This document describes the development workflow for the Dual Chatbot Plugin.

## Structure

- PHP: WordPress plugin code in `dual-chatbot-plugin.php` and `includes/`.
- CSS: `assets/css/chatbot.css`.
- JS (legacy): `assets/js/chatbot.js` (IIFE).
- JS (modular, new): `assets/js/src/*` with `index.js` bundling the legacy script.

## Build (Assets)

Requirements: Node 18+

1. Install dev deps:
   - `npm install`
2. Build once:
   - `npm run build`
   - Outputs `assets/js/chatbot.min.js` and `assets/css/chatbot.min.css`.
3. Watch (dev):
   - `npm run build:watch`

The plugin automatically prefers minified assets if present; otherwise it falls back to the original files.

## Tests

### JS (Vitest)

- Run: `npm test`
- Located in `assets/js/src/*.test.js`.

### PHP (PHPUnit)

Requirements: PHP 8.1+, Composer

1. `composer install`
2. `composer test`

The PHP tests are lightweight and avoid a full WordPress bootstrap using polyfills and small stubs. Extend as needed.

### Static Analysis & Linting

- PHPStan: `composer phpstan` (level 6; WordPress stubs via `szepeviktor/phpstan-wordpress`).
- PHPCS: `composer lint` (WordPress Coding Standards). Configure via `phpcs.xml.dist`.
- ESLint: `npm run lint:js`.

## CI (GitHub Actions)

- Workflow `.github/workflows/ci.yml` runs on push/PR:
  - Composer install, PHPCS, PHPStan, PHPUnit
  - Node install, ESLint, Vitest

## API Versioning

Routes are registered via filterable namespace/version helpers:

- `Dual_Chatbot_Rest_API::public_base()` defaults to `chatbot/v1`.
- `Dual_Chatbot_Rest_API::internal_base()` defaults to `dual-chatbot/v1`.

Override via filters in a mu‑plugin or theme:

```
add_filter('dual_chatbot_api_version', fn() => 'v1');
add_filter('dual_chatbot_api_ns_public', fn() => 'chatbot');
add_filter('dual_chatbot_api_ns_internal', fn() => 'dual-chatbot');
```

`DualChatbotConfig.restUrl` and `analyticsRestUrl` are built from these helpers.

## Multi‑Server Support

- Rate‑Limit storage prefers `wp_cache_*` (shared object cache) and falls back to transients.
- Request IDs are sent on responses as `X-Request-Id` (and in stream meta payloads) for log correlation.
- Client/Server logs:
  - Use filter `dual_chatbot_log_destination` to select `error_log` instead of file writes.
  - Use filter `dual_chatbot_log_path` to customize the log file path.

## Performance Monitoring

- Server side: duration metrics are written into `dual_chatbot_analytics` with type `perf_server`.
- Client side: UI build time is tracked via analytics event `perf_ui`.
- Existing message latency metrics remain (`message_user` and `message_bot` with `latency_ms`).

## Streaming (NDJSON) Notes

- Server headers: `Content-Type: application/x-ndjson`, `Cache-Control: no-cache, no-transform`, `X-Accel-Buffering: no`, `Connection: keep-alive`.
- Output buffering: we flush all active buffers and enable implicit flush; the handler echoes each line + `\n` and `flush()`s it.
- Always terminates the request with `done` (and `error` + `done` on exceptions), then `exit` to avoid REST wrapping.
- Nginx/Apache config hints:
  - Nginx: `proxy_buffering off;` und ggf. `fastcgi_buffering off;` im Location/Upstream für die REST‑Route.
  - Apache (mod_proxy): `ProxyPass ... flushpackets=on` und `ProxyPassReverse ...` je nach Setup; sicherstellen, dass keine Puffer zwischengeschaltet sind.

## Coding & Style

- `.editorconfig` enforces consistent formatting (PHP 4 spaces; JS/CSS 2 spaces).
- Avoid inline scripts in admin; prefer server-rendered or enqueued files.

## Gradual Modularization Plan

1. Keep the existing `assets/js/chatbot.js` for now (behavior unchanged).
2. Extract utilities into `assets/js/src/` (config, logging, text helpers).
3. Incrementally move UI components and API calls into modules; keep `index.js` as the entrypoint.
4. Build minified bundle via esbuild; WordPress enqueues the bundle automatically if present.
