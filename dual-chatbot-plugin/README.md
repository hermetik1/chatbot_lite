# dual-chatbot-plugin
WordPress plugin with dual chatbot system (FAQ + member advisory)

## Updates

- Add `.editorconfig` for consistent formatting (PHP 4-space, JS/CSS 2-space).
- Extended error logging:
  - Enabling “Debug-Log” writes to `wp-content/uploads/chatbot-debug.log`.
  - Client errors (window errors/unhandled rejections) are captured and sent to REST `dual-chatbot/v1/client-log` when debug is enabled.
- Improved input validation:
  - New setting “Max message length” (default 2000 chars).
  - Client trims/clamps; server validates and rejects overlong inputs.

## Build System

- Uses esbuild to produce `assets/js/chatbot.min.js` and `assets/css/chatbot.min.css`.
- Commands: `npm install`, then `npm run build`.

## API Versioning (Prep)

- REST namespaces are filterable: defaults `chatbot/v1` and `dual-chatbot/v1`.
- Filters: `dual_chatbot_api_version`, `dual_chatbot_api_ns_public`, `dual_chatbot_api_ns_internal`.

## Multi‑Server Support

- Rate limiting uses shared object cache when available.
- `X-Request-Id` header for correlation in all responses (including streaming meta).
- Logging destination customizable via filters (`error_log` or file path).

## Performance Monitoring

- Server: inserts `perf_server` events (latency_ms) into analytics table.
- Client: sends `perf_ui` (widget build time) to analytics endpoint.

Note: Enable the debug log only temporarily in production.
