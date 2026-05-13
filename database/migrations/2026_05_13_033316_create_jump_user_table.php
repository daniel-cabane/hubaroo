<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jump_user', function (Blueprint $table) {
            $table->foreignId('jump_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->json('question_list')->nullable();
            $table->integer('score')->default(0);
            $table->string('status')->default('inProgress'); // inProgress, finished
            $table->primary(['jump_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jump_user');
    }
};
