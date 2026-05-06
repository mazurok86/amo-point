<?php

use App\Http\Controllers\Api\JokeController;
use Illuminate\Support\Facades\Route;

Route::get('/jokes', [JokeController::class, 'index']);
