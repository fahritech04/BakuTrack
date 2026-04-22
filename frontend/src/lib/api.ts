import type {
  AlertItem,
  ApiEnvelope,
  DashboardSummary,
  PaginatedResponse,
  Watchlist,
} from "@/types/api";

const apiBaseUrl =
  process.env.NEXT_PUBLIC_API_BASE_URL ?? "http://127.0.0.1:8000/api/v1";

async function request<T>(
  path: string,
  token: string,
  init?: RequestInit,
): Promise<T> {
  const response = await fetch(`${apiBaseUrl}${path}`, {
    ...init,
    headers: {
      "Content-Type": "application/json",
      Authorization: `Bearer ${token}`,
      ...(init?.headers ?? {}),
    },
    cache: "no-store",
  });

  if (!response.ok) {
    const payload = await response.text();
    throw new Error(`API ${response.status}: ${payload}`);
  }

  if (response.status === 204) {
    return {} as T;
  }

  return (await response.json()) as T;
}

export async function fetchDashboardSummary(token: string): Promise<DashboardSummary> {
  const envelope = await request<ApiEnvelope<DashboardSummary>>("/dashboard/summary", token);
  return envelope.data;
}

export async function fetchWatchlists(token: string): Promise<Watchlist[]> {
  const payload = await request<PaginatedResponse<Watchlist>>("/watchlists", token);
  return payload.data;
}

export async function createWatchlist(
  token: string,
  body: {
    custom_product_name: string;
    target_price?: number;
    drop_threshold_pct?: number;
    arbitrage_threshold_pct?: number;
    base_unit?: string;
  },
): Promise<Watchlist> {
  const payload = await request<ApiEnvelope<Watchlist>>("/watchlists", token, {
    method: "POST",
    body: JSON.stringify(body),
  });

  return payload.data;
}

export async function fetchAlerts(token: string): Promise<AlertItem[]> {
  const payload = await request<PaginatedResponse<AlertItem>>("/alerts", token);
  return payload.data;
}

export async function ackAlert(token: string, alertId: number): Promise<void> {
  await request(`/alerts/${alertId}/ack`, token, { method: "POST" });
}

export async function triggerScrape(
  token: string,
  body?: { source_name?: string },
): Promise<{ queued: boolean; tenant_id: number; source_name: string }> {
  const payload = await request<ApiEnvelope<{ queued: boolean; tenant_id: number; source_name: string }>>(
    "/scrape/trigger",
    token,
    {
      method: "POST",
      body: JSON.stringify(body ?? {}),
    },
  );

  return payload.data;
}

export async function deleteWatchlist(token: string, watchlistId: number): Promise<void> {
  await request(`/watchlists/${watchlistId}`, token, {
    method: "DELETE",
  });
}
