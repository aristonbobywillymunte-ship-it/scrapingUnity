<?php
$files = glob('tests/Feature/*.php');
foreach ($files as $file) {
    $content = file_get_contents($file);
    // Fix roles insert
    $content = preg_replace('/"created_at" => now\(\),\s*"updated_at" => now\(\)/', '', $content);
    $content = str_replace("'created_at' => now(), 'updated_at' => now()", "", $content);
    $content = str_replace("['id' => 'owner', 'description' => 'Owner', 'is_internal_role' => false, 'created_at' => now(), 'updated_at' => now()]", "['id' => 'owner', 'description' => 'Owner', 'is_internal_role' => false]", $content);
    
    // In BillingE2ETest, maybe it doesn't insert roles at all. We should add it.
    if (strpos($file, 'BillingE2ETest') !== false && strpos($content, "table('roles')") === false) {
        $content = str_replace("test('users cannot hit add credits', function () {", "test('users cannot hit add credits', function () {\n    \Illuminate\Support\Facades\DB::table('roles')->insertOrIgnore([['id' => 'owner', 'description' => 'Owner', 'is_internal_role' => false]]);\n", $content);
    }
    
    // In RetryE2ETest, change failed_jobs to dead_letter_queue_records
    if (strpos($file, 'RetryE2ETest') !== false) {
        $content = str_replace("DB::table('failed_jobs')", "DB::table('dead_letter_queue_records')", $content);
    }
    
    file_put_contents($file, $content);
}
