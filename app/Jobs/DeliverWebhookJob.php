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

        // Complete SSRF Security Service implementation (inline)
        $parsedUrl = parse_url($webhook->target_url);
        if (!$parsedUrl || !isset($parsedUrl['host']) || !in_array($parsedUrl['scheme'] ?? '', ['https', 'http'])) {
            $this->fail(new \Exception("Blocked SSRF attempt: Invalid URL or missing host/scheme."));
            return;
        }

        $host = $parsedUrl['host'];
        $records = dns_get_record($host, DNS_A + DNS_AAAA);
        if (empty($records)) {
            $this->fail(new \Exception("Blocked SSRF attempt: Unresolvable host."));
            return;
        }

        foreach ($records as $record) {
            $ip = $record['ip'] ?? $record['ipv6'] ?? null;
            if (!$ip) continue;

            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                $this->fail(new \Exception("Blocked SSRF attempt: Private/internal IP resolved for webhook target."));
                return;
            }
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
            $url = $webhook->target_url;
            $maxRedirects = 3;
            $response = null;

            for ($i = 0; $i <= $maxRedirects; $i++) {
                $response = Http::timeout(10)
                    ->withOptions(['allow_redirects' => false])
                    ->withHeaders([
                        'X-ScrapingUnity-Event' => $this->eventType,
                        'X-ScrapingUnity-Signature' => "t={$timestamp},v1={$signature}",
                    ])
                    ->post($url, $payload);

                if (in_array($response->status(), [301, 302, 303, 307, 308])) {
                    $url = $response->header('Location');
                    if (!$url) throw new \Exception("Redirect missing Location header");
                    
                    // SSRF check on redirect
                    $parsedUrl = parse_url($url);
                    if (!$parsedUrl || !isset($parsedUrl['host']) || !in_array($parsedUrl['scheme'] ?? '', ['https', 'http'])) {
                        throw new \Exception("Blocked SSRF attempt: Invalid redirect URL.");
                    }
                    $host = $parsedUrl['host'];
                    $records = dns_get_record($host, DNS_A + DNS_AAAA);
                    if (empty($records)) throw new \Exception("Blocked SSRF attempt: Unresolvable redirect host.");
                    foreach ($records as $record) {
                        $ip = $record['ip'] ?? $record['ipv6'] ?? null;
                        if ($ip && filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                            throw new \Exception("Blocked SSRF attempt: Private/internal IP on redirect.");
                        }
                    }
                } else {
                    break;
                }
            }

            if (!$response || in_array($response->status(), [301, 302, 303, 307, 308])) {
                throw new \Exception("Too many redirects.");
            }

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
                throw new \Exception("Webhook received non-2xx response");
            }

        } catch (\Exception $e) {
            DB::table('webhook_deliveries')->insert([
                'id' => (string) Str::uuid(),
                'webhook_id' => $this->webhookId,
                'event_type' => $this->eventType,
                'payload' => $jsonPayload,
                'response_status' => 0,
                'response_body' => 'DELIVERY_FAILED', // sanitized error code
                'successful' => false,
                'created_at' => now(),
            ]);
            throw $e; // Trigger bounded retry
        }
    }
}
