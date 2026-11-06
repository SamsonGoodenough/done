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
        Schema::create('episodes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('media_item_id')->constrained('media_items');
            $table->unsignedInteger('season_number');
            $table->unsignedInteger('episode_number');
            $table->string('title')->nullable();
            $table->date('air_date')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // Lets a resync tell "already have this episode" from "missing" without
        // relying on application-level locking. See
        // docs/adr/0004-episode-sync-is-lazy-and-self-refreshing.md.
        DB::statement(
            'CREATE UNIQUE INDEX episodes_media_item_season_episode_unique ON episodes (media_item_id, season_number, episode_number) WHERE deleted_at IS NULL'
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('episodes');
    }
};
