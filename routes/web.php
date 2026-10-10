<?php

use App\Http\Controllers\LocaleController;
use App\Http\Controllers\SeoController;
use Illuminate\Support\Facades\Route;

Route::livewire('/', 'pages::utama')->name('home');
Route::get('robots.txt', [SeoController::class, 'robots'])->name('robots');
Route::get('sitemap.xml', [SeoController::class, 'sitemap'])->name('sitemap');
Route::livewire('tentang', 'pages::tentang')->name('tentang');
Route::livewire('privasi', 'pages::privasi')->name('privasi');
Route::livewire('terma', 'pages::terma')->name('terma');

Route::post('bahasa/{locale}', LocaleController::class)->name('locale.switch');

Route::livewire('masuk-murid', 'pages::masuk-murid')->middleware('guest')->name('murid.login');
Route::livewire('sijil/{code}', 'pages::sijil')->name('sijil');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', fn () => redirect()->route('peta'))->name('dashboard');
    Route::livewire('peta', 'pages::peta')->name('peta');
    Route::livewire('pelajaran/{lesson:slug}', 'pages::pelajaran')->name('pelajaran');
    Route::livewire('harian', 'pages::harian')->name('harian');
    Route::livewire('latih', 'pages::latih')->name('latih');
    Route::livewire('lencana', 'pages::lencana')->name('lencana');
    Route::livewire('main', 'pages::main')->name('main');
    Route::livewire('sertai', 'pages::sertai')->name('sertai');

    Route::livewire('anak', 'pages::anak')->middleware('can:guardian')->name('anak');

    Route::middleware('can:create,App\Models\Classroom')->group(function () {
        Route::livewire('guru', 'pages::guru.index')->name('guru');
        Route::livewire('guru/kelas/{classroom}', 'pages::guru.kelas')->name('guru.kelas');
    });

    Route::livewire('pratonton/{lesson:slug}', 'pages::admin.pratonton')->middleware('can:admin')->name('pratonton');
});

require __DIR__.'/settings.php';

if (app()->environment('local')) {
    Route::livewire('dev/playground', 'pages::dev.playground')->name('dev.playground');
}
