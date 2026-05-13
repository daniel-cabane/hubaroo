<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jumps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('nb_questions')->default(7);
            $table->unsignedTinyInteger('time')->default(15);
            $table->string('status')->default('draft'); // draft, active, expired
            $table->timestamp('expiration')->nullable();
            $table->tinyInteger('growth')->default(3);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jumps');
    }
};
