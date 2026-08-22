<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('scraping_items', function (Blueprint $table) {
            $table->unique(['external_id', 'request_fingerprint']);
        });
    }

    public function down(): void
    {
        Schema::table('scraping_items', function (Blueprint $table) {
            $table->dropUnique(['external_id', 'request_fingerprint']);
        });
    }
};
