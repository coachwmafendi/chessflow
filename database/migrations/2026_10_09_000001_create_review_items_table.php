<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** "Latih semula": lesson steps a student got wrong, brought back on a widening schedule (Leitner boxes). */
    public function up(): void
    {
        Schema::create('review_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lesson_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('step_index');
            $table->unsignedTinyInteger('box')->default(1);
            $table->date('due_on');
            $table->timestamps();

            $table->unique(['user_id', 'lesson_id', 'step_index']);
            $table->index(['user_id', 'due_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('review_items');
    }
};
