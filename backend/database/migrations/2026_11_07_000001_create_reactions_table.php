<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Fixed-emoji reactions on a Log Entry's Review. Atomic and hard-deleted
     * (no updated_at, no soft delete, same created-at-only shape as the
     * log_entry_participants pivot) — there's nothing to update, only add
     * or remove. See docs/planning/04-data-model.md and GLOSSARY.md
     * "Reaction".
     */
    public function up(): void
    {
        Schema::create('reactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('log_entry_id')->constrained('log_entries');
            $table->foreignId('user_id')->constrained('users');
            $table->string('emoji');
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['log_entry_id', 'user_id', 'emoji']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reactions');
    }
};
