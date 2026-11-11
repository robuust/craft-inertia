<?php

use Illuminate\Support\Facades\Route;

// No route, controller or rendering middleware for native entry pages.
Route::view('/native', 'frontend::native', ['title' => 'Native page', 'links' => []])->middleware('craft');
Route::get('/feed', fn () => response('<feed>Native feed</feed>', 200, ['Content-Type' => 'application/xml']));
