<?php

use App\Http\Controllers\RedirectController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/{code}', [RedirectController::class, 'redirect'])->where('code', '[a-zA-Z0-9]{1,10}');
