# VRSP URL Shortener Worker

This Cloudflare Worker replaces the legacy Bitly integration with a self-hosted short-link service.

## Features

- `POST /shorten` creates or retrieves a short URL for the provided destination.
- `GET /{slug}` redirects visitors to the destination URL.
- Automatic slug generation with collision checks and deterministic reuse for matching destinations.
- API token authentication for write operations.
- Cloudflare KV storage for durable mappings.
- CORS-friendly JSON responses for the REST endpoint.

## Setup

1. Create a KV namespace (e.g. `vrsp-url-links`).
2. Update `wrangler.toml` with the namespace binding IDs and your preferred Worker name.
3. Set the `API_TOKEN` variable to a strong shared secret; the WordPress plugin will send it as a bearer token.
4. (Optional) Set `SHORT_DOMAIN` if you want responses to use a vanity domain instead of the worker's default hostname.
5. Deploy the worker:

   ```bash
   npm install -g wrangler
   cd workers/url-shortener
   wrangler deploy
   ```

6. Map the worker to your custom domain via the Cloudflare dashboard if desired.

## Request Examples

### Create a short link

```bash
curl -X POST "https://short.example.com/shorten" \
     -H "Authorization: Bearer <API_TOKEN>" \
     -H "Content-Type: application/json" \
     --data '{"url":"https://your-site.com/reservations/123"}'
```

Response:

```json
{
  "url": "https://your-site.com/reservations/123",
  "short_url": "https://short.example.com/a1b2c3d",
  "slug": "a1b2c3d"
}
```

### Follow a short link

```
GET https://short.example.com/a1b2c3d → 302 redirect to the destination
```

## Local Development

To emulate the worker locally:

```bash
wrangler dev
```

Wrangler automatically provisions an in-memory KV namespace for development sessions.

## Environment

The worker expects the following bindings:

- `LINKS` (KV Namespace): Stores slug → URL mappings and hash → slug indexes.
- `API_TOKEN` (string): Shared secret for authenticated writes.
- `SHORT_DOMAIN` (string, optional): Hostname used in JSON responses.

