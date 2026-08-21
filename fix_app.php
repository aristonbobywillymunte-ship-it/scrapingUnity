<?php
$file = 'bootstrap/app.php';
$content = file_get_contents($file);

$content = str_replace(
    "->withMiddleware(function (Middleware \$middleware): void {
        //
    })",
    "->withMiddleware(function (Middleware \$middleware): void {
        \$middleware->api(append: [
            \Illuminate\Session\Middleware\StartSession::class,
        ]);
    })",
    $content
);

file_put_contents($file, $content);
