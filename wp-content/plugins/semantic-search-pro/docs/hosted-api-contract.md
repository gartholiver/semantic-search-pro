# Hosted API Contract

The plugin uses a versioned hosted API base URL, defaulting to `https://api.semanticsearchpro.example/v1`.

## Authentication

Every request includes:

- `X-SSP-License-Key`: customer license key from WordPress settings.
- `X-SSP-Site-ID`: generated UUID stored in WordPress options.
- `X-SSP-Site-URL`: WordPress `home_url()`.
- `X-SSP-Timestamp`: Unix timestamp.
- `X-SSP-Signature`: HMAC SHA-256 of `METHOD + "\n" + PATH + "\n" + JSON_BODY + "\n" + TIMESTAMP`, signed with the generated site secret.
- `X-SSP-Plugin-Version`: installed plugin version.

## `POST /license/validate`

Request:

```json
{
  "site_url": "https://example.com",
  "site_id": "uuid",
  "wp_version": "6.8",
  "plugin": "0.1.0"
}
```

Response:

```json
{
  "status": "active",
  "plan": "pro",
  "monthly_query_limit": 25000,
  "customer_portal_url": "https://billing.example.com/session"
}
```

## `POST /content/upsert`

Request:

```json
{
  "site_id": "uuid",
  "site_url": "https://example.com",
  "post_id": 123,
  "post_type": "post",
  "post_status": "publish",
  "title": "Example post",
  "excerpt": "Short summary",
  "content": "Plain text body",
  "url": "https://example.com/example-post/",
  "language": "en-US",
  "modified_gmt": "2026-06-06T12:00:00+00:00",
  "published_gmt": "2026-06-01T12:00:00+00:00",
  "taxonomies": {
    "category": [
      { "id": 1, "name": "Guides", "slug": "guides" }
    ]
  },
  "visibility": "public"
}
```

Response:

```json
{
  "status": "indexed",
  "chunks": 3,
  "embedding_tokens": 1200
}
```

## `POST /content/delete`

Request:

```json
{
  "site_id": "uuid",
  "site_url": "https://example.com",
  "post_id": 123
}
```

Response:

```json
{
  "status": "deleted"
}
```

## `POST /search`

Request:

```json
{
  "site_id": "uuid",
  "site_url": "https://example.com",
  "query": "how do I choose running shoes for flat feet",
  "limit": 8,
  "similarity_threshold": 0.72,
  "post_types": ["post", "page"]
}
```

Response:

```json
{
  "results": [
    {
      "post_id": 123,
      "title": "Running Shoe Fit Guide",
      "url": "https://example.com/running-shoe-fit-guide/",
      "excerpt": "A guide to support, arches, and fit.",
      "post_type": "post",
      "score": 0.89
    }
  ],
  "usage": {
    "monthly_query_count": 1042,
    "monthly_query_limit": 25000
  }
}
```

## Error shape

Use standard HTTP statuses and this JSON body:

```json
{
  "message": "Quota exceeded.",
  "code": "quota_exceeded"
}
```

The plugin falls back to native WordPress keyword search when search errors occur and fallback is enabled.
