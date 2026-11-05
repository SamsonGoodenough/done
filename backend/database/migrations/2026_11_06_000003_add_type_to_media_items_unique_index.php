<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The original (source, external_id) index let two media_items collide
     * across types: TMDB assigns movie IDs and TV IDs from independent
     * numbering spaces, so a movie and an unrelated TV show can share the
     * same numeric external_id under the single 'tmdb' source. Scoping the
     * uniqueness by type as well closes that.
     */
    public function up(): void
    {
        DB::statement('DROP INDEX IF EXISTS media_items_source_external_id_unique');

        DB::statement(
            'CREATE UNIQUE INDEX media_items_type_source_external_id_unique ON media_items (type, source, external_id) WHERE deleted_at IS NULL AND external_id IS NOT NULL'
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS media_items_type_source_external_id_unique');

        DB::statement(
            'CREATE UNIQUE INDEX media_items_source_external_id_unique ON media_items (source, external_id) WHERE deleted_at IS NULL AND external_id IS NOT NULL'
        );
    }
};
