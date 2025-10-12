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

### Deploying from the Cloudflare Dashboard

If you prefer to configure the worker entirely through the Cloudflare website instead of
using `wrangler`, follow these steps:

1. Log into the [Cloudflare dashboard](https://dash.cloudflare.com/) and select the account
   that owns your domain.
2. Navigate to **Workers & Pages** → **Workers** and click **Create** → **Create Worker**.
3. Replace the default script with the contents of [`src/index.ts`](./src/index.ts). You can
   paste the transpiled JavaScript output from `npm run build` or enable the **Quick Edit**
   TypeScript compiler toggle before pasting the TypeScript source.
4. Under **Settings** → **Variables**, add a text variable named `API_TOKEN` with the same value
   you will configure in WordPress. Add another optional text variable named `SHORT_DOMAIN`
   when you want the API to return a vanity domain.
5. Still under **Settings**, add a KV namespace binding named `LINKS` by selecting **Add binding** →
   **KV Namespace** and choosing **Create namespace** (or attaching an existing namespace).
6. Click **Save and deploy** to publish the worker.
7. (Optional) From the worker overview page, choose **Triggers** → **Add custom domain** to map
   the worker to `https://short.example.com` or another hostname on your zone.

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

