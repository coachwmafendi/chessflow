<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** A lesson a teacher sets for a class, optionally with a due date. Assigned lessons are open to the class. */
    public function up(): void
    {
        Schema::create('assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('classroom_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lesson_id')->constrained()->cascadeOnDelete();
            $table->date('due_on')->nullable();
            $table->string('note', 200)->nullable();
            $table->timestamps();

            $table->unique(['classroom_id', 'lesson_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assignments');
    }
};
