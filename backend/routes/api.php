<?php

use App\Http\Controllers\Api\AlertController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BillingWebhookController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\InternalScrapeController;
use App\Http\Controllers\Api\PriceController;
use App\Http\Controllers\Api\ScrapeController;
use App\Http\Controllers\Api\SubscriptionController;
use App\Http\Controllers\Api\WatchlistController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::post('/auth/register', [AuthController::class, 'register']);
    Route::post('/auth/login', [AuthController::class, 'login']);

    Route::post('/billing/webhook', BillingWebhookController::class);

    Route::post('/internal/scrape/dispatch', [InternalScrapeController::class, 'dispatch']);
    Route::post('/internal/scrape/results', [InternalScrapeController::class, 'ingestResults']);
    Route::post('/internal/scrape/job-status', [InternalScrapeController::class, 'updateScrapeJobStatus']);
    Route::post('/internal/notifications/wa/webhook', [InternalScrapeController::class, 'whatsappWebhook']);
    Route::get('/internal/notifications/pending', [InternalScrapeController::class, 'pendingNotifications']);
    Route::post('/internal/notifications/{notification}/status', [InternalScrapeController::class, 'updateNotificationStatus']);

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/auth/logout', [AuthController::class, 'logout']);

        Route::get('/dashboard/summary', [DashboardController::class, 'summary']);

        Route::get('/watchlists', [WatchlistController::class, 'index']);
        Route::post('/watchlists', [WatchlistController::class, 'store']);
        Route::get('/watchlists/{watchlist}', [WatchlistController::class, 'show']);
        Route::patch('/watchlists/{watchlist}', [WatchlistController::class, 'update']);
        Route::delete('/watchlists/{watchlist}', [WatchlistController::class, 'destroy']);

        Route::get('/prices/latest', [PriceController::class, 'latest']);
        Route::get('/prices/history', [PriceController::class, 'history']);
        Route::post('/scrape/trigger', [ScrapeController::class, 'trigger']);

        Route::get('/alerts', [AlertController::class, 'index']);
        Route::post('/alerts/{alert}/ack', [AlertController::class, 'ack']);

        Route::get('/subscription', [SubscriptionController::class, 'show']);
    });
});
