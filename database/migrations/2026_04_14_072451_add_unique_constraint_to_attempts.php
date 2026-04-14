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
        // Remove duplicate attempts for the same user in the same session, keeping the most recent
        DB::statement('
            DELETE FROM attempts
            WHERE id NOT IN (
                SELECT id FROM (
                    SELECT MAX(id) as id
                    FROM attempts
                    WHERE user_id IS NOT NULL
                    GROUP BY kangourou_session_id, user_id
                ) AS keep
            ) AND user_id IS NOT NULL
        ');

        Schema::table('attempts', function (Blueprint $table) {
            // Add unique constraint for authenticated users (one attempt per session per user)
            $table->unique(['kangourou_session_id', 'user_id'], 'attempts_session_user_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attempts', function (Blueprint $table) {
            $table->dropUnique('attempts_session_user_unique');
        });
    }
};
