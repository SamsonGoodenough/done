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
        Schema::create('friendships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('requester_id')->constrained('users');
            $table->foreignId('addressee_id')->constrained('users');
            $table->string('status')->default('pending');
            $table->timestamps();
            $table->timestamp('responded_at')->nullable();
            $table->softDeletes();
        });

        // At most one active (requester, addressee) row in this exact
        // direction; a crossing request (B requests A while A's request to
        // B is still pending) is resolved at the application layer by
        // accepting the existing row, not by inserting a second one. An
        // unfriend (soft delete) frees the pair up for a fresh request. See
        // docs/planning/04-data-model.md and GLOSSARY.md "Friendship".
        DB::statement(
            'CREATE UNIQUE INDEX friendships_requester_id_addressee_id_unique ON friendships (requester_id, addressee_id) WHERE deleted_at IS NULL'
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('friendships');
    }
};
