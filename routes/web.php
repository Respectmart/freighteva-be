<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/marketplace-test', function () {
    return view('marketplace-test');
})->name('marketplace.test');
