<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use App\Models\Webhook;

class DeliverWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $backoff = [30, 120, 300];

    protected $webhookId;
    protected $eventData;
    protected $eventType;

    public function __construct($webhookId, $eventType, $eventData)
    {
        $this->webhookId = $webhookId;
        $this->eventType = $eventType;
        $this->eventData = $eventData;
    }

    public function handle()
    {
        $webhook = DB::table('webhooks')->where('id', $this->webhookId)->where('status', 'ACTIVE')->first();
        if (!$webhook) return;

        // SSRF Check - Ensure URL is not internal/private
        $parsedUrl = parse_url($webhook->target_url);
        if (!$parsedUrl || !isset($parsedUrl['host'])) {
            return;
        }

        $ip = gethostbyname($parsedUrl['host']);
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            $this->fail(new \Exception("Blocked SSRF attempt: Private/internal IP resolved for webhook target."));
            return;
        }

        $eventId = (string) Str::uuid();
        $timestamp = time();

        $payload = [
            'id' => $eventId,
            'event' => $this->eventType,
            'timestamp' => $timestamp,
            'data' => $this->eventData
        ];

        $jsonPayload = json_encode($payload);
        $signature = hash_hmac('sha256', $timestamp . '.' . $jsonPayload, $webhook->secret_key);

        try {
            $response = Http::timeout(10)
                ->withHeaders([
                    'X-ScrapingUnity-Event' => $this->eventType,
                    'X-ScrapingUnity-Signature' => "t={$timestamp},v1={$signature}",
                ])
                ->post($webhook->target_url, $payload);

            DB::table('webhook_deliveries')->insert([
                'id' => (string) Str::uuid(),
                'webhook_id' => $this->webhookId,
                'event_type' => $this->eventType,
                'payload' => $jsonPayload,
                'response_status' => $response->status(),
                'response_body' => Str::limit($response->body(), 1000),
                'successful' => $response->successful(),
                'created_at' => now(),
            ]);

            if (!$response->successful()) {
                throw new \Exception("Webhook received non-2xx response: " . $response->status());
            }

        } catch (\Exception $e) {
            DB::table('webhook_deliveries')->insert([
                'id' => (string) Str::uuid(),
                'webhook_id' => $this->webhookId,
                'event_type' => $this->eventType,
                'payload' => $jsonPayload,
                'response_status' => 0,
                'response_body' => Str::limit($e->getMessage(), 1000),
                'successful' => false,
                'created_at' => now(),
            ]);
            throw $e; // Trigger bounded retry
        }
    }
}
