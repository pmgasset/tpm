export interface Env {
  LINKS: KVNamespace;
  API_TOKEN?: string;
  SHORT_DOMAIN?: string;
}

const ALPHABET = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
const SHORTEN_PATH = '/shorten';

export default {
  async fetch(request: Request, env: Env): Promise<Response> {
    if (request.method === 'OPTIONS') {
      return handleOptions(request);
    }

    const url = new URL(request.url);

    if (request.method === 'POST' && normalizePath(url.pathname) === SHORTEN_PATH) {
      const authCheck = authorize(request, env);
      if (authCheck) {
        return authCheck;
      }

      try {
        return await handleShorten(request, env, url);
      } catch (error) {
        console.error('Unexpected error while shortening URL', error);
        return jsonResponse({ error: 'Internal error' }, { status: 500 });
      }
    }

    if (request.method === 'GET') {
      if (normalizePath(url.pathname) === '/health') {
        return new Response('ok', { status: 200 });
      }

      const slug = url.pathname.replace(/^\//, '');
      if (!slug) {
        return new Response('Not Found', { status: 404 });
      }

      const target = await env.LINKS.get(slugKey(slug));
      if (!target) {
        return new Response('Not Found', { status: 404 });
      }

      return Response.redirect(target, 302);
    }

    return new Response('Not Found', { status: 404 });
  },
};

function handleOptions(request: Request): Response {
  const headers = new Headers();
  headers.set('Access-Control-Allow-Origin', request.headers.get('Origin') || '*');
  headers.set('Access-Control-Allow-Headers', 'Content-Type, Authorization');
  headers.set('Access-Control-Allow-Methods', 'POST, OPTIONS');
  headers.set('Access-Control-Max-Age', '86400');
  return new Response(null, { status: 204, headers });
}

function authorize(request: Request, env: Env): Response | null {
  const token = (env.API_TOKEN || '').trim();
  if (!token) {
    return null;
  }

  const header = request.headers.get('Authorization') || '';
  const expected = `Bearer ${token}`;
  if (header !== expected) {
    return jsonResponse({ error: 'Unauthorized' }, { status: 401 });
  }

  return null;
}

async function handleShorten(request: Request, env: Env, requestUrl: URL): Promise<Response> {
  const payload = await readJson(request);
  if (!payload || typeof payload.url !== 'string') {
    return jsonResponse({ error: 'Missing url parameter' }, { status: 400 });
  }

  const destination = payload.url.trim();
  if (!isValidUrl(destination)) {
    return jsonResponse({ error: 'Invalid URL' }, { status: 400 });
  }

  const preferredSlug = typeof payload.slug === 'string' ? sanitizeSlug(payload.slug) : '';

  const hashKeyValue = await hashUrl(destination);
  const hashKey = hashIndexKey(hashKeyValue);
  const existingSlug = await env.LINKS.get(hashKey);
  if (existingSlug) {
    const currentTarget = await env.LINKS.get(slugKey(existingSlug));
    if (currentTarget) {
      return jsonResponse(buildResponseBody(requestUrl, env, existingSlug, currentTarget));
    }
    await env.LINKS.delete(hashKey);
  }

  let slug = preferredSlug || (await generateSlug(env));

  if (preferredSlug) {
    const taken = await env.LINKS.get(slugKey(preferredSlug));
    if (taken && taken !== destination) {
      return jsonResponse({ error: 'Slug already in use' }, { status: 409 });
    }
  }

  await env.LINKS.put(slugKey(slug), destination);
  await env.LINKS.put(hashKey, slug);

  return jsonResponse(buildResponseBody(requestUrl, env, slug, destination));
}

async function generateSlug(env: Env, length = 7): Promise<string> {
  let candidate = '';
  do {
    candidate = randomSlug(length);
  } while (await env.LINKS.get(slugKey(candidate)));
  return candidate;
}

function randomSlug(length: number): string {
  const bytes = new Uint8Array(length);
  crypto.getRandomValues(bytes);
  let slug = '';
  for (let i = 0; i < length; i += 1) {
    slug += ALPHABET.charAt(bytes[i] % ALPHABET.length);
  }
  return slug;
}

function sanitizeSlug(value: string): string {
  const trimmed = value.trim();
  const cleaned = trimmed.replace(/[^a-zA-Z0-9_-]/g, '');
  return cleaned.slice(0, 48);
}

function isValidUrl(value: string): boolean {
  try {
    const parsed = new URL(value);
    return parsed.protocol === 'http:' || parsed.protocol === 'https:';
  } catch (error) {
    return false;
  }
}

async function readJson(request: Request): Promise<any> {
  try {
    return await request.json();
  } catch (error) {
    return null;
  }
}

function buildResponseBody(requestUrl: URL, env: Env, slug: string, destination: string) {
  return {
    url: destination,
    slug,
    short_url: `${responseOrigin(requestUrl, env)}/${slug}`,
  };
}

function responseOrigin(requestUrl: URL, env: Env): string {
  const configured = (env.SHORT_DOMAIN || '').trim();
  if (configured) {
    const normalized = configured.startsWith('http') ? configured : `https://${configured}`;
    return normalized.replace(/\/$/, '');
  }

  return `${requestUrl.protocol}//${requestUrl.host}`;
}

function slugKey(slug: string): string {
  return `slug:${slug}`;
}

function hashIndexKey(hash: string): string {
  return `hash:${hash}`;
}

async function hashUrl(value: string): Promise<string> {
  const data = new TextEncoder().encode(value);
  const digest = await crypto.subtle.digest('SHA-256', data);
  const bytes = Array.from(new Uint8Array(digest));
  return bytes.map((byte) => byte.toString(16).padStart(2, '0')).join('').slice(0, 32);
}

function jsonResponse(body: any, init: ResponseInit = {}): Response {
  const headers = new Headers(init.headers);
  headers.set('Content-Type', 'application/json');
  headers.set('Access-Control-Allow-Origin', '*');
  headers.set('Cache-Control', 'no-store');
  return new Response(JSON.stringify(body), { ...init, headers });
}

function normalizePath(pathname: string): string {
  if (!pathname) {
    return '/';
  }
  return pathname.endsWith('/') && pathname !== '/' ? pathname.slice(0, -1) : pathname;
}
