<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::livewire('masuk-murid', 'pages::masuk-murid')->middleware('guest')->name('murid.login');
Route::livewire('sijil/{code}', 'pages::sijil')->name('sijil');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', fn () => redirect()->route('peta'))->name('dashboard');
    Route::livewire('peta', 'pages::peta')->name('peta');
    Route::livewire('pelajaran/{lesson:slug}', 'pages::pelajaran')->name('pelajaran');
    Route::livewire('harian', 'pages::harian')->name('harian');
    Route::livewire('main', 'pages::main')->name('main');
});

require __DIR__.'/settings.php';

if (app()->environment('local')) {
    Route::livewire('dev/playground', 'pages::dev.playground')->name('dev.playground');
}
