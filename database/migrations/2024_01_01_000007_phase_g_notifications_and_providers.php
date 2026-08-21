<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void {
        DB::statement("
            DO \$\$ BEGIN
                IF NOT EXISTS (SELECT 1 FROM pg_type WHERE typname = 'notification_delivery_status') THEN
                    CREATE TYPE notification_delivery_status AS ENUM ('QUEUED', 'SENDING', 'DELIVERED', 'FAILED');
                END IF;
            END \$\$;
        ");

        Schema::create('provider_configs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('provider_name', 100);
            $table->string('provider_type', 100);
            $table->string('status', 50)->default('ACTIVE');
            $table->jsonb('safe_metadata')->nullable();
            $table->string('encrypted_credentials', 2048);
            $table->string('key_reference', 255);
            $table->string('encryption_version', 50);
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();
        });

        Schema::create('wa_pools', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 255);
            $table->string('status', 50)->default('ACTIVE');
            $table->jsonb('concurrency_config')->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();
            $table->timestampTz('deleted_at')->nullable();
        });

        Schema::create('wa_instances', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('pool_id');
            $table->string('name', 255);
            $table->string('status', 50)->default('ACTIVE');
            $table->string('health_state', 50)->default('HEALTHY');
            $table->uuid('provider_config_id')->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();
            $table->foreign('pool_id')->references('id')->on('wa_pools')->onDelete('restrict');
            $table->foreign('provider_config_id')->references('id')->on('provider_configs')->onDelete('restrict');
        });

        Schema::create('notification_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->string('event_type', 255);
            $table->string('event_version', 50);
            $table->string('dedupe_key', 255);
            $table->jsonb('safe_payload')->nullable();
            $table->timestampTz('occurred_at')->useCurrent();
            $table->timestampTz('created_at')->useCurrent();
            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('restrict');
            $table->unique(['organization_id', 'dedupe_key']);
            $table->unique(['id', 'organization_id']);
        });

        Schema::create('notification_rules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->string('event_type', 255);
            $table->jsonb('channels');
            $table->string('status', 50)->default('ACTIVE');
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();
            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
        });

        Schema::create('notification_templates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id')->nullable();
            $table->string('channel', 50);
            $table->string('event_type', 255);
            $table->text('template_content');
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();
            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
        });

        Schema::create('logical_notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->uuid('event_id');
            $table->uuid('recipient_id');
            $table->string('channel', 50);
            $table->string('status')->default('QUEUED');
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();
            $table->foreign(['event_id', 'organization_id'])->references(['id', 'organization_id'])->on('notification_events')->onDelete('restrict');
            $table->foreign('recipient_id')->references('id')->on('users')->onDelete('restrict');
            $table->unique(['event_id', 'recipient_id', 'channel']);
        });

        Schema::create('notification_deliveries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('logical_notification_id');
            $table->string('status')->default('QUEUED');
            $table->string('provider_reference', 255)->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();
            $table->foreign('logical_notification_id')->references('id')->on('logical_notifications')->onDelete('restrict');
        });

        Schema::create('notification_delivery_attempts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('delivery_id');
            $table->integer('attempt_number');
            $table->uuid('provider_instance_id')->nullable();
            $table->string('provider_event_id', 255)->nullable();
            $table->string('runtime_phase', 100)->nullable();
            $table->jsonb('safe_error')->nullable();
            $table->integer('latency_ms')->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->foreign('delivery_id')->references('id')->on('notification_deliveries')->onDelete('restrict');
            $table->foreign('provider_instance_id')->references('id')->on('wa_instances')->onDelete('restrict');
            $table->unique(['delivery_id', 'attempt_number']);
        });

        Schema::create('in_app_notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('recipient_id');
            $table->uuid('logical_notification_id');
            $table->jsonb('content');
            $table->timestampTz('read_at')->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->foreign('recipient_id')->references('id')->on('users')->onDelete('restrict');
            $table->foreign('logical_notification_id')->references('id')->on('logical_notifications')->onDelete('restrict');
        });

        Schema::create('outgoing_webhooks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->jsonb('events');
            $table->string('status', 50)->default('ACTIVE');
            $table->text('endpoint_url');
            $table->string('encrypted_secret', 2048);
            $table->string('key_reference', 255);
            $table->string('encryption_version', 50);
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();
            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('restrict');
            $table->unique(['id', 'organization_id']);
        });

        Schema::create('webhook_deliveries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('webhook_id');
            $table->uuid('organization_id');
            $table->uuid('event_id');
            $table->string('status');
            $table->integer('response_code')->nullable();
            $table->jsonb('safe_error')->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->foreign(['webhook_id', 'organization_id'])->references(['id', 'organization_id'])->on('outgoing_webhooks')->onDelete('restrict');
            $table->foreign(['event_id', 'organization_id'])->references(['id', 'organization_id'])->on('notification_events')->onDelete('restrict');
        });

        DB::statement('ALTER TABLE logical_notifications ALTER COLUMN status DROP DEFAULT');
        DB::statement('ALTER TABLE logical_notifications ALTER COLUMN status TYPE notification_delivery_status USING status::notification_delivery_status');
        DB::statement("ALTER TABLE logical_notifications ALTER COLUMN status SET DEFAULT 'QUEUED'::notification_delivery_status");
        DB::statement('ALTER TABLE notification_deliveries ALTER COLUMN status DROP DEFAULT');
        DB::statement('ALTER TABLE notification_deliveries ALTER COLUMN status TYPE notification_delivery_status USING status::notification_delivery_status');
        DB::statement("ALTER TABLE notification_deliveries ALTER COLUMN status SET DEFAULT 'QUEUED'::notification_delivery_status");
        DB::statement('ALTER TABLE webhook_deliveries ALTER COLUMN status DROP DEFAULT');
        DB::statement('ALTER TABLE webhook_deliveries ALTER COLUMN status TYPE notification_delivery_status USING status::notification_delivery_status');
        DB::statement("ALTER TABLE webhook_deliveries ALTER COLUMN status SET DEFAULT 'QUEUED'::notification_delivery_status");

        DB::statement('ALTER TABLE logical_notifications ALTER COLUMN status DROP DEFAULT');
        DB::statement('ALTER TABLE logical_notifications ALTER COLUMN status TYPE notification_delivery_status USING status::notification_delivery_status');
        DB::statement("ALTER TABLE logical_notifications ALTER COLUMN status SET DEFAULT 'QUEUED'::notification_delivery_status");
        DB::statement('ALTER TABLE notification_deliveries ALTER COLUMN status DROP DEFAULT');
        DB::statement('ALTER TABLE notification_deliveries ALTER COLUMN status TYPE notification_delivery_status USING status::notification_delivery_status');
        DB::statement("ALTER TABLE notification_deliveries ALTER COLUMN status SET DEFAULT 'QUEUED'::notification_delivery_status");
        DB::statement('ALTER TABLE webhook_deliveries ALTER COLUMN status DROP DEFAULT');
        DB::statement('ALTER TABLE webhook_deliveries ALTER COLUMN status TYPE notification_delivery_status USING status::notification_delivery_status');
        DB::statement("ALTER TABLE webhook_deliveries ALTER COLUMN status SET DEFAULT 'QUEUED'::notification_delivery_status");
    }

    public function down(): void {
        Schema::dropIfExists('webhook_deliveries');
        Schema::dropIfExists('outgoing_webhooks');
        Schema::dropIfExists('in_app_notifications');
        Schema::dropIfExists('notification_delivery_attempts');
        Schema::dropIfExists('notification_deliveries');
        Schema::dropIfExists('logical_notifications');
        Schema::dropIfExists('notification_templates');
        Schema::dropIfExists('notification_rules');
        Schema::dropIfExists('notification_events');
        Schema::dropIfExists('wa_instances');
        Schema::dropIfExists('wa_pools');
        Schema::dropIfExists('provider_configs');
        DB::statement('DROP TYPE IF EXISTS notification_delivery_status');
    }
};
