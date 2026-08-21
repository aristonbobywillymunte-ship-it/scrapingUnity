<?php
$file = 'routes/api.php';
$content = file_get_contents($file);

$content = str_replace(
    "Route::get('/v1/runs/{id}', [RunController::class, 'getRun']);",
    "Route::get('/v1/runs', [RunController::class, 'listRuns']);\n    Route::get('/v1/runs/{id}', [RunController::class, 'getRun']);",
    $content
);

file_put_contents($file, $content);
