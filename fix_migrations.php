<?php
// We will write migration 4, 5, 6, 7, 8 using string instead of addColumn, then add ALTER TABLE TYPE at the end of up()

function fix_migration($file, $types) {
    if (!file_exists($file)) return;
    $content = file_get_contents($file);
    
    $alters = "";
    foreach ($types as $type => $columns) {
        foreach ($columns as $table => $cols) {
            foreach ($cols as $col) {
                // Replace addColumn with string
                $content = str_replace("\$table->addColumn('$type', '$col')", "\$table->string('$col')", $content);
                // Add alter statement
                $alters .= "\n        DB::statement('ALTER TABLE $table ALTER COLUMN $col TYPE $type USING $col::$type');";
            }
        }
    }
    
    if ($alters) {
        $content = str_replace("    }\n\n    public function down(): void {", $alters . "\n    }\n\n    public function down(): void {", $content);
    }
    file_put_contents($file, $content);
}

fix_migration('database/migrations/2024_01_01_000004_phase_d_runs_and_tasks.php', [
    'run_status' => ['runs' => ['status']],
    'task_status' => ['tasks' => ['status']],
    'error_category' => ['runs' => ['error_category'], 'tasks' => ['error_category'], 'task_attempts' => ['error_category'], 'dead_letter_queue_records' => ['error_category']]
]);

fix_migration('database/migrations/2024_01_01_000005_phase_e_resources_and_leases.php', [
    'resource_health_status' => ['social_accounts' => ['health_status'], 'proxies' => ['health_status']]
]);

fix_migration('database/migrations/2024_01_01_000006_phase_f_billing_payments_and_ledger.php', [
    'credit_transaction_type' => ['credit_ledger' => ['transaction_type']],
    'refund_status' => ['refund_approvals' => ['status'], 'refunds' => ['status']]
]);

fix_migration('database/migrations/2024_01_01_000007_phase_g_notifications_and_providers.php', [
    'notification_delivery_status' => ['logical_notifications' => ['status'], 'notification_deliveries' => ['status'], 'webhook_deliveries' => ['status']]
]);

fix_migration('database/migrations/2024_01_01_000008_phase_h_platform_operations.php', [
    'export_status' => ['exports' => ['status']],
    'selector_version_status' => ['selector_versions' => ['status']]
]);
