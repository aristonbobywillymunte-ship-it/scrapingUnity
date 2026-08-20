<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MigrateRaw extends Command
{
    protected $signature = 'migrate:raw';
    protected $description = 'Apply raw SQL migrations';

    public function handle()
    {
        $this->ensureTrackingTable();

        $files = glob(base_path('migrations/*.up.sql'));
        sort($files);

        $batch = DB::table('raw_schema_migrations')->max('batch') + 1;

        foreach ($files as $file) {
            $name = basename($file);

            $exists = DB::table('raw_schema_migrations')->where('migration_name', $name)->exists();
            if ($exists) {
                continue;
            }

            $this->info("Migrating: {$name}");
            $sql = file_get_contents($file);

            try {
                DB::transaction(function () use ($sql) {
                    DB::unprepared($sql);
                });

                DB::table('raw_schema_migrations')->insert([
                    'migration_name' => $name,
                    'batch' => $batch,
                    'applied_at' => now(),
                ]);
                $this->info("Migrated: {$name}");
            } catch (\Exception $e) {
                $this->error("Failed migrating {$name}: " . $e->getMessage());
                return 1;
            }
        }

        $this->info("All raw migrations applied.");
        return 0;
    }

    private function ensureTrackingTable()
    {
        $sql = "
            CREATE TABLE IF NOT EXISTS raw_schema_migrations (
                migration_name VARCHAR(255) PRIMARY KEY,
                batch INT NOT NULL,
                applied_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
            );
        ";
        DB::unprepared($sql);
    }
}
