<?php

use App\Http\Controllers\WeatherController;
use Illuminate\Support\Facades\Route;

Route::get('/weather/{city}', [WeatherController::class, 'show']);
Route::get('/weather/{city}/cached', [WeatherController::class, 'cached']);
