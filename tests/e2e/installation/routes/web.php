<?php

use App\Http\Controllers\PageController;
use App\Http\Middleware\PublicFrontend;
use Illuminate\Support\Facades\Route;

Route::middleware(['craft', PublicFrontend::class])->group(function (): void {
    Route::get('/', PageController::class)->name('home');
    Route::get('/about', PageController::class)->name('about');
});

Route::view('/native', 'frontend::native', [
    'title' => 'Native page',
    'links' => ['home' => '/', 'about' => '/about'],
])->middleware('craft');
Route::get('/feed', fn () => response('<feed>Native feed</feed>', 200, ['Content-Type' => 'application/xml']));
