// Thin fetch wrapper for the UniFind API.
// - adds the Bearer token from localStorage
// - parses JSON and throws ApiError { message, status, errors }
// - fires the global sign-out handler on 401

// VITE_API_URL may be given with or without the trailing /api; request paths
// below already start with /api, so only the server origin is kept.
const BASE = (import.meta.env.VITE_API_URL || '').replace(/\/+$/, '').replace(/\/api$/, '');
const TOKEN_KEY = 'unifind.token';

export class ApiError extends Error {
  constructor(message, status = 0, errors = {}) {
    super(message);
    this.name = 'ApiError';
    this.status = status;
    this.errors = errors;
  }
}

export const NETWORK_MESSAGE = 'Cannot reach the server, is Laravel running on port 8000?';

let unauthorizedHandler = () => {};
export function onUnauthorized(handler) {
  unauthorizedHandler = handler;
}

export const token = {
  get() {
    try { return localStorage.getItem(TOKEN_KEY); } catch { return null; }
  },
  set(value) {
    try { localStorage.setItem(TOKEN_KEY, value); } catch { /* storage unavailable */ }
  },
  clear() {
    try { localStorage.removeItem(TOKEN_KEY); } catch { /* storage unavailable */ }
  },
};

/** Image URLs from the API are absolute (cloud storage) or relative /storage paths (local disk). */
export function assetUrl(path) {
  if (!path) return null;
  return /^https?:\/\//.test(path) ? path : `${BASE}${path}`;
}

function buildQuery(query) {
  if (!query) return '';
  const params = new URLSearchParams();
  Object.entries(query).forEach(([key, value]) => {
    if (value !== undefined && value !== null && value !== '') params.append(key, value);
  });
  const text = params.toString();
  return text ? `?${text}` : '';
}

export async function api(path, { method = 'GET', body, query, signal } = {}) {
  const headers = { Accept: 'application/json' };
  const current = token.get();
  if (current) headers.Authorization = `Bearer ${current}`;

  let payload;
  if (body instanceof FormData) {
    payload = body; // the browser sets the multipart boundary
  } else if (body !== undefined) {
    headers['Content-Type'] = 'application/json';
    payload = JSON.stringify(body);
  }

  let response;
  try {
    response = await fetch(`${BASE}${path}${buildQuery(query)}`, { method, headers, body: payload, signal });
  } catch (error) {
    if (error.name === 'AbortError') throw error;
    throw new ApiError(NETWORK_MESSAGE, 0);
  }

  let data = null;
  const text = await response.text();
  if (text) {
    try {
      data = JSON.parse(text);
    } catch {
      // The Vite proxy answers 502/504 with HTML when Laravel is down.
      throw new ApiError(response.status >= 500 ? NETWORK_MESSAGE : 'The server sent an unexpected response.', response.status);
    }
  }

  if (!response.ok) {
    if (response.status === 401 && current && !path.startsWith('/api/auth/login')) {
      token.clear();
      unauthorizedHandler();
    }
    if ((response.status === 502 || response.status === 504) && !data) {
      throw new ApiError(NETWORK_MESSAGE, response.status);
    }
    const message = data?.message || `Request failed (${response.status}).`;
    throw new ApiError(message, response.status, data?.errors || {});
  }

  return data;
}

export const get = (path, query, options) => api(path, { ...options, query });
export const post = (path, body) => api(path, { method: 'POST', body });
export const put = (path, body) => api(path, { method: 'PUT', body });
export const patch = (path, body) => api(path, { method: 'PATCH', body });
export const del = (path, body) => api(path, { method: 'DELETE', body });

/** First validation message for a field, if any. */
export function fieldError(errors, name) {
  const value = errors?.[name];
  return Array.isArray(value) ? value[0] : value || null;
}
