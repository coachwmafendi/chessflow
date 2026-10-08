<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('levels', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('number')->unique();
            $table->string('name');
            $table->string('note')->nullable();
            $table->unsignedSmallInteger('position');
            $table->timestamps();
        });

        Schema::create('lessons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('level_id')->constrained()->cascadeOnDelete();
            $table->string('slug')->unique();
            $table->string('title');
            $table->string('icon', 4);
            $table->string('kind')->default('lesson');
            $table->unsignedSmallInteger('position')->index();
            $table->unsignedSmallInteger('xp');
            $table->text('tip')->nullable();
            $table->json('steps');
            $table->boolean('is_published')->default(true);
            $table->unsignedInteger('content_version')->default(1);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lessons');
        Schema::dropIfExists('levels');
    }
};
