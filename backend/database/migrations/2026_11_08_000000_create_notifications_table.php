<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * A personal, read/unread alert to one user about one of six events
     * (tagged, friend request/accept, comment, reply, reaction). `data` is a
     * JSON payload keyed per type (log_entry_id, comment_id, from_user_id,
     * ...) rather than per-type FK columns, same reasoning as
     * media_items.metadata. See docs/planning/04-data-model.md and
     * GLOSSARY.md "Notification".
     */
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
            $table->string('type');
            $table->json('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'read_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
