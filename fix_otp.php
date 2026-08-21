<?php
$file = 'app/Http/Controllers/OtpController.php';
$content = file_get_contents($file);

$content = str_replace(
    "        );\n            throw \$e;\n        }",
    "        );",
    $content
);

file_put_contents($file, $content);
