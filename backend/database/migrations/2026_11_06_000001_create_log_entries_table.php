<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Deliberately no episode_id/season_number yet — those are added in
     * Phase 4 alongside the episodes table they depend on, per
     * docs/planning/09-roadmap.md. This phase is whole-item logging only.
     */
    public function up(): void
    {
        Schema::create('log_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_media_id')->constrained('user_media');
            $table->date('logged_date');
            $table->decimal('rating', 2, 1)->nullable();
            $table->text('review')->nullable();
            $table->boolean('contains_spoilers')->default(false);
            $table->boolean('is_rewatch')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('log_entries');
    }
};
