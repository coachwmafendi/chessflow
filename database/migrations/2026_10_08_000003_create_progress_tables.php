<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lesson_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lesson_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('best_stars')->default(0);
            $table->unsignedTinyInteger('last_stars')->default(0);
            $table->unsignedInteger('mistakes')->default(0);
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'lesson_id']);
        });

        Schema::create('exam_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lesson_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('score');
            $table->unsignedSmallInteger('total');
            $table->boolean('passed');
            $table->json('failed_steps');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('certificates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('level_id')->constrained()->cascadeOnDelete();
            $table->foreignId('exam_attempt_id')->constrained()->cascadeOnDelete();
            $table->string('display_name');
            $table->unsignedSmallInteger('score');
            $table->unsignedSmallInteger('total');
            $table->char('code', 10)->unique();
            $table->timestamp('issued_at');
        });

        Schema::create('daily_puzzles', function (Blueprint $table) {
            $table->id();
            $table->date('date')->unique();
            $table->foreignId('lesson_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('step_index');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('daily_completions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->timestamp('solved_at');
            $table->unique(['user_id', 'date']);
        });

        Schema::create('games', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->char('user_color', 1);
            $table->unsignedTinyInteger('level');
            $table->string('result');
            $table->text('pgn');
            $table->unsignedSmallInteger('move_count');
            $table->timestamp('finished_at');
        });

        Schema::create('xp_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->integer('amount');
            $table->nullableMorphs('source');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('xp_events');
        Schema::dropIfExists('games');
        Schema::dropIfExists('daily_completions');
        Schema::dropIfExists('daily_puzzles');
        Schema::dropIfExists('certificates');
        Schema::dropIfExists('exam_attempts');
        Schema::dropIfExists('lesson_progress');
    }
};
