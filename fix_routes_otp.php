<?php
$file = 'routes/api.php';
$content = file_get_contents($file);

$content = str_replace(
    "Route::post('/v1/auth/otp/request', [OtpController::class, 'request']);\nRoute::post('/v1/auth/otp/complete', [OtpController::class, 'complete']);",
    "Route::post('/v1/auth/otp/request', [OtpController::class, 'request']);\nRoute::post('/v1/auth/otp/complete', [OtpController::class, 'complete']);\nRoute::post('/v1/auth/password-reset/request', [OtpController::class, 'request']);\nRoute::post('/v1/auth/password-reset/complete', [OtpController::class, 'complete']);",
    $content
);

file_put_contents($file, $content);
