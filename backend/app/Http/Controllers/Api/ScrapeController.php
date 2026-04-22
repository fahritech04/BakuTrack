<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ResolvesTenant;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ScrapeController extends Controller
{
    use ResolvesTenant;

    public function trigger(Request $request): JsonResponse
    {
        if ($guard = $this->tenantGuard($request)) {
            return $guard;
        }

        $validated = $request->validate([
            'source_name' => ['nullable', 'string', 'max:60'],
        ]);

        $tenantId = (int) $this->tenantIdFrom($request);
        $sourceName = $validated['source_name'] ?? 'hybrid';
        $scraperPythonBin = config('bakutrack.scraper_python_bin');
        $scraperWorkdir = config('bakutrack.scraper_workdir');

        if (! is_string($scraperPythonBin) || $scraperPythonBin === '') {
            return response()->json([
                'message' => 'SCRAPER_PYTHON_BIN is not configured.',
            ], 500);
        }

        if (! is_string($scraperWorkdir) || $scraperWorkdir === '') {
            return response()->json([
                'message' => 'SCRAPER_WORKDIR is not configured.',
            ], 500);
        }

        $this->spawnDetachedRunner(
            scraperPythonBin: $scraperPythonBin,
            scraperWorkdir: $scraperWorkdir,
            sourceName: $sourceName,
            tenantId: $tenantId
        );

        return response()->json([
            'data' => [
                'queued' => true,
                'tenant_id' => $tenantId,
                'source_name' => $sourceName,
            ],
        ], 202);
    }

    private function spawnDetachedRunner(
        string $scraperPythonBin,
        string $scraperWorkdir,
        string $sourceName,
        int $tenantId
    ): void {
        $tenantArg = (string) $tenantId;
        $safeSource = preg_replace('/[^a-z0-9_-]/i', '', $sourceName) ?: 'hybrid';

        if (DIRECTORY_SEPARATOR === '\\') {
            $quotedPython = str_replace("'", "''", $scraperPythonBin);
            $quotedWorkdir = str_replace("'", "''", $scraperWorkdir);
            $argumentList = "-m src.runner --source {$safeSource} --tenant-id {$tenantArg}";
            $quotedArgs = str_replace("'", "''", $argumentList);
            $command = sprintf(
                "powershell -NoProfile -Command \"Start-Process -FilePath '%s' -ArgumentList '%s' -WorkingDirectory '%s' -WindowStyle Hidden\"",
                $quotedPython,
                $quotedArgs,
                $quotedWorkdir
            );
            pclose(popen($command, 'r'));

            return;
        }

        $escapedPython = escapeshellarg($scraperPythonBin);
        $escapedWorkdir = escapeshellarg($scraperWorkdir);
        $escapedSource = escapeshellarg($safeSource);
        $command = "cd {$escapedWorkdir} && nohup {$escapedPython} -m src.runner --source {$escapedSource} --tenant-id {$tenantArg} >/dev/null 2>&1 &";
        exec($command);
    }
}
