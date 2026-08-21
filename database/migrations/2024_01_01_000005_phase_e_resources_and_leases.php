<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void {
        DB::statement("
            DO \$\$ BEGIN
                IF NOT EXISTS (SELECT 1 FROM pg_type WHERE typname = 'resource_health_status') THEN
                    CREATE TYPE resource_health_status AS ENUM ('HEALTHY', 'DEGRADED', 'UNHEALTHY');
                END IF;
            END \$\$;
        ");

        Schema::create('resource_pools', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 255)->unique();
            $table->string('status', 50)->default('ACTIVE');
            $table->string('platform', 50)->nullable();
            $table->integer('max_concurrency')->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();
            $table->timestampTz('deleted_at')->nullable();
        });

        Schema::create('proxy_pools', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 255)->unique();
            $table->string('status', 50)->default('ACTIVE');
            $table->integer('max_concurrency')->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();
            $table->timestampTz('deleted_at')->nullable();
        });

        Schema::create('social_accounts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('platform', 50);
            $table->uuid('pool_id')->nullable();
            $table->string('health_status')->default('HEALTHY');
            $table->string('operational_state', 50)->default('AVAILABLE');
            $table->timestampTz('cooldown_until')->nullable();
            $table->jsonb('affinity_metadata')->nullable();
            $table->integer('max_concurrency')->default(1);
            $table->string('encrypted_credentials', 2048);
            $table->string('key_reference', 255);
            $table->string('encryption_version', 50);
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();
            $table->timestampTz('deleted_at')->nullable();
            $table->foreign('pool_id')->references('id')->on('resource_pools')->onDelete('restrict');
            $table->index('pool_id', 'idx_social_accounts_pool');
            $table->index('platform', 'idx_social_accounts_platform');
        });

        Schema::create('social_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('account_id');
            $table->string('encrypted_session', 4096);
            $table->string('key_reference', 255);
            $table->string('encryption_version', 50);
            $table->string('status', 50)->default('ACTIVE');
            $table->timestampTz('expires_at')->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();
            $table->timestampTz('revoked_at')->nullable();
            $table->foreign('account_id')->references('id')->on('social_accounts')->onDelete('restrict');
            $table->index('account_id', 'idx_social_sessions_account');
        });

        Schema::create('proxies', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('pool_id')->nullable();
            $table->string('host', 255);
            $table->integer('port');
            $table->string('health_status')->default('HEALTHY');
            $table->string('operational_state', 50)->default('AVAILABLE');
            $table->timestampTz('cooldown_until')->nullable();
            $table->integer('max_concurrency')->default(1);
            $table->string('encrypted_credentials', 2048)->nullable();
            $table->string('key_reference', 255)->nullable();
            $table->string('encryption_version', 50)->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();
            $table->timestampTz('deleted_at')->nullable();
            $table->foreign('pool_id')->references('id')->on('proxy_pools')->onDelete('restrict');
            $table->index('pool_id', 'idx_proxies_pool');
        });
        DB::statement('ALTER TABLE proxies ADD CONSTRAINT chk_proxy_port CHECK (port >= 1 AND port <= 65535)');

        Schema::create('account_leases', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('account_id');
            $table->uuid('task_id');
            $table->string('worker_identity', 255);
            $table->timestampTz('acquired_at')->useCurrent();
            $table->timestampTz('expires_at');
            $table->timestampTz('heartbeat_at')->nullable();
            $table->timestampTz('released_at')->nullable();
            $table->string('status', 50)->default('ACQUIRED');
            $table->string('release_reason', 255)->nullable();
            $table->foreign('account_id')->references('id')->on('social_accounts')->onDelete('restrict');
            $table->foreign('task_id')->references('id')->on('tasks')->onDelete('restrict');
        });
        DB::statement('CREATE INDEX idx_account_leases_active ON account_leases(account_id) WHERE released_at IS NULL');
        DB::statement('CREATE INDEX idx_account_leases_task ON account_leases(task_id)');
        DB::statement('CREATE INDEX idx_account_leases_recovery ON account_leases(expires_at, released_at) WHERE released_at IS NULL');

        Schema::create('proxy_leases', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('proxy_id');
            $table->uuid('task_id');
            $table->string('worker_identity', 255);
            $table->timestampTz('acquired_at')->useCurrent();
            $table->timestampTz('expires_at');
            $table->timestampTz('heartbeat_at')->nullable();
            $table->timestampTz('released_at')->nullable();
            $table->string('status', 50)->default('ACQUIRED');
            $table->string('release_reason', 255)->nullable();
            $table->foreign('proxy_id')->references('id')->on('proxies')->onDelete('restrict');
            $table->foreign('task_id')->references('id')->on('tasks')->onDelete('restrict');
        });
        DB::statement('CREATE INDEX idx_proxy_leases_active ON proxy_leases(proxy_id) WHERE released_at IS NULL');
        DB::statement('CREATE INDEX idx_proxy_leases_task ON proxy_leases(task_id)');
        DB::statement('CREATE INDEX idx_proxy_leases_recovery ON proxy_leases(expires_at, released_at) WHERE released_at IS NULL');

        DB::statement('ALTER TABLE task_attempts ADD CONSTRAINT task_attempts_account_lease_id_fkey FOREIGN KEY (account_lease_id) REFERENCES account_leases(id) ON DELETE RESTRICT');
        DB::statement('ALTER TABLE task_attempts ADD CONSTRAINT task_attempts_proxy_lease_id_fkey FOREIGN KEY (proxy_lease_id) REFERENCES proxy_leases(id) ON DELETE RESTRICT');

        DB::statement('ALTER TABLE social_accounts ALTER COLUMN health_status DROP DEFAULT');
        DB::statement('ALTER TABLE social_accounts ALTER COLUMN health_status TYPE resource_health_status USING health_status::resource_health_status');
        DB::statement("ALTER TABLE social_accounts ALTER COLUMN health_status SET DEFAULT 'HEALTHY'::resource_health_status");
        DB::statement('ALTER TABLE proxies ALTER COLUMN health_status DROP DEFAULT');
        DB::statement('ALTER TABLE proxies ALTER COLUMN health_status TYPE resource_health_status USING health_status::resource_health_status');
        DB::statement("ALTER TABLE proxies ALTER COLUMN health_status SET DEFAULT 'HEALTHY'::resource_health_status");

        DB::statement('ALTER TABLE social_accounts ALTER COLUMN health_status DROP DEFAULT');
        DB::statement('ALTER TABLE social_accounts ALTER COLUMN health_status TYPE resource_health_status USING health_status::resource_health_status');
        DB::statement("ALTER TABLE social_accounts ALTER COLUMN health_status SET DEFAULT 'HEALTHY'::resource_health_status");
        DB::statement('ALTER TABLE proxies ALTER COLUMN health_status DROP DEFAULT');
        DB::statement('ALTER TABLE proxies ALTER COLUMN health_status TYPE resource_health_status USING health_status::resource_health_status');
        DB::statement("ALTER TABLE proxies ALTER COLUMN health_status SET DEFAULT 'HEALTHY'::resource_health_status");
    }

    public function down(): void {
        DB::statement('ALTER TABLE task_attempts DROP CONSTRAINT IF EXISTS task_attempts_proxy_lease_id_fkey');
        DB::statement('ALTER TABLE task_attempts DROP CONSTRAINT IF EXISTS task_attempts_account_lease_id_fkey');
        Schema::dropIfExists('proxy_leases');
        Schema::dropIfExists('account_leases');
        Schema::dropIfExists('proxies');
        Schema::dropIfExists('social_sessions');
        Schema::dropIfExists('social_accounts');
        Schema::dropIfExists('proxy_pools');
        Schema::dropIfExists('resource_pools');
        DB::statement('DROP TYPE IF EXISTS resource_health_status');
    }
};
