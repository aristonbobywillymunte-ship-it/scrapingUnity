<?php

use App\Services\FacebookTransportService;

test('URL Normalization', function () {
    $t = new FacebookTransportService();
    expect($t->validateAndNormalizeUrl('zuck'))->toBe('https://www.facebook.com/zuck');
    expect($t->validateAndNormalizeUrl('https://www.facebook.com/zuck'))->toBe('https://www.facebook.com/zuck');
    expect($t->validateAndNormalizeUrl('https://m.facebook.com/zuck'))->toBe('https://m.facebook.com/zuck');
});

test('SSRF Rejection', function () {
    $t = new FacebookTransportService();
    expect($t->validateAndNormalizeUrl('http://127.0.0.1:8000'))->toBeNull();
    expect($t->validateAndNormalizeUrl('http://localhost:8000'))->toBeNull();
    expect($t->validateAndNormalizeUrl('http://169.254.169.254/latest/meta-data'))->toBeNull();
    expect($t->validateAndNormalizeUrl('https://evil.com/facebook.com'))->toBeNull();

    $res = $t->fetch('http://127.0.0.1:8000');
    expect($res['success'])->toBeFalse();
    expect($res['classification'])->toBe('INVALID_TARGET');
    expect($res['error_code'])->toBe('SSRF_REJECTED');
});

test('Response Classification', function () {
    $t = new FacebookTransportService();
    expect($t->classifyResponse(404, ''))->toBe('NOT_FOUND');
    expect($t->classifyResponse(429, ''))->toBe('RATE_LIMITED');
    expect($t->classifyResponse(403, ''))->toBe('BLOCKED');
    expect($t->classifyResponse(200, 'Please complete the security check captcha'))->toBe('CHALLENGE');
    expect($t->classifyResponse(200, 'Log into Facebook to see this'))->toBe('LOGIN_REQUIRED');
    expect($t->classifyResponse(200, '<html><head><meta property="og:title" content="Zuck"></head></html>'))->toBe('SUCCESS');
});

test('Safe Destination Validation', function () {
    $t = new FacebookTransportService();
    [$isSafe, $err] = $t->isSafeDestination('https://www.facebook.com/zuck');
    expect($isSafe)->toBeTrue();
    expect($err)->toBeNull();

    [$isSafeBad, $errBad] = $t->isSafeDestination('http://127.0.0.1/admin');
    expect($isSafeBad)->toBeFalse();
    expect($errBad)->toContain('not in allowed Facebook whitelist');
});
