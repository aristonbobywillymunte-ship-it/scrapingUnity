<?php
$file = 'app/Http/Controllers/RunController.php';
$content = file_get_contents($file);

$gateCheck = "        \$orgId = \$request->header('X-Organization-Id');\n        if (!\\Illuminate\\Support\\Facades\\Gate::allows('access-org', \$orgId)) {\n            return response()->json(['error' => 'Forbidden'], 403);\n        }";

$content = str_replace(
    "\$orgId = \$request->header('X-Organization-Id');\n        if (!\$orgId) return response()->json(['error' => 'Missing Org'], 400);",
    $gateCheck,
    $content
);

file_put_contents($file, $content);
