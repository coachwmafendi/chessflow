<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Nullable until Fasa 3 wires username login; students need no email.
            $table->string('username')->nullable()->unique()->after('name');
            $table->string('email')->nullable()->change();
            $table->string('role')->default('murid')->after('password');
            $table->string('pin')->nullable()->after('role');
            $table->unsignedInteger('xp')->default(0);
            $table->unsignedInteger('streak_current')->default(0);
            $table->unsignedInteger('streak_best')->default(0);
            $table->date('streak_last_date')->nullable();
            $table->json('preferences')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['username']);
            $table->dropColumn(['username', 'role', 'pin', 'xp', 'streak_current', 'streak_best', 'streak_last_date', 'preferences']);
            $table->string('email')->nullable(false)->change();
        });
    }
};
