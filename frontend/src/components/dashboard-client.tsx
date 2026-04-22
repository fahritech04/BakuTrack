"use client";

import {
  ackAlert,
  createWatchlist,
  deleteWatchlist,
  fetchAlerts,
  fetchDashboardSummary,
  fetchWatchlists,
  triggerScrape,
} from "@/lib/api";
import type { AlertItem, DashboardSummary, Watchlist } from "@/types/api";
import { useCallback, useMemo, useState } from "react";

const TOKEN_KEY = "bakutrack_token";

export function DashboardClient() {
  const [token, setToken] = useState(() => {
    if (typeof window === "undefined") {
      return "";
    }

    return localStorage.getItem(TOKEN_KEY) ?? "";
  });

  const [summary, setSummary] = useState<DashboardSummary | null>(null);
  const [watchlists, setWatchlists] = useState<Watchlist[]>([]);
  const [alerts, setAlerts] = useState<AlertItem[]>([]);
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(false);
  const [scraping, setScraping] = useState(false);

  const [productName, setProductName] = useState("");
  const [targetPrice, setTargetPrice] = useState("");
  const [info, setInfo] = useState<string | null>(null);

  const loadData = useCallback(async () => {
    if (!token) {
      setError("Simpan token API terlebih dahulu.");
      return;
    }

    setLoading(true);
    setError(null);
    setInfo(null);

    try {
      const [summaryData, watchlistData, alertData] = await Promise.all([
        fetchDashboardSummary(token),
        fetchWatchlists(token),
        fetchAlerts(token),
      ]);

      setSummary(summaryData);
      setWatchlists(watchlistData);
      setAlerts(alertData);
    } catch (err) {
      setError(err instanceof Error ? err.message : "Gagal mengambil data API.");
    } finally {
      setLoading(false);
    }
  }, [token]);

  const saveToken = useCallback(async () => {
    if (!token.trim()) {
      setError("Token tidak boleh kosong.");
      return;
    }

    const trimmed = token.trim();
    localStorage.setItem(TOKEN_KEY, trimmed);
    setToken(trimmed);
    setError(null);
    await loadData();
  }, [loadData, token]);

  const triggerScrapeAndReload = useCallback(
    async (message: string) => {
      setScraping(true);
      setError(null);
      try {
        await triggerScrape(token, { source_name: "hybrid" });
        setInfo(message);
        await sleep(10000);
        await loadData();
      } finally {
        setScraping(false);
      }
    },
    [loadData, token],
  );

  const insight = useMemo(() => {
    if (!summary) {
      return "Sambungkan token API untuk menampilkan insight harga harian.";
    }

    if (summary.alerts_open > 0) {
      return `Ada ${summary.alerts_open} alert terbuka. Prioritaskan cek supplier termurah hari ini.`;
    }

    return "Belum ada alert kritis. Pantau tren harga dan tambah watchlist komoditas utama.";
  }, [summary]);

  async function submitWatchlist(event: React.FormEvent<HTMLFormElement>) {
    event.preventDefault();

    if (!productName.trim()) {
      setError("Nama bahan baku wajib diisi.");
      return;
    }

    try {
      await createWatchlist(token, {
        custom_product_name: productName,
        target_price: targetPrice ? Number(targetPrice) : undefined,
      });

      await triggerScrapeAndReload("Scraping hybrid dipicu. Memuat ulang observasi sekitar 10 detik...");
      setProductName("");
      setTargetPrice("");
    } catch (err) {
      setError(err instanceof Error ? err.message : "Gagal membuat watchlist.");
    }
  }

  async function handleTriggerScrape() {
    if (!token) {
      setError("Simpan token API terlebih dahulu.");
      return;
    }

    try {
      await triggerScrapeAndReload("Scraping hybrid dipicu. Memuat ulang observasi sekitar 10 detik...");
    } catch (err) {
      setError(err instanceof Error ? err.message : "Gagal trigger scraping.");
    }
  }

  async function handleAck(alertId: number) {
    try {
      await ackAlert(token, alertId);
      await loadData();
    } catch (err) {
      setError(err instanceof Error ? err.message : "Gagal acknowledge alert.");
    }
  }

  async function handleDeleteWatchlist(watchlistId: number) {
    try {
      await deleteWatchlist(token, watchlistId);
      setInfo("Watchlist dihapus.");
      await loadData();
    } catch (err) {
      setError(err instanceof Error ? err.message : "Gagal menghapus watchlist.");
    }
  }

  return (
    <div className="market-shell mx-auto w-full max-w-7xl px-4 py-7 sm:px-6 lg:px-8">
      <div className="orb orb-a" />
      <div className="orb orb-b" />

      <section className="hero-panel fade-rise rounded-[2rem] p-6 sm:p-8">
        <div className="grid gap-6 lg:grid-cols-[minmax(0,1.2fr)_minmax(0,0.8fr)] lg:items-start">
          <div className="space-y-4">
            <span className="hero-badge inline-flex rounded-full px-3 py-1 text-xs font-bold tracking-wide">
              LIVE PRICE INTEL
            </span>
            <div className="space-y-2">
              <h1 className="text-3xl font-bold leading-tight text-ink sm:text-4xl">
                BakuTrack
                <br />
                Control Room
              </h1>
              <p className="max-w-2xl text-sm leading-relaxed text-muted sm:text-base">
                Pantau bahan baku harian, tangkap peluang arbitrage, dan gerakkan keputusan belanja lebih cepat
                dengan alert otomatis.
              </p>
            </div>
            <div className="chip chip-alert">
              <span className="h-2 w-2 rounded-full bg-brand-clay" />
              {insight}
            </div>
          </div>

          <div className="surface-card rounded-2xl p-4 sm:p-5">
            <p className="text-xs font-semibold uppercase tracking-wider text-muted">Aksi Cepat</p>
            <div className="mt-3 grid gap-3 sm:grid-cols-2">
              <button onClick={() => void loadData()} className="btn btn-outline px-4 py-2 text-sm" type="button">
                Refresh Data
              </button>
              <button
                onClick={() => void handleTriggerScrape()}
                className="btn btn-olive px-4 py-2 text-sm"
                type="button"
                disabled={scraping}
              >
                {scraping ? "Scraping..." : "Scrape Sekarang"}
              </button>
            </div>
            <p className="mt-3 text-xs text-muted">Mode: `hybrid` (BI + marketplace fallback)</p>
          </div>
        </div>
      </section>

      <section className="surface-card mt-6 grid gap-4 rounded-3xl p-4 sm:grid-cols-3 sm:p-5">
        <label className="col-span-2">
          <span className="mb-1 block text-sm font-semibold text-ink">API Token (Laravel Sanctum)</span>
          <input
            className="input w-full px-3 py-2 text-sm text-ink"
            placeholder="paste bearer token..."
            value={token}
            onChange={(event) => setToken(event.target.value)}
          />
        </label>
        <div className="flex items-end gap-2">
          <button
            type="button"
            onClick={() => void saveToken()}
            className="btn btn-main w-full px-4 py-2 text-sm"
          >
            Simpan Token
          </button>
        </div>
      </section>

      {error ? (
        <p className="mt-4 rounded-2xl border border-red-300 bg-red-50/90 px-4 py-3 text-sm font-medium text-red-800">
          {error}
        </p>
      ) : null}
      {info ? (
        <p className="pulse-note mt-4 rounded-2xl border border-emerald-300 bg-emerald-50/90 px-4 py-3 text-sm font-medium text-emerald-800">
          {info}
        </p>
      ) : null}

      <section className="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <MetricCard label="Watchlist" value={summary?.watchlists_total ?? 0} />
        <MetricCard label="Aktif" value={summary?.watchlists_active ?? 0} />
        <MetricCard label="Alert Open" value={summary?.alerts_open ?? 0} tone="danger" />
        <MetricCard label="Alert Hari Ini" value={summary?.alerts_today ?? 0} />
      </section>

      <section className="mt-6 grid gap-6 xl:grid-cols-[minmax(0,1.3fr)_minmax(0,0.9fr)]">
        <article className="surface-card rounded-3xl p-4 sm:p-5">
          <div className="flex flex-wrap items-center justify-between gap-3">
            <div>
              <h2 className="text-lg font-bold text-ink sm:text-xl">Observasi Harga Terbaru</h2>
              <p className="mt-1 text-sm text-muted">Data yang paling relevan untuk setiap watchlist aktif.</p>
            </div>
            <span className="chip chip-source">{summary?.latest_observations?.length ?? 0} item</span>
          </div>

          <div className="mt-4 space-y-3 sm:space-y-4">
            {summary?.latest_observations?.length ? (
              summary.latest_observations.map((item, index) => {
                const title = formatListingTitle(item.listing_title);
                return (
                  <div
                    key={item.id}
                    className="entry-card fade-rise rounded-2xl px-4 py-3 sm:px-5"
                    style={{ animationDelay: `${Math.min(index * 70, 280)}ms` }}
                  >
                    <div className="flex flex-wrap items-start justify-between gap-2">
                      <p className="text-base font-semibold leading-snug text-ink">
                        {item.watchlist_name ? item.watchlist_name : title}
                      </p>
                      <span className="chip chip-source">observed</span>
                    </div>
                    <p className="mt-2 line-clamp-2 text-sm text-muted">Sumber listing: {title}</p>
                    <p className="mt-2 text-lg font-bold text-brand-night">
                      Rp {Number(item.price_per_base_unit).toLocaleString("id-ID")}/{item.base_unit}
                    </p>
                    <p className="mt-1 text-xs text-muted">
                      {new Date(item.observed_at).toLocaleString("id-ID")}
                      {item.is_anomaly ? " - flagged anomaly" : ""}
                    </p>
                  </div>
                );
              })
            ) : (
              <p className="rounded-2xl border border-dashed border-line bg-white/60 px-4 py-6 text-sm text-muted">
                Belum ada observasi harga.
              </p>
            )}
          </div>
        </article>

        <article className="surface-card rounded-3xl p-4 sm:p-5">
          <h2 className="text-lg font-bold text-ink sm:text-xl">Tambah Watchlist</h2>
          <p className="mt-1 text-sm text-muted">Input bahan baku baru, sistem akan cari harga terbaru otomatis.</p>
          <form className="mt-4 space-y-3" onSubmit={submitWatchlist}>
            <input
              className="input w-full px-3 py-2 text-sm"
              placeholder="masukkan nama bahan baku"
              value={productName}
              onChange={(event) => setProductName(event.target.value)}
              required
            />
            <input
              className="input w-full px-3 py-2 text-sm"
              placeholder="target harga (opsional)"
              value={targetPrice}
              onChange={(event) => setTargetPrice(event.target.value)}
              type="number"
              min={0}
            />
            <button
              type="submit"
              className="btn btn-main w-full px-4 py-2 text-sm"
            >
              Simpan Watchlist
            </button>
          </form>

          <div className="mt-5 space-y-2.5">
            {watchlists.map((watchlist) => (
              <div key={watchlist.id} className="entry-card rounded-2xl p-3 text-sm">
                <p className="font-semibold text-ink">{watchlist.custom_product_name ?? "(tanpa nama)"}</p>
                <div className="mt-1 flex items-center justify-between gap-3">
                  <p className="text-muted">
                    Drop {watchlist.drop_threshold_pct}% | Arbitrage {watchlist.arbitrage_threshold_pct}%
                  </p>
                  <button
                    type="button"
                    onClick={() => void handleDeleteWatchlist(watchlist.id)}
                    className="btn btn-outline rounded-lg border-red-200 px-2 py-1 text-xs text-danger"
                  >
                    Hapus
                  </button>
                </div>
              </div>
            ))}
          </div>
        </article>
      </section>

      <section className="surface-card mt-6 rounded-3xl p-4 sm:p-5">
        <div className="flex items-center justify-between gap-3">
          <h2 className="text-lg font-bold text-ink sm:text-xl">Alert Feed</h2>
          <span className="chip chip-alert">{alerts.length} alert</span>
        </div>
        <div className="mt-3 space-y-3">
          {alerts.length ? (
            alerts.map((alert) => (
              <div
                key={alert.id}
                className="entry-card flex flex-col gap-3 rounded-2xl p-3 sm:flex-row sm:items-center sm:justify-between"
              >
                <div>
                  <p className="font-semibold text-ink">
                    [{alert.alert_type}] {alert.message}
                  </p>
                  <p className="mt-1 text-xs text-muted">{new Date(alert.triggered_at).toLocaleString("id-ID")}</p>
                </div>
                {alert.status === "open" ? (
                  <button
                    type="button"
                    onClick={() => void handleAck(alert.id)}
                    className="btn btn-olive px-3 py-2 text-sm"
                  >
                    Ack
                  </button>
                ) : (
                  <span className="chip chip-source uppercase">{alert.status}</span>
                )}
              </div>
            ))
          ) : (
            <p className="rounded-2xl border border-dashed border-line bg-white/60 px-4 py-6 text-sm text-muted">
              Belum ada alert.
            </p>
          )}
        </div>
      </section>

      {loading ? <p className="mt-4 text-sm font-semibold text-muted">Memuat data...</p> : null}
    </div>
  );
}

function formatListingTitle(rawTitle: string): string {
  const compact = rawTitle.replace(/\s+/g, " ").trim();
  const noLeadingUnit = compact
    .replace(/^(\d+\s*(unit|box|dus|karton|pack)\s*)/i, "")
    .replace(/^(\d+\s*(unit|box|dus|karton|pack))(?=[A-Za-z])/i, "");
  const beforePrice = noLeadingUnit.split(/Rp\s*[\d.,]+/i)[0].trim();
  const cleaned = (beforePrice || noLeadingUnit).replace(/\s{2,}/g, " ").trim();
  return cleaned.length > 120 ? `${cleaned.slice(0, 117)}...` : cleaned;
}

function sleep(ms: number): Promise<void> {
  return new Promise((resolve) => {
    setTimeout(resolve, ms);
  });
}

function MetricCard({
  label,
  value,
  tone = "normal",
}: {
  label: string;
  value: number;
  tone?: "normal" | "danger";
}) {
  return (
    <div className={`metric-tile rounded-2xl p-4 ${tone === "danger" ? "metric-tile-danger" : ""}`}>
      <p className="text-xs font-semibold uppercase tracking-wider text-muted">{label}</p>
      <p className="mt-1 text-3xl font-bold text-brand-night">{value}</p>
    </div>
  );
}
