<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MigrateRawDown extends Command
{
    protected $signature = 'migrate:raw-down';
    protected $description = 'Rollback raw SQL migrations';

    public function handle()
    {
        try {
            // Check if table exists first
            DB::table('raw_schema_migrations')->exists();
        } catch (\Exception $e) {
            $this->info("Tracking table does not exist. Nothing to rollback.");
            return 0;
        }

        $latestBatch = DB::table('raw_schema_migrations')->max('batch');
        if (!$latestBatch) {
            $this->info("No migrations to rollback.");
            return 0;
        }

        $migrations = DB::table('raw_schema_migrations')
            ->where('batch', $latestBatch)
            ->orderBy('migration_name', 'desc')
            ->get();

        foreach ($migrations as $migration) {
            $this->info("Rolling back: {$migration->migration_name}");
            
            $downFile = str_replace('.up.sql', '.down.sql', $migration->migration_name);
            $path = base_path('migrations/' . $downFile);

            if (file_exists($path)) {
                $sql = file_get_contents($path);
                try {
                    DB::transaction(function () use ($sql) {
                        DB::unprepared($sql);
                    });
                } catch (\Exception $e) {
                    $this->error("Failed rolling back {$migration->migration_name}: " . $e->getMessage());
                    return 1;
                }
            }

            DB::table('raw_schema_migrations')
                ->where('migration_name', $migration->migration_name)
                ->delete();

            $this->info("Rolled back: {$migration->migration_name}");
        }

        $this->info("Rollback complete.");
        return 0;
    }
}
