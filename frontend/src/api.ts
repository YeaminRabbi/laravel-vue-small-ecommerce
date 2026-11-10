export interface User { id: number; name: string; email: string; roles: string[]; permissions: string[] }
export interface Item {
  id: number; name: string; description?: string; price?: string; thumbnail?: string;
  sku?: string; slug?: string; owner_id?: number; created_by?: number; product_id?: number;
  is_active?: boolean; listings_count?: number; shops_count?: number; email?: string;
  owner?: { name: string }; creator?: { name: string }; product?: { name: string; sku: string };
  shop?: { id: number; name: string };
}
export interface Page { data: Item[]; current_page: number; last_page: number; total: number }
export interface Dashboard { stats: Record<string, number | null>; recent_products: Item[]; shops: Item[] }
const base = import.meta.env.VITE_API_BASE_URL || '/api/v1'
export async function api<T>(path: string, method = 'GET', body?: unknown): Promise<T> {
  let response: Response
  try {
    response = await fetch(base + path, {
      method, headers: { Accept: 'application/json', 'Content-Type': 'application/json',
        ...(sessionStorage.getItem('token') ? { Authorization: 'Bearer ' + sessionStorage.getItem('token') } : {}) },
      ...(body !== undefined ? { body: JSON.stringify(body) } : {}),
    })
  } catch { throw new Error('Cannot reach the server. Check that the Laravel backend is running.') }
  if (response.status === 401) { sessionStorage.removeItem('token'); window.dispatchEvent(new Event('session-expired')) }
  if (response.status === 204) return undefined as T
  const data = await response.json().catch(() => null)
  if (response.status === 429) {
    const retryAfter = Number(response.headers.get('Retry-After'))
    const wait = Number.isFinite(retryAfter) && retryAfter > 0 ? `${retryAfter} seconds` : 'a minute'
    throw new Error(`Too many attempts. Please try again in ${wait}.`)
  }
  if (!response.ok) throw new Error(data?.errors ? Object.values(data.errors).flat().join(' ') : data?.message || 'The request could not be completed.')
  return data as T
}
export async function allItems(path: string): Promise<Item[]> {
  const items: Item[] = []
  let page = 1
  while (true) {
    const result = await api<Page>(path + '?page=' + page)
    items.push(...result.data)
    if (page >= result.last_page) return items
    page++
  }
}

