<?php
$file = 'app/Http/Controllers/AuthController.php';
$content = file_get_contents($file);

$content = str_replace(
    "Auth::logout();\n        \$request->session()->invalidate();\n        \$request->session()->regenerateToken();",
    "if (method_exists(Auth::guard(), 'logout')) { Auth::logout(); }\n        \$request->session()->invalidate();\n        \$request->session()->regenerateToken();",
    $content
);

$content = str_replace(
    "Auth::logout();\n            \$request->session()->invalidate();\n            return",
    "if (method_exists(Auth::guard(), 'logout')) { Auth::logout(); }\n            \$request->session()->invalidate();\n            return",
    $content
);

file_put_contents($file, $content);
