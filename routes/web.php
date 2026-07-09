<?php

use Illuminate\Support\Facades\Route;

Route::get('/', fn () => view('baobab::welcome'))->name('baobab.welcome');
