<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessScrapingJob;
use App\Models\ScraperJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class ScraperController extends Controller
{
    public function index(): View
    {
        $logFile = storage_path('logs/scraper.log');
        $recentLogs = [];

        if (file_exists($logFile)) {
            $lines = file($logFile);
            $recentLogs = \array_slice(array_reverse($lines), 0, 50);
        }

        // Get recent scraper jobs with their products
        $recentJobs = ScraperJob::with('product')
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get();

        // Get statistics
        $stats = [
            'total_jobs' => ScraperJob::count(),
            'completed_jobs' => ScraperJob::where('status', 'completed')->count(),
            'failed_jobs' => ScraperJob::where('status', 'failed')->count(),
            'pending_jobs' => ScraperJob::whereIn('status', ['queued', 'running'])->count(),
        ];

        return view('admin.scraper.index', compact('recentLogs', 'recentJobs', 'stats'));
    }

    public function startScraping(Request $request): RedirectResponse
    {
        $request->validate([
            'urls' => 'required|string',
        ]);

        $urls = array_filter(
            array_map('trim', explode("\n", $request->urls)),
            static function ($url) {
                return !empty($url) && filter_var($url, \FILTER_VALIDATE_URL);
            }
        );

        if (empty($urls)) {
            return back()->with('error', 'No valid URLs provided.');
        }

        $batchId = uniqid('batch_');
        Log::channel('scraper')->info("🚀 NEW BATCH: {$batchId} - " . \count($urls) . ' URLs');

        $jobsDispatched = 0;
        foreach ($urls as $index => $url) {
            try {
                // Create scraper job record first
                $scraperJob = ScraperJob::create([
                    'batch_id' => $batchId,
                    'job_number' => $index + 1,
                    'url' => $url,
                    'status' => 'queued',
                ]);

                // Dispatch the job with the scraper job ID
                ProcessScrapingJob::dispatch($url, $batchId, $index + 1, $scraperJob->id);
                ++$jobsDispatched;

                Log::channel('scraper')->info('✅ Job #' . ($index + 1) . ' queued (ScraperJob ID: ' . $scraperJob->id . ')');
            } catch (\Exception $e) {
                Log::channel('scraper')->error('❌ Failed to queue job: ' . $e->getMessage());
            }
        }

        return back()->with('success', "Scraping started! {$jobsDispatched} jobs dispatched to queue. Check the Status Dashboard below.");
    }

    public function getLogs(): JsonResponse
    {
        $logFile = storage_path('logs/scraper.log');
        $logs = [];

        if (file_exists($logFile)) {
            $lines = file($logFile);
            $logs = \array_slice(array_reverse($lines), 0, 100);
        }

        return response()->json(['logs' => $logs]);
    }

    public function getJobs(): JsonResponse
    {
        $jobs = ScraperJob::with('product')
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get()
            ->map(function ($job) {
                return [
                    'id' => $job->id,
                    'batch_id' => $job->batch_id,
                    'job_number' => $job->job_number,
                    'url' => $job->url,
                    'store_adapter' => $job->store_adapter,
                    'status' => $job->status,
                    'product_id' => $job->product_id,
                    'product_name' => $job->product ? $job->product->name : null,
                    'error_message' => $job->error_message,
                    'duration' => $job->getDuration(),
                    'created_at' => $job->created_at ? $job->created_at->format('Y-m-d H:i:s') : null,
                    'started_at' => $job->started_at ? $job->started_at->format('Y-m-d H:i:s') : null,
                    'completed_at' => $job->completed_at ? $job->completed_at->format('Y-m-d H:i:s') : null,
                ];
            });

        $stats = [
            'total_jobs' => ScraperJob::count(),
            'completed_jobs' => ScraperJob::where('status', 'completed')->count(),
            'failed_jobs' => ScraperJob::where('status', 'failed')->count(),
            'pending_jobs' => ScraperJob::whereIn('status', ['queued', 'running'])->count(),
        ];

        return response()->json([
            'jobs' => $jobs,
            'stats' => $stats,
        ]);
    }

    public function clearLogs(): RedirectResponse
    {
        $logFile = storage_path('logs/scraper.log');

        if (file_exists($logFile)) {
            file_put_contents($logFile, '');
        }

        return back()->with('success', 'Logs cleared successfully.');
    }

    public function clearJobs(): RedirectResponse
    {
        // Only clear completed and failed jobs, keep pending ones
        ScraperJob::whereIn('status', ['completed', 'failed'])->delete();

        return back()->with('success', 'Completed and failed jobs cleared successfully.');
    }
}
