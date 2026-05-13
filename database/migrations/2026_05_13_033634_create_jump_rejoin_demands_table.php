<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jump_rejoin_demands', function (Blueprint $table) {
            $table->id();
            $table->foreignId('jump_attempt_id')->constrained('jump_user')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jump_rejoin_demands');
    }
};
