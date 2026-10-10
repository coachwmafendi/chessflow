<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Translations of level/lesson text from data/lessons.json, e.g. {"en": {"title": "…", "tip": "…"}}.
     * Step translations live inside each step (`steps[i].en`).
     */
    public function up(): void
    {
        Schema::table('levels', function (Blueprint $table) {
            $table->json('i18n')->nullable()->after('note');
        });
        Schema::table('lessons', function (Blueprint $table) {
            $table->json('i18n')->nullable()->after('tip');
        });
    }

    public function down(): void
    {
        Schema::table('levels', fn (Blueprint $table) => $table->dropColumn('i18n'));
        Schema::table('lessons', fn (Blueprint $table) => $table->dropColumn('i18n'));
    }
};
