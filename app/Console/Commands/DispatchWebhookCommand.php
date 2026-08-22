<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Jobs\DeliverWebhookJob;
use Illuminate\Support\Facades\DB;

class DispatchWebhookCommand extends Command
{
    protected $signature = 'webhook:dispatch {job_id}';
    protected $description = 'Dispatch webhooks for a completed job';

    public function handle()
    {
        $jobId = $this->argument('job_id');
        $job = DB::table('scraping_jobs')->where('id', $jobId)->first();
        if (!$job) return;

        $webhooks = DB::table('webhooks')->where('user_id', $job->user_id)->where('status', 'ACTIVE')->get();
        
        $eventType = 'job.' . strtolower($job->status);
        if (!in_array($eventType, ['job.completed', 'job.partial', 'job.failed'])) {
            return;
        }

        foreach ($webhooks as $webhook) {
            $events = is_string($webhook->events) ? json_decode($webhook->events, true) : $webhook->events;
            if (is_array($events) && (in_array('*', $events) || in_array($eventType, $events))) {
                DeliverWebhookJob::dispatch($webhook->id, $eventType, [
                    'job_id' => $job->id,
                    'platform' => $job->platform,
                    'operation' => $job->operation,
                    'status' => $job->status,
                ]);
            }
        }
    }
}
