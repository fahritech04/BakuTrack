export type DashboardSummary = {
  watchlists_total: number;
  watchlists_active: number;
  alerts_open: number;
  alerts_today: number;
  latest_observations: Array<{
    id: number;
    watchlist_id: number | null;
    watchlist_name?: string | null;
    listing_title: string;
    price_per_base_unit: string;
    base_unit: string;
    is_anomaly: boolean;
    observed_at: string;
  }>;
};

export type Watchlist = {
  id: number;
  custom_product_name: string | null;
  target_price: string | null;
  drop_threshold_pct: string;
  arbitrage_threshold_pct: string;
  is_active: boolean;
  base_unit: string;
};

export type AlertItem = {
  id: number;
  alert_type: "price_drop" | "arbitrage" | "anomaly_block";
  status: "open" | "acknowledged" | "sent";
  message: string;
  triggered_at: string;
};

export type ApiEnvelope<T> = {
  data: T;
};

export type PaginatedResponse<T> = {
  current_page: number;
  data: T[];
  first_page_url: string;
  from: number | null;
  last_page: number;
  last_page_url: string;
  links: Array<{
    url: string | null;
    label: string;
    active: boolean;
  }>;
  next_page_url: string | null;
  path: string;
  per_page: number;
  prev_page_url: string | null;
  to: number | null;
  total: number;
};
