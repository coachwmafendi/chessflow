<?php

use Database\Seeders\LessonSeeder;

it('passes on a seeded local install', function () {
    $this->seed(LessonSeeder::class);

    $this->artisan('chessflow:doctor')->assertSuccessful();
});

it('fails in production with debug on, http and missing lessons', function () {
    app()['env'] = 'production';
    config(['app.debug' => true, 'app.url' => 'http://chessflow.example']);

    $this->artisan('chessflow:doctor')->assertFailed();
});
