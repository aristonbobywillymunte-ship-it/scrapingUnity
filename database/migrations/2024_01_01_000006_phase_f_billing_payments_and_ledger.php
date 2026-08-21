<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void {
        DB::statement("
            DO \$\$ BEGIN
                IF NOT EXISTS (SELECT 1 FROM pg_type WHERE typname = 'credit_transaction_type') THEN
                    CREATE TYPE credit_transaction_type AS ENUM ('PACKAGE_CREDIT', 'PURCHASE', 'RESERVE', 'RELEASE', 'USAGE', 'REFUND', 'BONUS', 'ADJUSTMENT', 'EXPIRED');
                END IF;
            END \$\$;
        ");
        DB::statement("
            DO \$\$ BEGIN
                IF NOT EXISTS (SELECT 1 FROM pg_type WHERE typname = 'refund_status') THEN
                    CREATE TYPE refund_status AS ENUM ('PENDING', 'APPROVED', 'REJECTED');
                END IF;
            END \$\$;
        ");

        DB::statement('ALTER TABLE runs ADD CONSTRAINT runs_pricing_snapshot_id_fkey FOREIGN KEY (pricing_snapshot_id) REFERENCES pricing_versions(id) ON DELETE RESTRICT');

        Schema::create('billing_reservations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('run_id')->unique();
            $table->uuid('organization_id');
            $table->bigInteger('estimated');
            $table->bigInteger('reserved');
            $table->bigInteger('settled')->default(0);
            $table->bigInteger('released')->default(0);
            $table->string('status', 50);
            $table->uuid('pricing_version_id')->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();
            $table->foreign(['run_id', 'organization_id'])->references(['id', 'organization_id'])->on('runs')->onDelete('restrict');
            $table->foreign('pricing_version_id')->references('id')->on('pricing_versions')->onDelete('restrict');
            $table->unique(['id', 'organization_id']);
        });
        DB::statement('ALTER TABLE billing_reservations ADD CONSTRAINT chk_bres_est CHECK (estimated >= 0)');
        DB::statement('ALTER TABLE billing_reservations ADD CONSTRAINT chk_bres_res CHECK (reserved >= 0)');
        DB::statement('ALTER TABLE billing_reservations ADD CONSTRAINT chk_bres_set CHECK (settled >= 0)');
        DB::statement('ALTER TABLE billing_reservations ADD CONSTRAINT chk_bres_rel CHECK (released >= 0)');
        DB::statement('ALTER TABLE billing_reservations ADD CONSTRAINT chk_bres_bal CHECK (settled + released <= reserved)');

        Schema::create('credit_reservation_allocations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('reservation_id');
            $table->uuid('organization_id');
            $table->uuid('credit_lot_id');
            $table->bigInteger('reserved_quantity');
            $table->bigInteger('settled_quantity')->default(0);
            $table->bigInteger('released_quantity')->default(0);
            $table->jsonb('economic_value_snapshot')->nullable();
            $table->integer('allocation_order');
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();
            $table->foreign(['reservation_id', 'organization_id'])->references(['id', 'organization_id'])->on('billing_reservations')->onDelete('restrict');
            $table->foreign(['credit_lot_id', 'organization_id'])->references(['id', 'organization_id'])->on('credit_lots')->onDelete('restrict');
            $table->unique(['reservation_id', 'credit_lot_id']);
            $table->index(['reservation_id', 'allocation_order'], 'idx_cra_reservation_order');
        });
        DB::statement('ALTER TABLE credit_reservation_allocations ADD CONSTRAINT chk_cra_res_qty CHECK (reserved_quantity >= 0)');
        DB::statement('ALTER TABLE credit_reservation_allocations ADD CONSTRAINT chk_cra_set_qty CHECK (settled_quantity >= 0)');
        DB::statement('ALTER TABLE credit_reservation_allocations ADD CONSTRAINT chk_cra_rel_qty CHECK (released_quantity >= 0)');
        DB::statement('ALTER TABLE credit_reservation_allocations ADD CONSTRAINT chk_cra_bal CHECK (settled_quantity + released_quantity <= reserved_quantity)');

        Schema::create('credit_ledger', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->string('event_idempotency_key', 255)->unique();
            $table->string('transaction_type');
            $table->uuid('credit_lot_id');
            $table->uuid('reservation_id')->nullable();
            $table->uuid('reservation_allocation_id')->nullable();
            $table->uuid('run_id')->nullable();
            $table->bigInteger('quantity');
            $table->jsonb('economic_value_reference')->nullable();
            $table->uuid('actor_id')->nullable();
            $table->string('reason', 255)->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('restrict');
            $table->foreign('credit_lot_id')->references('id')->on('credit_lots')->onDelete('restrict');
            $table->foreign(['reservation_id', 'organization_id'])->references(['id', 'organization_id'])->on('billing_reservations')->onDelete('restrict');
            $table->foreign(['run_id', 'organization_id'])->references(['id', 'organization_id'])->on('runs')->onDelete('restrict');
            $table->foreign('actor_id')->references('id')->on('users')->onDelete('restrict');
        });

        DB::unprepared("
            CREATE OR REPLACE FUNCTION prevent_credit_ledger_modification()
            RETURNS TRIGGER AS $$
            BEGIN
                RAISE EXCEPTION 'credit_ledger is append-only';
            END;
            $$ LANGUAGE plpgsql;

            CREATE TRIGGER trg_credit_ledger_append_only
            BEFORE UPDATE OR DELETE ON credit_ledger
            FOR EACH ROW
            EXECUTE FUNCTION prevent_credit_ledger_modification();
        ");

        Schema::create('invoices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->bigInteger('total_cents');
            $table->string('currency', 3)->default('USD');
            $table->string('status', 50);
            $table->timestampTz('issued_at')->useCurrent();
            $table->timestampTz('due_at')->nullable();
            $table->timestampTz('paid_at')->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();
            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('restrict');
            $table->unique(['id', 'organization_id']);
        });
        DB::statement('ALTER TABLE invoices ADD CONSTRAINT chk_inv_total CHECK (total_cents >= 0)');

        Schema::create('payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->uuid('invoice_id');
            $table->string('provider', 100);
            $table->string('provider_transaction_id', 255);
            $table->string('currency', 3);
            $table->bigInteger('amount_cents');
            $table->string('status', 50);
            $table->timestampTz('paid_at')->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();
            $table->foreign(['invoice_id', 'organization_id'])->references(['id', 'organization_id'])->on('invoices')->onDelete('restrict');
            $table->unique(['provider', 'provider_transaction_id']);
            $table->unique(['id', 'organization_id']);
        });
        DB::statement('ALTER TABLE payments ADD CONSTRAINT chk_pay_amount CHECK (amount_cents >= 0)');

        Schema::create('payment_webhook_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('provider', 100);
            $table->string('provider_event_id', 255);
            $table->uuid('payment_id')->nullable();
            $table->string('provider_transaction_reference', 255)->nullable();
            $table->string('event_type', 100);
            $table->timestampTz('received_at')->useCurrent();
            $table->timestampTz('processed_at')->nullable();
            $table->string('processing_status', 50);
            $table->jsonb('safe_payload_metadata')->nullable();
            $table->jsonb('safe_error')->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->foreign('payment_id')->references('id')->on('payments')->onDelete('restrict');
            $table->unique(['provider', 'provider_event_id']);
        });

        Schema::create('credit_allocations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->uuid('payment_id');
            $table->uuid('credit_lot_id');
            $table->bigInteger('quantity');
            $table->string('allocation_reference', 255)->unique();
            $table->timestampTz('created_at')->useCurrent();
            $table->foreign(['payment_id', 'organization_id'])->references(['id', 'organization_id'])->on('payments')->onDelete('restrict');
            $table->foreign(['credit_lot_id', 'organization_id'])->references(['id', 'organization_id'])->on('credit_lots')->onDelete('restrict');
        });
        DB::statement('ALTER TABLE credit_allocations ADD CONSTRAINT chk_ca_qty CHECK (quantity >= 0)');

        Schema::create('refund_approvals', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('maker_id');
            $table->uuid('checker_id');
            $table->string('status');
            $table->string('reason', 255)->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('reviewed_at')->nullable();
            $table->foreign('maker_id')->references('id')->on('users')->onDelete('restrict');
            $table->foreign('checker_id')->references('id')->on('users')->onDelete('restrict');
        });
        DB::statement('ALTER TABLE refund_approvals ADD CONSTRAINT chk_ra_maker_checker CHECK (maker_id <> checker_id)');

        Schema::create('refunds', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->uuid('payment_id')->nullable();
            $table->uuid('run_id')->nullable();
            $table->uuid('approval_id');
            $table->bigInteger('amount_cents')->nullable();
            $table->bigInteger('credit_quantity')->nullable();
            $table->string('status');
            $table->string('reason', 255);
            $table->string('idempotency_key', 255)->unique();
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();
            $table->foreign('payment_id')->references('id')->on('payments')->onDelete('restrict');
            $table->foreign(['run_id', 'organization_id'])->references(['id', 'organization_id'])->on('runs')->onDelete('restrict');
            $table->foreign('approval_id')->references('id')->on('refund_approvals')->onDelete('restrict');
        });
        DB::statement('ALTER TABLE refunds ADD CONSTRAINT chk_ref_amount CHECK (amount_cents >= 0)');
        DB::statement('ALTER TABLE refunds ADD CONSTRAINT chk_ref_credit CHECK (credit_quantity >= 0)');
        DB::statement('ALTER TABLE refunds ADD CONSTRAINT chk_ref_values CHECK (COALESCE(amount_cents, 0) > 0 OR COALESCE(credit_quantity, 0) > 0)');

        Schema::create('internal_costs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('event_idempotency_key', 255)->unique();
            $table->uuid('run_id')->nullable();
            $table->uuid('task_id')->nullable();
            $table->string('category', 100);
            $table->string('provider_reference', 255)->nullable();
            $table->bigInteger('amount_cents');
            $table->timestampTz('created_at')->useCurrent();
            $table->foreign('run_id')->references('id')->on('runs')->onDelete('restrict');
            $table->foreign('task_id')->references('id')->on('tasks')->onDelete('restrict');
        });
        DB::statement('ALTER TABLE internal_costs ADD CONSTRAINT chk_ic_amount CHECK (amount_cents >= 0)');

        DB::statement('ALTER TABLE credit_ledger ALTER COLUMN transaction_type DROP DEFAULT');
        DB::statement('ALTER TABLE credit_ledger ALTER COLUMN transaction_type TYPE credit_transaction_type USING transaction_type::credit_transaction_type');
        DB::statement('ALTER TABLE refund_approvals ALTER COLUMN status DROP DEFAULT');
        DB::statement('ALTER TABLE refund_approvals ALTER COLUMN status TYPE refund_status USING status::refund_status');
        DB::statement('ALTER TABLE refunds ALTER COLUMN status DROP DEFAULT');
        DB::statement('ALTER TABLE refunds ALTER COLUMN status TYPE refund_status USING status::refund_status');

        DB::statement('ALTER TABLE credit_ledger ALTER COLUMN transaction_type DROP DEFAULT');
        DB::statement('ALTER TABLE credit_ledger ALTER COLUMN transaction_type TYPE credit_transaction_type USING transaction_type::credit_transaction_type');
        DB::statement('ALTER TABLE refund_approvals ALTER COLUMN status DROP DEFAULT');
        DB::statement('ALTER TABLE refund_approvals ALTER COLUMN status TYPE refund_status USING status::refund_status');
        DB::statement('ALTER TABLE refunds ALTER COLUMN status DROP DEFAULT');
        DB::statement('ALTER TABLE refunds ALTER COLUMN status TYPE refund_status USING status::refund_status');
    }

    public function down(): void {
        Schema::dropIfExists('internal_costs');
        Schema::dropIfExists('refunds');
        Schema::dropIfExists('refund_approvals');
        Schema::dropIfExists('credit_allocations');
        Schema::dropIfExists('payment_webhook_events');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('invoices');
        DB::unprepared("
            DROP TRIGGER IF EXISTS trg_credit_ledger_append_only ON credit_ledger;
            DROP FUNCTION IF EXISTS prevent_credit_ledger_modification();
        ");
        Schema::dropIfExists('credit_ledger');
        Schema::dropIfExists('credit_reservation_allocations');
        Schema::dropIfExists('billing_reservations');
        DB::statement('ALTER TABLE runs DROP CONSTRAINT IF EXISTS runs_pricing_snapshot_id_fkey');
        DB::statement('DROP TYPE IF EXISTS refund_status');
        DB::statement('DROP TYPE IF EXISTS credit_transaction_type');
    }
};
