<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations for canonical PRD/ERD scraping core.
     */
    public function up(): void
    {
        if (!Schema::hasTable('scraping_jobs')) {
            Schema::create('scraping_jobs', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('user_id');
                $table->string('platform', 32);
                $table->string('operation', 64);
                $table->string('target_type', 32);
                $table->text('target_value');
                $table->jsonb('options')->nullable();
                $table->string('idempotency_key', 64)->nullable()->index();
                $table->string('request_fingerprint', 64)->nullable()->index();
                $table->uuid('scrape_execution_id')->nullable()->index();
                $table->string('status', 32)->default('QUEUED')->index();
                $table->timestamps();

                $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            });
        }

        if (!Schema::hasTable('scraping_items')) {
            Schema::create('scraping_items', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('platform', 32);
                $table->string('content_type', 32);
                $table->string('external_id', 128)->index();
                $table->text('canonical_url');
                $table->string('request_fingerprint', 64)->nullable()->index();
                $table->jsonb('author')->nullable();
                $table->text('text')->nullable();
                $table->timestampTz('published_at')->nullable();
                $table->jsonb('media')->nullable();
                $table->jsonb('metrics')->nullable();
                $table->jsonb('platform_fields')->nullable();
                $table->timestampTz('collected_at');
                $table->string('parser_version', 32);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('webhooks')) {
            Schema::create('webhooks', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('user_id');
                $table->text('url');
                $table->jsonb('events');
                $table->string('secret', 128);
                $table->string('status', 32)->default('ACTIVE');
                $table->timestamps();

                $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('webhooks');
        Schema::dropIfExists('scraping_items');
        Schema::dropIfExists('scraping_jobs');
    }
};
