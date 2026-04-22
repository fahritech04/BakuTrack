<?php

use App\Models\Alert;
use App\Models\NotificationLog;
use App\Models\PriceObservation;
use App\Models\ScrapeJob;
use App\Models\ScrapeResultRaw;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('bakutrack:prune-data {--raw-days=30} {--observation-days=180} {--job-days=30}', function () {
    $rawDays = max(1, (int) $this->option('raw-days'));
    $observationDays = max(7, (int) $this->option('observation-days'));
    $jobDays = max(1, (int) $this->option('job-days'));

    $rawCutoff = now()->subDays($rawDays);
    $observationCutoff = now()->subDays($observationDays);
    $jobCutoff = now()->subDays($jobDays);

    $deletedRaw = ScrapeResultRaw::query()->where('scraped_at', '<', $rawCutoff)->delete();
    $deletedObservations = PriceObservation::query()->where('observed_at', '<', $observationCutoff)->delete();
    $deletedJobs = ScrapeJob::query()
        ->whereIn('status', ['success', 'failed'])
        ->where('finished_at', '<', $jobCutoff)
        ->delete();
    $deletedNotificationLogs = NotificationLog::query()
        ->whereIn('status', ['sent', 'failed'])
        ->where('created_at', '<', $jobCutoff)
        ->delete();
    $deletedAlerts = Alert::query()
        ->whereIn('status', ['acknowledged', 'sent'])
        ->where('created_at', '<', $jobCutoff)
        ->delete();

    $this->info('Prune completed.');
    $this->line("Deleted scrape_result_raws: {$deletedRaw}");
    $this->line("Deleted price_observations: {$deletedObservations}");
    $this->line("Deleted scrape_jobs: {$deletedJobs}");
    $this->line("Deleted notification_logs: {$deletedNotificationLogs}");
    $this->line("Deleted alerts: {$deletedAlerts}");
})->purpose('Prune old dynamic data to keep database healthy');
