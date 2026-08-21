<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void {
        Schema::create('organizations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 255);
            $table->string('status', 50)->default('ACTIVE');
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();
            $table->timestampTz('deleted_at')->nullable();
        });

        Schema::create('users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('email', 255)->unique();
            $table->string('password_hash', 255)->nullable();
            $table->boolean('mfa_enabled')->default(false);
            $table->string('status', 50)->default('ACTIVE');
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();
            $table->timestampTz('deleted_at')->nullable();
        });

        Schema::create('roles', function (Blueprint $table) {
            $table->string('id', 50)->primary();
            $table->boolean('is_internal_role');
            $table->string('description', 255)->nullable();
            $table->unique(['id', 'is_internal_role']);
        });

        Schema::create('permissions', function (Blueprint $table) {
            $table->string('id', 100)->primary();
        });

        Schema::create('role_permissions', function (Blueprint $table) {
            $table->string('role_id', 50);
            $table->string('permission_id', 100);
            $table->primary(['role_id', 'permission_id']);
            $table->foreign('role_id')->references('id')->on('roles')->onDelete('cascade');
            $table->foreign('permission_id')->references('id')->on('permissions')->onDelete('cascade');
        });

        Schema::create('organization_memberships', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->uuid('user_id');
            $table->string('role_id', 50);
            $table->boolean('role_is_internal')->default(false);
            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('restrict');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('restrict');
            $table->foreign(['role_id', 'role_is_internal'])->references(['id', 'is_internal_role'])->on('roles')->onDelete('restrict');
            $table->unique(['organization_id', 'user_id']);
        });
        DB::statement('ALTER TABLE organization_memberships ADD CONSTRAINT chk_org_memberships_role_internal CHECK (role_is_internal = false)');

        Schema::create('internal_user_assignments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->string('role_id', 50);
            $table->boolean('role_is_internal')->default(true);
            $table->foreign('user_id')->references('id')->on('users')->onDelete('restrict');
            $table->foreign(['role_id', 'role_is_internal'])->references(['id', 'is_internal_role'])->on('roles')->onDelete('restrict');
            $table->unique(['user_id', 'role_id']);
        });
        DB::statement('ALTER TABLE internal_user_assignments ADD CONSTRAINT chk_internal_user_assignments_role CHECK (role_is_internal = true)');

        Schema::create('auth_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->string('token_hash', 255)->unique();
            $table->jsonb('device_metadata')->nullable();
            $table->ipAddress('ip_address')->nullable();
            $table->timestampTz('expires_at');
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('revoked_at')->nullable();
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->index('expires_at', 'idx_auth_sessions_expires_at');
        });

        Schema::create('auth_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->string('event_type', 100);
            $table->ipAddress('ip_address')->nullable();
            $table->jsonb('device_metadata')->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->foreign('user_id')->references('id')->on('users')->onDelete('restrict');
            $table->index('created_at', 'idx_auth_logs_created_at');
        });

        Schema::create('otp_rate_buckets', function (Blueprint $table) {
            $table->uuid('user_id');
            $table->string('channel', 50);
            $table->date('bucket_date');
            $table->integer('request_count')->default(1);
            $table->timestampTz('updated_at')->useCurrent();
            $table->primary(['user_id', 'channel', 'bucket_date']);
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
        DB::statement("ALTER TABLE otp_rate_buckets ADD CONSTRAINT chk_otp_req_count CHECK (request_count <= 3)");
        DB::statement("ALTER TABLE otp_rate_buckets ADD CONSTRAINT chk_otp_channel CHECK (channel IN ('EMAIL', 'WHATSAPP'))");

        Schema::create('otp_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->string('channel', 50);
            $table->string('otp_hash', 255);
            $table->string('purpose', 100);
            $table->integer('attempt_count')->default(0);
            $table->timestampTz('expires_at');
            $table->timestampTz('used_at')->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->index(['user_id', 'created_at'], 'idx_otp_requests_user_created');
        });
        DB::statement("ALTER TABLE otp_requests ADD CONSTRAINT chk_otp_req_attempt CHECK (attempt_count <= 5)");
        DB::statement("ALTER TABLE otp_requests ADD CONSTRAINT chk_otp_req_channel CHECK (channel IN ('EMAIL', 'WHATSAPP'))");

        Schema::create('channel_bindings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->string('channel', 50);
            $table->string('external_identity', 255);
            $table->timestampTz('verified_at')->nullable();
            $table->string('status', 50)->default('ACTIVE');
            $table->timestampTz('revoked_at')->nullable();
            $table->jsonb('safe_metadata')->nullable();
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->unique(['channel', 'external_identity']);
        });
        DB::statement("ALTER TABLE channel_bindings ADD CONSTRAINT chk_channel_bindings_channel CHECK (channel IN ('WHATSAPP', 'TELEGRAM'))");

        Schema::create('api_keys', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->uuid('created_by')->nullable();
            $table->string('key_hash', 255)->unique();
            $table->string('key_prefix', 50);
            $table->jsonb('scopes')->nullable();
            $table->string('status', 50)->default('ACTIVE');
            $table->timestampTz('last_used_at')->nullable();
            $table->timestampTz('revoked_at')->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('restrict');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
        });

        Schema::create('api_idempotency_keys', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->string('actor_identity', 255);
            $table->string('operation_id', 255);
            $table->string('key_hash', 255);
            $table->string('request_fingerprint', 255);
            $table->string('status', 50)->default('PROCESSING');
            $table->jsonb('response_reference')->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('expires_at');
            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('restrict');
            $table->unique(['organization_id', 'actor_identity', 'operation_id', 'key_hash']);
        });

        Schema::create('service_actors', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 255);
            $table->string('credentials_hash', 255);
            $table->string('purpose', 255);
            $table->uuid('owner_user_id')->nullable();
            $table->string('environment', 50);
            $table->string('credential_rotation_reference', 255)->nullable();
            $table->timestampTz('last_rotated_at')->nullable();
            $table->string('audit_reference', 255)->nullable();
            $table->string('status', 50)->default('ACTIVE');
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();
            $table->timestampTz('deleted_at')->nullable();
            $table->foreign('owner_user_id')->references('id')->on('users')->onDelete('set null');
        });

        Schema::create('service_actor_permissions', function (Blueprint $table) {
            $table->uuid('service_actor_id');
            $table->string('permission_id', 100);
            $table->primary(['service_actor_id', 'permission_id']);
            $table->foreign('service_actor_id')->references('id')->on('service_actors')->onDelete('cascade');
            $table->foreign('permission_id')->references('id')->on('permissions')->onDelete('cascade');
        });

        Schema::create('temporary_access_grants', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->string('role_id', 50);
            $table->uuid('approver_id');
            $table->uuid('organization_id')->nullable();
            $table->text('reason');
            $table->timestampTz('starts_at')->useCurrent();
            $table->timestampTz('expires_at');
            $table->timestampTz('revoked_at')->nullable();
            $table->string('audit_reference', 255)->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->foreign('user_id')->references('id')->on('users')->onDelete('restrict');
            $table->foreign('role_id')->references('id')->on('roles')->onDelete('restrict');
            $table->foreign('approver_id')->references('id')->on('users')->onDelete('restrict');
            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('restrict');
        });

        Schema::create('jit_privilege_grants', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->uuid('requested_by');
            $table->uuid('approved_by');
            $table->string('permission_id', 100);
            $table->uuid('organization_id')->nullable();
            $table->text('reason');
            $table->string('status', 50)->default('ACTIVE');
            $table->timestampTz('starts_at')->useCurrent();
            $table->timestampTz('expires_at');
            $table->timestampTz('revoked_at')->nullable();
            $table->string('audit_reference', 255)->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->foreign('user_id')->references('id')->on('users')->onDelete('restrict');
            $table->foreign('requested_by')->references('id')->on('users')->onDelete('restrict');
            $table->foreign('approved_by')->references('id')->on('users')->onDelete('restrict');
            $table->foreign('permission_id')->references('id')->on('permissions')->onDelete('restrict');
            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('restrict');
        });

        Schema::create('break_glass_activations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->text('reason');
            $table->timestampTz('starts_at')->useCurrent();
            $table->timestampTz('expires_at');
            $table->timestampTz('revoked_at')->nullable();
            $table->string('audit_reference', 255)->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->foreign('user_id')->references('id')->on('users')->onDelete('restrict');
        });

        Schema::create('access_reviews', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('target_user_id');
            $table->uuid('reviewer_id');
            $table->string('status', 50);
            $table->timestampTz('reviewed_at')->useCurrent();
            $table->foreign('target_user_id')->references('id')->on('users')->onDelete('restrict');
            $table->foreign('reviewer_id')->references('id')->on('users')->onDelete('restrict');
        });
    }

    public function down(): void {
        Schema::dropIfExists('access_reviews');
        Schema::dropIfExists('break_glass_activations');
        Schema::dropIfExists('jit_privilege_grants');
        Schema::dropIfExists('temporary_access_grants');
        Schema::dropIfExists('service_actor_permissions');
        Schema::dropIfExists('service_actors');
        Schema::dropIfExists('api_idempotency_keys');
        Schema::dropIfExists('api_keys');
        Schema::dropIfExists('channel_bindings');
        Schema::dropIfExists('otp_requests');
        Schema::dropIfExists('otp_rate_buckets');
        Schema::dropIfExists('auth_logs');
        Schema::dropIfExists('auth_sessions');
        Schema::dropIfExists('internal_user_assignments');
        Schema::dropIfExists('organization_memberships');
        Schema::dropIfExists('role_permissions');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('users');
        Schema::dropIfExists('organizations');
    }
};
