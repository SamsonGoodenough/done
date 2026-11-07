<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Threaded comments on a Log Entry's Review, self-referencing via
     * parent_comment_id for nesting. Soft-deleted rather than hard-deleted
     * so a deleted comment's replies aren't orphaned — see
     * docs/planning/04-data-model.md and GLOSSARY.md "Comment".
     */
    public function up(): void
    {
        Schema::create('comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('log_entry_id')->constrained('log_entries');
            $table->foreignId('user_id')->constrained('users');
            $table->foreignId('parent_comment_id')->nullable()->constrained('comments');
            $table->text('body');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comments');
    }
};
