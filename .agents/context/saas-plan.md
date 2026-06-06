# SaaS Backend Plan For Semantic Search Pro

## Summary
Build a separate SaaS backend at `/Users/garth/Studio/semantic-search-pro-saas`, not inside WordPress. Use a modular TypeScript monorepo so the product can start as one deployable service but later split API, workers, and dashboard without rewriting core logic.

Recommended long-term stack: TypeScript, NestJS with Fastify, PostgreSQL, Prisma, AWS ECS/Fargate, RDS Postgres, SQS, Redis, Pinecone Serverless, OpenAI embeddings, Stripe Billing, Next.js dashboard, Terraform, OpenTelemetry, and Sentry.

## Stack Decisions
- **API:** NestJS with Fastify adapter for typed controllers, dependency injection, OpenAPI docs, validation, and a structure that will hold up as billing, indexing, search, analytics, and admin modules grow.
- **Database:** PostgreSQL with Prisma migrations; use RDS Postgres for production and Docker Postgres locally.
- **Jobs:** AWS SQS for durable indexing, deletion, Stripe-event, and usage-rollup jobs; local development uses LocalStack.
- **Cache/rate limits:** Redis for request throttling, license/entitlement cache, idempotency cache, and short-lived search caches.
- **Vector search:** Pinecone Serverless with one production index and namespaces per site/account; use metadata filters for post type, language, publish status, and taxonomies.
- **Embeddings:** OpenAI `text-embedding-3-small` at 1536 dimensions for v1 because it is current, low-cost, and built for semantic search.
- **Billing:** Stripe Billing subscriptions plus internal entitlement tables; use Stripe as the billing source, but enforce quotas in our own backend before reporting billable usage.
- **Dashboard:** Next.js App Router for customer signup, subscription management, license keys, site usage, onboarding, and internal admin tools.
- **Deployment:** Dockerized services on AWS ECS/Fargate, RDS, ElastiCache Redis, SQS, Secrets Manager, CloudWatch, and Terraform-managed infrastructure.

## Required Plugin Contract Adjustment
- Add a first-run site registration flow because the plugin currently signs requests with `site_secret`, but the backend has no way to know that secret.
- Update plugin validation payload to include `site_secret` only during initial `/license/validate` or a new `/sites/register` call over HTTPS.
- Store `site_secret` encrypted at rest with AWS KMS or Secrets Manager, then require HMAC verification for all content and search requests.
- Add `X-SSP-Request-ID` for idempotency and replay protection; reject timestamps older than five minutes.
- Change `/content/upsert` from “indexed immediately” to “queued” for scalability; expose indexing status through `/sites/status`.

## Backend Modules
- **Auth and accounts:** customer accounts, users, roles, passwordless magic links or OAuth, admin-only internal routes, and account/site ownership checks.
- **Licensing:** generated license keys stored as hashes, site activation limits per plan, license status, entitlement snapshots, and customer portal sessions.
- **Billing:** Stripe checkout, customer portal, subscription webhooks, plan changes, failed payment handling, trials, usage meters, and invoice-period usage rollups.
- **Site registry:** bind WordPress `site_id`, URL, secret, plugin version, WordPress version, active plan, sync state, and quota state.
- **Content ingestion:** accept public post/page/product documents, normalize metadata, dedupe by `site_id + post_id + modified_gmt`, enqueue indexing jobs, and delete stale vectors.
- **Chunking:** split content server-side into deterministic chunks with title, excerpt, URL, taxonomy, language, and post metadata preserved.
- **Embedding:** batch OpenAI embedding requests, track token usage, retry transient failures, and persist chunk embedding metadata.
- **Vector store:** upsert/delete Pinecone vectors with IDs like `siteId:postId:chunkIndex`, namespace by site, and filter by metadata at query time.
- **Search:** embed query, search Pinecone, merge chunk hits into post-level results, apply threshold/result limits, return the plugin’s normalized result shape.
- **Usage and analytics:** track indexed items, chunks, embedding tokens, search queries, empty searches, fallback-triggering errors, latency, and quota consumption.
- **Operations:** structured logs, traces, metrics, health checks, background job dashboards, dead-letter queues, and alerting.

## Data Model
- `accounts`: customer organization records.
- `users`: dashboard users attached to accounts.
- `stripe_customers`: Stripe customer IDs, account IDs, and billing metadata.
- `subscriptions`: Stripe subscription state, plan, period boundaries, cancel/past-due flags.
- `licenses`: hashed license key, account ID, plan, status, max sites, created/revoked timestamps.
- `sites`: license ID, `site_id`, normalized URL, encrypted site secret, plugin/WP versions, status, last seen, sync state.
- `documents`: site ID, WordPress post ID, post type, URL, title, excerpt, content hash, modified time, index state.
- `chunks`: document ID, chunk index, vector ID, text hash, token count, indexed timestamp.
- `usage_events`: account/site/license, metric name, quantity, idempotency key, event timestamp.
- `search_events`: query hash, site ID, result count, source, latency, threshold, status.
- `webhook_events`: Stripe event ID, type, processed status, payload hash.
- `api_requests`: request ID, site ID, route, status, latency, idempotency result.

## API Surface
- `POST /v1/license/validate`: validates license, registers or refreshes site, returns entitlements, plan, quotas, and portal URL.
- `POST /v1/content/upsert`: verifies HMAC, stores document snapshot, queues indexing, returns `202 queued`.
- `POST /v1/content/delete`: verifies HMAC, marks document deleted, queues vector deletion.
- `POST /v1/search`: verifies HMAC, enforces quota, runs semantic search, records usage, returns normalized results.
- `GET /v1/sites/status`: returns sync progress, indexed counts, quota state, last error, and plan details.
- `POST /v1/stripe/webhook`: handles checkout, subscription, invoice, customer, and payment lifecycle events.
- `POST /v1/dashboard/checkout`: creates Stripe Checkout session for Starter, Pro, or Agency.
- `POST /v1/dashboard/customer-portal`: creates Stripe Customer Portal session.
- `GET /v1/dashboard/sites`: lists account sites, usage, sync status, and quota warnings.

## Implementation Phases
- **Phase 1: Foundation:** create monorepo, NestJS API, Next.js dashboard shell, Prisma schema, local Docker Compose, env validation, OpenAPI docs, CI checks, and health endpoints.
- **Phase 2: Licensing:** implement account, license, site registration, HMAC verification, idempotency, entitlement snapshots, and plugin-compatible `/license/validate`.
- **Phase 3: Ingestion:** implement `/content/upsert`, `/content/delete`, document persistence, SQS jobs, chunking, retry policy, and sync status.
- **Phase 4: Embeddings and vectors:** integrate OpenAI embeddings, create Pinecone index/namespace strategy, upsert/delete vectors, and store token usage.
- **Phase 5: Search:** implement query embedding, Pinecone retrieval, post-level aggregation, threshold filtering, quota enforcement, usage events, and plugin response shape.
- **Phase 6: Billing:** integrate Stripe Checkout, subscriptions, webhooks, portal sessions, plan entitlements, usage rollups, failed-payment handling, and quota downgrade behavior.
- **Phase 7: Dashboard:** build onboarding, license display, site list, sync status, usage analytics, billing portal links, and internal admin views.
- **Phase 8: Production hardening:** add Terraform, ECS deploys, RDS backups, Redis, SQS dead-letter queues, Sentry, OpenTelemetry, CloudWatch alerts, runbooks, and security review.

## Test Plan
- **Unit tests:** HMAC verification, entitlement checks, quota math, chunking, metadata normalization, result aggregation, and Stripe webhook reducers.
- **Integration tests:** license validation, site registration, content upsert/delete, search, usage recording, webhook idempotency, and dashboard billing flows.
- **Contract tests:** run backend responses against the WordPress plugin’s expected JSON shapes and error behavior.
- **Load tests:** index large sites, concurrent searches, Stripe webhook bursts, OpenAI retry handling, and Pinecone latency thresholds.
- **Security tests:** replay rejection, invalid signatures, revoked license, exceeded site limits, quota exhaustion, private content rejection, webhook signature validation.
- **Operational tests:** worker retry/dead-letter behavior, database migration rollback, backup restore, alert firing, and deploy rollback.

## Assumptions
- Backend will live in its own repo/directory, separate from the WordPress Studio site.
- The SaaS backend is the source of truth for plans, entitlements, quotas, and usage.
- WordPress plugin search requests are proxied through the plugin, so the backend does not need to expose unauthenticated browser search.
- Start with semantic dense search; add hybrid lexical search and reranking after the paid MVP proves demand.
- Stripe handles payment collection, while internal tables enforce real-time quota and site access.
- OpenAI and Pinecone costs should be reviewed again before launch, but current docs support `text-embedding-3-small`, usage-based billing, and serverless vector search.

## References
- OpenAI embeddings: [OpenAI Embeddings Guide](https://platform.openai.com/docs/guides/embeddings)
- OpenAI embedding model: [text-embedding-3-small](https://platform.openai.com/docs/models/text-embedding-3-small)
- Stripe usage billing: [Stripe Usage-Based Billing](https://docs.stripe.com/billing/subscriptions/usage-based)
- Stripe billing lifecycle: [How Usage-Based Billing Works](https://docs.stripe.com/billing/subscriptions/usage-based/how-it-works)
- Pinecone hybrid/vector search: [Pinecone Hybrid Search](https://docs.pinecone.io/guides/search/hybrid-search)
- Pinecone serverless indexes: [Create a Serverless Index](https://docs.pinecone.io/docs/create-an-index)
