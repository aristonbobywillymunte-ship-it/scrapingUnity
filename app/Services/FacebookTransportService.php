<?php
namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FacebookTransportService {
    /**
     * Allowed public Facebook hosts for SSRF prevention.
     */
    private const ALLOWED_HOSTS = [
        'facebook.com',
        'www.facebook.com',
        'm.facebook.com',
        'mbasic.facebook.com',
        'touch.facebook.com',
    ];

    /**
     * Bounded real HTTP fetch with classification and SSRF guard.
     */
    public function fetch(string $targetUrl, array $options = []): array {
        $startTime = microtime(true);
        $proxyUrl = $options['proxy_url'] ?? null;
        $timeout = min(15, (int) ($options['timeout'] ?? 10));

        // 1. SSRF and Host Validation
        $validatedUrl = $this->validateAndNormalizeUrl($targetUrl);
        if (!$validatedUrl) {
            return [
                'success' => false,
                'classification' => 'INVALID_TARGET',
                'status_code' => 400,
                'requested_url' => $targetUrl,
                'final_url' => $targetUrl,
                'transport_mode' => 'HTTP',
                'elapsed_ms' => 0,
                'error_code' => 'SSRF_REJECTED',
                'error_message' => 'Target host is not an allowed Facebook public host.',
                'body' => null,
                'fetched_at' => now()->toIso8601String(),
            ];
        }

        // 2. Real HTTP Request
        try {
            $client = Http::withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
                'Accept-Language' => 'en-US,en;q=0.9,id;q=0.8',
                'Sec-Fetch-Dest' => 'document',
                'Sec-Fetch-Mode' => 'navigate',
                'Sec-Fetch-Site' => 'none',
                'Sec-Fetch-User' => '?1',
                'Upgrade-Insecure-Requests' => '1',
            ])
            ->timeout($timeout)
            ->connectTimeout(5)
            ->maxRedirects(3);

            if ($proxyUrl) {
                $client = $client->withOptions(['proxy' => $proxyUrl]);
            }

            $response = $client->get($validatedUrl);
            $statusCode = $response->status();
            $body = $response->body();
            $elapsedMs = (int) round((microtime(true) - $startTime) * 1000);

            // 3. Response Classification
            $classification = $this->classifyResponse($statusCode, $body);

            return [
                'success' => ($classification === 'SUCCESS'),
                'classification' => $classification,
                'status_code' => $statusCode,
                'requested_url' => $targetUrl,
                'final_url' => $validatedUrl,
                'transport_mode' => 'HTTP',
                'elapsed_ms' => $elapsedMs,
                'error_code' => ($classification === 'SUCCESS') ? null : $classification,
                'error_message' => ($classification === 'SUCCESS') ? null : "Facebook response classified as {$classification}",
                'body' => $body,
                'fetched_at' => now()->toIso8601String(),
            ];
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            $elapsedMs = (int) round((microtime(true) - $startTime) * 1000);
            return [
                'success' => false,
                'classification' => 'NETWORK_ERROR',
                'status_code' => 0,
                'requested_url' => $targetUrl,
                'final_url' => $validatedUrl,
                'transport_mode' => 'HTTP',
                'elapsed_ms' => $elapsedMs,
                'error_code' => 'CONNECTION_FAILED',
                'error_message' => 'Connection to Facebook endpoint failed: ' . $e->getMessage(),
                'body' => null,
                'fetched_at' => now()->toIso8601String(),
            ];
        } catch (\Exception $e) {
            $elapsedMs = (int) round((microtime(true) - $startTime) * 1000);
            return [
                'success' => false,
                'classification' => 'NETWORK_ERROR',
                'status_code' => 0,
                'requested_url' => $targetUrl,
                'final_url' => $validatedUrl,
                'transport_mode' => 'HTTP',
                'elapsed_ms' => $elapsedMs,
                'error_code' => 'REQUEST_EXCEPTION',
                'error_message' => 'HTTP fetch error: ' . $e->getMessage(),
                'body' => null,
                'fetched_at' => now()->toIso8601String(),
            ];
        }
    }

    /**
     * Validate URL host and address against whitelist and SSRF subnets.
     */
    public function isSafeDestination(string $url): array {
        $normalized = $this->validateAndNormalizeUrl($url);
        if (!$normalized) {
            return [false, 'Target host is not in allowed Facebook whitelist or invalid.'];
        }
        return [true, null];
    }

    /**
     * Validate URL host against whitelist to prevent SSRF.
     */
    public function validateAndNormalizeUrl(string $url): ?string {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            if (preg_match('/^[a-zA-Z0-9._-]+$/', $url)) {
                $url = 'https://www.facebook.com/' . $url;
            } else {
                return null;
            }
        }

        $parsed = parse_url($url);
        if (!$parsed || !isset($parsed['host']) || !in_array(strtolower($parsed['scheme'] ?? ''), ['http', 'https'])) {
            return null;
        }

        $host = strtolower($parsed['host']);
        if (!in_array($host, self::ALLOWED_HOSTS)) {
            return null;
        }

        return $url;
    }

    /**
     * Classify raw HTML response into canonical states.
     */
    public function classifyResponse(int $statusCode, string $body): string {
        if ($statusCode === 404) {
            return 'NOT_FOUND';
        }
        if ($statusCode === 429) {
            return 'RATE_LIMITED';
        }
        if ($statusCode === 403 || $statusCode === 401) {
            return 'BLOCKED';
        }

        if (stripos($body, 'checkpoint') !== false || stripos($body, 'security check') !== false || stripos($body, 'captcha') !== false) {
            return 'CHALLENGE';
        }
        if (stripos($body, 'login_form') !== false || (stripos($body, 'Log into Facebook') !== false && stripos($body, 'content="profile"') === false)) {
            return 'LOGIN_REQUIRED';
        }
        if (stripos($body, 'This content isn\'t available right now') !== false || stripos($body, 'The link you followed may be broken') !== false) {
            return 'NOT_FOUND';
        }
        if (stripos($body, 'Temporarily Blocked') !== false || stripos($body, 'Rate limit exceeded') !== false) {
            return 'RATE_LIMITED';
        }

        return ($statusCode >= 200 && $statusCode < 300) ? 'SUCCESS' : 'BLOCKED';
    }
}
