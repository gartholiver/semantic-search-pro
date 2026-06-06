# Premium WordPress Semantic Search Plugin Development Plan

## Summary
Build a SaaS-backed WordPress plugin for content-heavy sites that replaces or supplements keyword search with semantic search. The WordPress plugin stays lightweight: it handles setup, content sync, admin controls, blocks/shortcodes, REST endpoints, and fallback behavior. A hosted backend handles embeddings, vector storage, usage metering, licensing, billing enforcement, and analytics.

Default product shape: freemium/lite WordPress plugin plus paid hosted subscription tiers. This fits WordPress.org rules because paid service functionality is allowed when plugin code is available, while premium-only code can be shipped outside WordPress.org if needed.

## Architecture
- **WordPress plugin:** create `wp-content/plugins/semantic-search-pro/` with namespaced PHP, activation hooks, settings screens, REST controllers, Gutenberg block, shortcode, and async sync jobs.
- **Hosted API service:** expose authenticated endpoints for site registration, license validation, content upsert/delete, query search, usage reporting, analytics, and entitlement checks.
- **Embedding layer:** use OpenAI embeddings by default, with an internal provider interface so another embedding provider can be swapped later without changing the WordPress plugin.
- **Vector storage:** use managed vector infrastructure for v1, with metadata filters for site ID, post type, language, taxonomy, publish status, and visibility.
- **Billing:** use Stripe subscriptions with usage metering for indexed items, embedding tokens, and search queries; enforce limits in the hosted API and mirror status in WordPress admin.

## Plugin Features
- **Content ingestion:** index published posts and pages first; add WooCommerce products and selected custom post types in the commercial v1 milestone.
- **Sync behavior:** run a full initial sync from admin, then incremental updates on `save_post`, `trash_post`, `deleted_post`, status changes, and permalink changes.
- **Chunking:** send normalized title, excerpt, body text, URL, post type, taxonomies, language, modified date, and visibility metadata to the backend; chunk long content server-side for consistency.
- **Search UI:** provide a Gutenberg search block, shortcode, and optional replacement for default WordPress search results.
- **Headless support:** expose a public REST endpoint like `/wp-json/semantic-search-pro/v1/search` that proxies to the hosted API and returns normalized JSON results.
- **Admin controls:** include license connection, indexing status, post type selection, result count, similarity threshold, fallback keyword search toggle, reindex button, and usage dashboard.
- **Fallbacks:** use native WordPress keyword search when license is inactive, backend is unavailable, quota is exceeded, or semantic results are below threshold.
- **Privacy:** never index drafts, private posts, password-protected content, or noindex content unless explicitly enabled by an admin.

## Commercial Product Plan
- **Starter tier:** one site, posts/pages, fixed indexed-content cap, basic semantic search block, monthly query allowance.
- **Pro tier:** higher caps, WooCommerce products, custom post types, headless API, analytics, and boost rules.
- **Agency tier:** multiple sites, larger caps, priority sync, white-label search UI options, and consolidated billing.
- **Entitlements:** license state, quota, enabled content types, headless API access, and analytics depth come from the hosted service.
- **Distribution:** publish a lite connector on WordPress.org if desired; sell subscriptions through the product site and Stripe Customer Portal.

## Implementation Phases
- **Phase 1: Plugin foundation:** scaffold plugin, autoloading, activation/deactivation hooks, Settings API admin page, secure option storage, REST namespace, and Studio-compatible local workflow.
- **Phase 2: Content sync MVP:** implement post/page discovery, initial sync, incremental sync hooks, backend request signing, queue/status tracking, and admin progress UI.
- **Phase 3: Hosted search backend:** build site registration, license validation, content upsert/delete, embedding generation, vector upsert/search, usage counters, and query logging.
- **Phase 4: Search experience:** add shortcode, Gutenberg block, frontend assets, REST proxy endpoint, result rendering, fallback search, and basic styling controls.
- **Phase 5: Monetization:** integrate Stripe subscriptions, usage meters, entitlement enforcement, quota warnings, customer portal link, and admin notices.
- **Phase 6: Pro capabilities:** add WooCommerce products, selected custom post types, taxonomy filters, boost rules, analytics dashboard, and headless API documentation.
- **Phase 7: Launch hardening:** add uninstall behavior, privacy policy text, error logging, retry/backoff, rate limits, documentation, onboarding checklist, and packaging.

## Testing Plan
- **WordPress unit/integration:** activation creates defaults, settings sanitize correctly, hooks enqueue sync jobs, REST endpoints enforce permissions, uninstall removes plugin-owned data.
- **Sync scenarios:** new post, update post, delete post, trash/restore post, status transition, permalink change, large content chunking, excluded post type, quota exceeded.
- **Search scenarios:** semantic match, no results, low-confidence fallback, backend outage, inactive license, exceeded quota, malformed query, headless REST request.
- **Commerce scenarios:** Stripe subscription active, trialing, canceled, past due, tier upgrade, downgrade, usage overage, customer portal return.
- **Security:** verify nonces, capabilities, sanitization, escaping, request signing, rate limiting, no private content indexing by default.
- **Studio validation:** use `studio wp` commands only, avoid WordPress core edits, and account for SQLite limitations in local development.

## Assumptions
- The product is SaaS-backed, not a standalone plugin that stores vectors entirely inside WordPress.
- OpenAI embeddings are the default embedding provider for v1.
- Managed vector storage is used for v1 to reduce operational risk and support larger sites.
- Stripe handles subscription billing, while the hosted app owns entitlement and quota logic.
- WordPress.org distribution is optional; if used, the listed plugin remains serviceware-compliant and premium-only code ships separately.

## References
- WordPress plugin hooks, activation, Settings API, REST API, and custom tables: [Plugin Handbook](https://developer.wordpress.org/plugins/)
- WordPress.org paid service/plugin guidance: [Detailed Plugin Guidelines](https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/)
- OpenAI embedding behavior and pricing model: [OpenAI Embeddings Guide](https://platform.openai.com/docs/guides/embeddings)
- Stripe subscription and usage billing model: [Stripe Usage-Based Billing](https://docs.stripe.com/billing/subscriptions/usage-based/how-it-works)
- Managed vector search direction: [Pinecone Docs](https://docs.pinecone.io/)
