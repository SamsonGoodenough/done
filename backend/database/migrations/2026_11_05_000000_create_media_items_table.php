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
        Schema::create('media_items', function (Blueprint $table) {
            $table->id();
            $table->string('type');
            $table->string('source');
            $table->string('external_id')->nullable();
            $table->string('title');
            $table->date('release_date')->nullable();
            $table->string('poster_url')->nullable();
            $table->text('overview')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->foreignId('added_by')->nullable()->constrained('users');
            $table->timestamps();
            $table->softDeletes();
        });

        // external_id is null for manual entries, and Postgres already treats
        // distinct NULLs as non-duplicate under a unique index; the explicit
        // "external_id IS NOT NULL" just keeps that intent visible rather than
        // relying on NULL-handling trivia. See the dedup/restore note on
        // media_items in docs/planning/04-data-model.md.
        DB::statement(
            'CREATE UNIQUE INDEX media_items_source_external_id_unique ON media_items (source, external_id) WHERE deleted_at IS NULL AND external_id IS NOT NULL'
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('media_items');
    }
};
