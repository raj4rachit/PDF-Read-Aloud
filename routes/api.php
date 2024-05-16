<?php

use App\Http\Controllers\TTSController;
use Illuminate\Support\Facades\Route;

Route::post('/tts/convert', [TTSController::class, 'convert']);
Route::post('/tts/play', [TTSController::class, 'play']);
Route::post('/tts/pause', [TTSController::class, 'pause']);
Route::post('/tts/stop', [TTSController::class, 'stop']);
