<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Latih semula" can also hold a position from the player's own game against Pak Kuda: the position before a
 * mistake, with the better move. Such items have a game and ply instead of a lesson step.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('review_items', function (Blueprint $table) {
            $table->foreignId('lesson_id')->nullable()->change();
            $table->unsignedSmallInteger('step_index')->nullable()->change();
            $table->foreignId('game_id')->nullable()->after('step_index')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('ply')->nullable()->after('game_id');
            // {fen, best (UCI), bestSan, also (other good SAN), played (SAN)}
            $table->json('position')->nullable()->after('ply');
            $table->unique(['user_id', 'game_id', 'ply']);
        });
    }

    public function down(): void
    {
        Schema::table('review_items', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'game_id', 'ply']);
            $table->dropConstrainedForeignId('game_id');
            $table->dropColumn(['ply', 'position']);
        });
    }
};
