<?php
function wrap_enum($file) {
    if (!file_exists($file)) return;
    $content = file_get_contents($file);
    
    // Match DB::statement("CREATE TYPE name AS ENUM ('a', 'b')")
    $content = preg_replace_callback('/DB::statement\(\s*[\'"]CREATE TYPE ([a-zA-Z0-9_]+) AS ENUM \((.*?)\)[\'"]\s*\);/s', function($m) {
        $name = $m[1];
        $values = $m[2];
        return "DB::statement(\"
            DO \\\$\\\$ BEGIN
                IF NOT EXISTS (SELECT 1 FROM pg_type WHERE typname = '$name') THEN
                    CREATE TYPE $name AS ENUM ($values);
                END IF;
            END \\\$\\\$;
        \");";
    }, $content);
    
    file_put_contents($file, $content);
}

wrap_enum('database/migrations/2024_01_01_000004_phase_d_runs_and_tasks.php');
wrap_enum('database/migrations/2024_01_01_000005_phase_e_resources_and_leases.php');
wrap_enum('database/migrations/2024_01_01_000006_phase_f_billing_payments_and_ledger.php');
wrap_enum('database/migrations/2024_01_01_000007_phase_g_notifications_and_providers.php');
wrap_enum('database/migrations/2024_01_01_000008_phase_h_platform_operations.php');
