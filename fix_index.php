<?php
$file = 'database/migrations/2024_01_01_000008_phase_h_platform_operations.php';
$content = file_get_contents($file);

$content = str_replace(
    "DB::statement(\"CREATE UNIQUE INDEX idx_selector_versions_active ON selector_versions(selector_id) WHERE status = 'ACTIVE'\");",
    "// index recreated below",
    $content
);

$content = str_replace(
    "DB::statement('ALTER TABLE selector_versions ALTER COLUMN status TYPE selector_version_status USING status::selector_version_status');\n        DB::statement(\"ALTER TABLE selector_versions ALTER COLUMN status SET DEFAULT 'DRAFT'::selector_version_status\");",
    "DB::statement('ALTER TABLE selector_versions ALTER COLUMN status TYPE selector_version_status USING status::selector_version_status');\n        DB::statement(\"ALTER TABLE selector_versions ALTER COLUMN status SET DEFAULT 'DRAFT'::selector_version_status\");\n        DB::statement(\"CREATE UNIQUE INDEX idx_selector_versions_active ON selector_versions(selector_id) WHERE status = 'ACTIVE'\");",
    $content
);

file_put_contents($file, $content);
