<?php
$file = 'tests/Feature/SecurityFinalSuiteTest.php';
$content = file_get_contents($file);

// Find all withSession(session()->all()) and replace with withHeader('Authorization', 'Bearer ' . $token)
// But wait, some of them might not have $token defined.
// Wait, in line 29: $token = $login->json('token'); is already there!

$content = str_replace(
    "->withSession(session()->all())",
    "->withHeader('Authorization', 'Bearer ' . \$token)",
    $content
);

file_put_contents($file, $content);
