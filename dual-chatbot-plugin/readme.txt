=== Dual Chatbot Plugin ===
Contributors: ki-kraft
Requires at least: 6.8
Tested up to: 6.8
Requires PHP: 8.1
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

== Description ==
WordPress plugin providing a dual chatbot (FAQ + member advisory) with server-side RAG and OpenAI integration.

== Privacy ==
- Opt-in analytics: Disabled by default. When disabled, no analytics rows are written.
- DSAR: Admin tools to export/delete a user’s data at any time.
- Retention: Configurable days (>=7). Daily cron purges older records transactionally.

== Security ==
- Prepared SQL throughout; WPCS enforced in CI.
- Public routes are rate-limited; all write endpoints nonce/capability protected.
- Nonces/capabilities hardened across admin and REST.

== Accessibility ==
- Visible focus indicators across all interactive elements.

== Installation ==
1. Upload and activate the plugin.
2. Configure under Settings → Dual Chatbot.

== Changelog ==
See CHANGELOG.md in the repository for detailed release notes.

