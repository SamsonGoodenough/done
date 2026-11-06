<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Phase 4: a log entry can now target the whole show (both null, same as
     * before), one season (season_number set), or one episode (episode_id
     * set), never both. See docs/planning/04-data-model.md.
     */
    public function up(): void
    {
        Schema::table('log_entries', function (Blueprint $table) {
            $table->foreignId('episode_id')->nullable()->after('user_media_id')->constrained('episodes');
            $table->unsignedInteger('season_number')->nullable()->after('episode_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('log_entries', function (Blueprint $table) {
            $table->dropConstrainedForeignId('episode_id');
            $table->dropColumn('season_number');
        });
    }
};
