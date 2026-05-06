<?php

use App\Http\Controllers\Api\JokeController;
use App\Http\Controllers\Api\VisitController;
use Illuminate\Support\Facades\Route;

Route::get('/jokes', [JokeController::class, 'index']);

Route::post('/visits', [VisitController::class, 'store'])->middleware('throttle:60,1');
