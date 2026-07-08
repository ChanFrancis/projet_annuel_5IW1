import { useAuthStore } from '@/store/auth';

const baseURL = import.meta.env.VITE_API_URL ?? 'http://localhost:8000';

export class ApiError extends Error {
  constructor(
    public status: number,
    public data: unknown,
  ) {
    super(`API error ${status}`);
    this.name = 'ApiError';
  }
}

interface RequestOptions extends Omit<RequestInit, 'body'> {
  body?: unknown;
  auth?: boolean; // attach JWT (default: true)
}

// Coalesce concurrent refreshes so a burst of 401s triggers a single refresh.
let refreshing: Promise<string | null> | null = null;

async function request<T>(path: string, options: RequestOptions = {}, isRetry = false): Promise<T> {
  const { body, auth = true, headers, ...rest } = options;

  const finalHeaders = new Headers(headers);
  if (body !== undefined && !finalHeaders.has('Content-Type')) {
    finalHeaders.set('Content-Type', 'application/json');
  }
  if (auth) {
    const token = useAuthStore.getState().token;
    if (token) finalHeaders.set('Authorization', `Bearer ${token}`);
  }

  const res = await fetch(`${baseURL}${path}`, {
    ...rest,
    headers: finalHeaders,
    body: body !== undefined ? JSON.stringify(body) : undefined,
  });

  // Transparent refresh-and-replay on the first 401.
  if (res.status === 401 && auth && !isRetry) {
    refreshing ??= useAuthStore.getState().refresh();
    const newToken = await refreshing;
    refreshing = null;
    if (newToken) {
      return request<T>(path, options, true);
    }
    useAuthStore.getState().logout();
  }

  const isJson = res.headers.get('Content-Type')?.includes('application/json');
  const data = isJson ? await res.json() : await res.text();

  if (!res.ok) {
    throw new ApiError(res.status, data);
  }
  return data as T;
}

export const api = {
  get: <T>(path: string, options?: RequestOptions) => request<T>(path, { ...options, method: 'GET' }),
  post: <T>(path: string, body?: unknown, options?: RequestOptions) =>
    request<T>(path, { ...options, method: 'POST', body }),
  put: <T>(path: string, body?: unknown, options?: RequestOptions) =>
    request<T>(path, { ...options, method: 'PUT', body }),
  patch: <T>(path: string, body?: unknown, options?: RequestOptions) =>
    request<T>(path, { ...options, method: 'PATCH', body }),
  delete: <T>(path: string, options?: RequestOptions) =>
    request<T>(path, { ...options, method: 'DELETE' }),
};
