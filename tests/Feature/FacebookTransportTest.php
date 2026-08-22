<?php

use App\Services\FacebookTransportService;
use App\Services\FacebookParserService;
use App\Collectors\FacebookPostsCollector;
use Illuminate\Support\Facades\Http;

test('FacebookTransportService validates and normalizes safe public Facebook hosts', function () {
    $transport = new FacebookTransportService();

    expect($transport->validateAndNormalizeUrl('zuck'))->toBe('https://www.facebook.com/zuck');
    expect($transport->validateAndNormalizeUrl('https://www.facebook.com/zuck'))->toBe('https://www.facebook.com/zuck');
    expect($transport->validateAndNormalizeUrl('https://m.facebook.com/zuck'))->toBe('https://m.facebook.com/zuck');
});

test('FacebookTransportService rejects SSRF destinations and non-whitelisted hosts', function () {
    $transport = new FacebookTransportService();

    expect($transport->validateAndNormalizeUrl('http://127.0.0.1:8000/admin'))->toBeNull();
    expect($transport->validateAndNormalizeUrl('http://localhost:8000'))->toBeNull();
    expect($transport->validateAndNormalizeUrl('http://169.254.169.254/latest/meta-data'))->toBeNull();
    expect($transport->validateAndNormalizeUrl('https://evil.com/facebook.com'))->toBeNull();

    $res = $transport->fetch('http://127.0.0.1:8000/admin');
    expect($res['success'])->toBeFalse();
    expect($res['classification'])->toBe('INVALID_TARGET');
    expect($res['error_code'])->toBe('SSRF_REJECTED');
});

test('FacebookTransportService classifies responses into canonical states', function () {
    $transport = new FacebookTransportService();

    expect($transport->classifyResponse(404, 'Not found'))->toBe('NOT_FOUND');
    expect($transport->classifyResponse(429, 'Rate limit exceeded'))->toBe('RATE_LIMITED');
    expect($transport->classifyResponse(403, 'Forbidden'))->toBe('BLOCKED');
    expect($transport->classifyResponse(200, '<html><body>Please complete the security check captcha</body></html>'))->toBe('CHALLENGE');
    expect($transport->classifyResponse(200, '<html><body>Log into Facebook to see this page</body></html>'))->toBe('LOGIN_REQUIRED');
    expect($transport->classifyResponse(200, '<html><head><meta property="og:title" content="Mark Zuckerberg"></head><body>Profile</body></html>'))->toBe('SUCCESS');
});

test('FacebookParserService parses OpenGraph and DOM structures without synthetic fields', function () {
    $parser = new FacebookParserService();
    $mockHtml = '<html><head><meta property="og:title" content="Zuck Post - Official"><meta property="og:description" content="Building AI and Open Source infrastructure."><meta property="og:url" content="https://www.facebook.com/zuck/posts/101"></head><body></body></html>';

    $records = $parser->parsePosts($mockHtml, 'https://www.facebook.com/zuck/posts/101');
    expect($records)->toHaveCount(1);
    expect($records[0]['platform'])->toBe('FACEBOOK');
    expect($records[0]['entity_type'])->toBe('POST');
    expect($records[0]['payload']['text_content'])->toBe('Building AI and Open Source infrastructure.');
    expect($records[0]['payload']['raw_extracted'])->toBeTrue();
});

test('FacebookPostsCollector uses real transport in non-testing mode and rejects blocked responses', function () {
    Http::fake([
        'https://www.facebook.com/test_user' => Http::response('<html><body>Log into Facebook</body></html>', 200),
    ]);

    $collector = new FacebookPostsCollector();
    $task = (object)[
        'id' => (string) \Illuminate\Support\Str::uuid(),
        'payload' => [
            'target' => 'https://www.facebook.com/test_user',
            'force_real_transport' => true,
        ]
    ];

    expect(fn() => $collector->collect($task))->toThrow(Exception::class, 'Facebook fetch failed: LOGIN_REQUIRED');
});
