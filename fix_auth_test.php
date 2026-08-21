<?php
$file = 'tests/Feature/AuthTest.php';
$content = file_get_contents($file);
$content = str_replace(
    "assertJsonPath('email', 'me@a.com')",
    "assertJsonPath('user.email', 'me@a.com')",
    $content
);
file_put_contents($file, $content);
