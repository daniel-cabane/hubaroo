<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // SQLite doesn't support adding a primary key column via ALTER TABLE.
        // We recreate the table with the new schema instead.
        Schema::table('jump_user', function (Blueprint $table) {
            $table->dropForeign(['jump_id']);
            $table->dropForeign(['user_id']);
        });

        Schema::drop('jump_user');

        Schema::create('jump_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('jump_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->json('question_list')->nullable();
            $table->integer('score')->default(0);
            $table->string('status')->default('inProgress');
            $table->integer('timer')->default(0);
            $table->integer('extra_time')->default(0);
            $table->string('termination')->default('none');
            $table->unique(['jump_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::table('jump_user', function (Blueprint $table) {
            $table->dropForeign(['jump_id']);
            $table->dropForeign(['user_id']);
            $table->dropUnique(['jump_id', 'user_id']);
            $table->dropColumn(['id', 'timer', 'extra_time', 'termination']);
            $table->primary(['jump_id', 'user_id']);
            $table->foreign('jump_id')->references('id')->on('jumps')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }
};
