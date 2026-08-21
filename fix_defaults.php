<?php

$files = glob('database/migrations/2024_01_01_00000*.php');

foreach ($files as $file) {
    $content = file_get_contents($file);
    
    // Replace: DB::statement('ALTER TABLE runs ALTER COLUMN status TYPE run_status USING status::run_status');
    // With:
    // DB::statement('ALTER TABLE runs ALTER COLUMN status DROP DEFAULT');
    // DB::statement('ALTER TABLE runs ALTER COLUMN status TYPE run_status USING status::run_status');
    // DB::statement("ALTER TABLE runs ALTER COLUMN status SET DEFAULT 'QUEUED'::run_status");
    
    $content = preg_replace_callback('/DB::statement\(\'ALTER TABLE ([a-zA-Z0-9_]+) ALTER COLUMN ([a-zA-Z0-9_]+) TYPE ([a-zA-Z0-9_]+) USING (.*?)\'\);/', function($m) use ($content) {
        $table = $m[1];
        $col = $m[2];
        $type = $m[3];
        $using = $m[4];
        
        // Find default value if any from the Schema::create block
        $default = null;
        if (preg_match('/\$table->string\(\'' . $col . '\'\)->default\(\'(.*?)\'\)/', $content, $dm)) {
            $default = $dm[1];
        } elseif (preg_match('/\$table->string\(\'' . $col . '\', \d+\)->default\(\'(.*?)\'\)/', $content, $dm)) {
            $default = $dm[1];
        }
        
        $res = "DB::statement('ALTER TABLE $table ALTER COLUMN $col DROP DEFAULT');\n";
        $res .= "        DB::statement('ALTER TABLE $table ALTER COLUMN $col TYPE $type USING $using');";
        
        if ($default !== null) {
            $res .= "\n        DB::statement(\"ALTER TABLE $table ALTER COLUMN $col SET DEFAULT '$default'::$type\");";
        }
        
        return $res;
    }, $content);
    
    file_put_contents($file, $content);
}
