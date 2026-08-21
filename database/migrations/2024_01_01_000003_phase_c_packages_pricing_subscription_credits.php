<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void {
        Schema::create('packages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 255);
            $table->boolean('is_custom')->default(false);
            $table->string('status', 50)->default('ACTIVE');
            $table->integer('duration_days')->nullable();
            $table->integer('retention_days')->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();
        });

        Schema::create('package_entitlements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('package_id');
            $table->string('capability', 100);
            $table->jsonb('limits')->default('{}');
            $table->foreign('package_id')->references('id')->on('packages')->onDelete('restrict');
            $table->unique(['package_id', 'capability']);
        });

        Schema::create('pricing_versions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('capability', 100);
            $table->bigInteger('credits_per_result');
            $table->timestampTz('valid_from')->useCurrent();
            $table->timestampTz('valid_until')->nullable();
            $table->string('status', 50)->default('ACTIVE');
            $table->timestampTz('created_at')->useCurrent();
        });
        DB::statement('ALTER TABLE pricing_versions ADD CONSTRAINT chk_pricing_credits CHECK (credits_per_result >= 0)');
        DB::statement("CREATE UNIQUE INDEX idx_pricing_versions_active_capability ON pricing_versions(capability) WHERE status = 'ACTIVE'");

        Schema::create('subscriptions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->uuid('package_id');
            $table->string('status', 50)->default('ACTIVE');
            $table->timestampTz('starts_at')->useCurrent();
            $table->timestampTz('expires_at');
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();
            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('restrict');
            $table->foreign('package_id')->references('id')->on('packages')->onDelete('restrict');
        });

        Schema::create('subscription_snapshots', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('subscription_id');
            $table->jsonb('snapshot_data');
            $table->timestampTz('created_at')->useCurrent();
            $table->foreign('subscription_id')->references('id')->on('subscriptions')->onDelete('restrict');
        });

        Schema::create('credit_lots', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->string('source', 50);
            $table->string('source_reference', 255)->nullable();
            $table->bigInteger('original_quantity');
            $table->bigInteger('remaining_quantity');
            $table->bigInteger('effective_monetary_value_cents')->default(0);
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('expires_at');
            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('restrict');
            $table->unique(['id', 'organization_id']);
            $table->index('organization_id', 'idx_credit_lots_org');
        });
        DB::statement('ALTER TABLE credit_lots ADD CONSTRAINT chk_cl_orig_qty CHECK (original_quantity >= 0)');
        DB::statement('ALTER TABLE credit_lots ADD CONSTRAINT chk_cl_rem_qty CHECK (remaining_quantity >= 0)');
        DB::statement('ALTER TABLE credit_lots ADD CONSTRAINT chk_cl_rem_le_orig CHECK (remaining_quantity <= original_quantity)');
        DB::statement("ALTER TABLE credit_lots ADD CONSTRAINT chk_cl_source CHECK (source IN ('SUBSCRIPTION', 'TOP_UP', 'BONUS', 'ADJUSTMENT', 'REFUND'))");
        DB::statement('CREATE INDEX idx_credit_lots_fefo ON credit_lots(organization_id, expires_at ASC) WHERE remaining_quantity > 0');
    }

    public function down(): void {
        Schema::dropIfExists('credit_lots');
        Schema::dropIfExists('subscription_snapshots');
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('pricing_versions');
        Schema::dropIfExists('package_entitlements');
        Schema::dropIfExists('packages');
    }
};
