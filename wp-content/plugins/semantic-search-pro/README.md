# Semantic Search Pro

Semantic Search Pro is a SaaS-backed WordPress plugin that adds semantic search to content-heavy WordPress sites while keeping embeddings, vector storage, entitlement checks, billing, and analytics in a hosted service.

## Implemented plugin capabilities

- Settings page at `Settings > Semantic Search Pro`.
- License key and hosted API URL configuration.
- Generated site ID and site secret for signed hosted API requests.
- Full-sync queue for published posts and pages by default.
- Incremental sync hooks for save, trash, delete, and status transitions.
- Public REST search endpoint at `/wp-json/semantic-search-pro/v1/search`.
- Admin REST endpoints for status, full sync, and license validation.
- Native keyword fallback when semantic search fails or returns no results.
- Optional main-query search replacement using semantic result ordering.
- Shortcode: `[semantic_search_pro]`.
- Dynamic Gutenberg block: `Semantic Search`.
- Privacy policy text and uninstall cleanup.

## Hosted service responsibilities

The WordPress plugin expects a hosted API to handle:

- License validation and entitlement responses.
- OpenAI embedding generation.
- Managed vector database upserts, deletes, and search.
- Usage metering for indexed content, embedding tokens, and search queries.
- Stripe subscription and customer portal integration.
- Analytics aggregation and plan enforcement.

See `docs/hosted-api-contract.md` for request and response shapes.

## Local Studio workflow

Use Studio CLI commands from the WordPress root:

```bash
studio wp plugin activate semantic-search-pro
studio wp option get ssp_options --format=json
```

If `studio` cannot access the site, open WordPress Studio and enable the Studio CLI from settings.

## MVP rollout

1. Configure a hosted API URL and license key.
2. Validate the license from the settings page.
3. Select content types for indexing.
4. Queue a full sync.
5. Add the Semantic Search block or `[semantic_search_pro]` shortcode to a page.
6. Enable default search replacement after semantic results are verified.
