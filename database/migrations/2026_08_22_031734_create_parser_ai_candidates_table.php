<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * P2 Forward Migration:
     * `parser_ai_candidates`: AI-generated parser repair candidates lifecycle (PRD §14, §18)
     * Statuses: PENDING, VALIDATING, VALID, INVALID, APPROVED, REJECTED
     */
    public function up(): void
    {
        if (!Schema::hasTable('parser_ai_candidates')) {
            Schema::create('parser_ai_candidates', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('failure_id')->nullable();
                $table->string('platform', 50);
                $table->string('operation', 100);
                $table->string('base_version', 50)->nullable();
                $table->jsonb('candidate_selectors');
                $table->string('ai_provider', 100)->default('OPENAI');
                $table->string('ai_model', 100)->default('gpt-4o');
                $table->string('status', 50)->default('PENDING');
                $table->jsonb('validation_results')->nullable();
                $table->text('rejection_reason')->nullable();
                $table->uuid('approved_by')->nullable();
                $table->timestampTz('approved_at')->nullable();
                $table->timestampTz('created_at')->useCurrent();
                $table->timestampTz('updated_at')->useCurrent();

                $table->foreign('failure_id')->references('id')->on('parser_failures')->onDelete('set null');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('parser_ai_candidates');
    }
};
