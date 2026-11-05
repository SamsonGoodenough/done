<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('user_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
            $table->foreignId('media_item_id')->constrained('media_items');
            $table->string('status');
            $table->boolean('is_favourite')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });

        // A user has at most one active shelf entry per item; removing one
        // (soft delete) and re-adding the same item restores the existing
        // row instead of inserting a duplicate — same dedup/restore pattern
        // as media_items (source, external_id). See
        // docs/planning/04-data-model.md and
        // docs/adr/0001-media-source-integration-architecture.md.
        DB::statement(
            'CREATE UNIQUE INDEX user_media_user_id_media_item_id_unique ON user_media (user_id, media_item_id) WHERE deleted_at IS NULL'
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_media');
    }
};
