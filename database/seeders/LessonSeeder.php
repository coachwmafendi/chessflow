<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use RuntimeException;

class LessonSeeder extends Seeder
{
    public function run(): void
    {
        if (Artisan::call('chessflow:import-lessons') !== 0) {
            throw new RuntimeException('Lesson import failed: '.Artisan::output());
        }
    }
}
