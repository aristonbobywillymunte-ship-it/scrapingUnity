<?php
$file = 'app/Services/RunEngineService.php';
$content = file_get_contents($file);

$content = str_replace(
    "if (\$capability !== 'SCRAPER_X') {\n            throw new \Exception(\"Unsupported capability\");\n        }",
    "if (!CapabilityRegistry::isValid(\$capability)) {\n            throw new \Exception(\"Unsupported capability\");\n        }",
    $content
);

file_put_contents($file, $content);
