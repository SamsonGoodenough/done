<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Plain many-to-many pivot backing Tag Friends — no surrogate id, no
     * soft delete (un-tagging is a hard delete, there's no history worth
     * keeping for a tag that no longer applies). See
     * docs/planning/04-data-model.md.
     */
    public function up(): void
    {
        Schema::create('log_entry_participants', function (Blueprint $table) {
            $table->foreignId('log_entry_id')->constrained('log_entries')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->primary(['log_entry_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('log_entry_participants');
    }
};
