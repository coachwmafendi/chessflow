<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** The opening a game reached (name and ECO code from public/openings/openings.json), for the game history. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->string('opening', 150)->nullable()->after('pgn');
            $table->char('eco', 3)->nullable()->after('opening');
        });
    }

    public function down(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->dropColumn(['opening', 'eco']);
        });
    }
};
