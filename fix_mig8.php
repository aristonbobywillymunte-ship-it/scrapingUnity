<?php
$file = 'database/migrations/2024_01_01_000008_phase_h_platform_operations.php';
$content = file_get_contents($file);

$content = str_replace(
    "ALTER TABLE selector_versions ALTER COLUMN status SET DEFAULT 'QUEUED'::selector_version_status",
    "ALTER TABLE selector_versions ALTER COLUMN status SET DEFAULT 'DRAFT'::selector_version_status",
    $content
);

file_put_contents($file, $content);
