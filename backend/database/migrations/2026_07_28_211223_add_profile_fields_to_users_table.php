<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('password')->nullable()->change();
            $table->string('avatar_path')->nullable()->after('password');
            $table->string('profile_visibility')->default('public')->after('avatar_path');
            $table->timestamp('deactivated_at')->nullable()->after('profile_visibility');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['avatar_path', 'profile_visibility', 'deactivated_at']);
            $table->string('password')->nullable(false)->change();
        });
    }
};
