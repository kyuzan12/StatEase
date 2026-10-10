<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Every survey belongs to the user who uploaded it.
 * A Likert scale belongs to the user who created it; the built-in scales have no owner (user_id = null)
 * and are shared by everyone.
 *
 * Existing rows keep user_id = null here. The first account that registers claims them
 * (see AuthController::claimExistingData), so no data is lost.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('surveys', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
        });

        Schema::table('likert_scales', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('id')->constrained()->cascadeOnDelete();

            // A scale name only has to be unique for its owner, not across all users.
            $table->dropUnique(['name']);
            $table->unique(['user_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::table('likert_scales', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'name']);
            $table->dropConstrainedForeignId('user_id');
            $table->unique('name');
        });

        Schema::table('surveys', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
        });
    }
};
