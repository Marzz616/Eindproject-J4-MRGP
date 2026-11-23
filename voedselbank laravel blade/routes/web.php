<?php

use Illuminate\Support\Facades\Route;

Route::view('/login', 'pages.login')->name('login');
Route::view('/', 'pages.dashboard')->name('dashboard');
Route::view('/dashboard', 'pages.dashboard');
Route::view('/account', 'pages.account')->name('account');
Route::view('/gebruikers', 'pages.gebruikers')->name('gebruikers');
Route::view('/klanten', 'pages.klanten')->name('klanten');
Route::view('/leveranciers', 'pages.leveranciers')->name('leveranciers');
Route::view('/maandoverzicht', 'pages.maandoverzicht')->name('maandoverzicht');
Route::view('/pakbon', 'pages.pakbon')->name('pakbon');
Route::view('/pakketten', 'pages.pakketten')->name('pakketten');
Route::view('/producten', 'pages.producten')->name('producten');
Route::view('/rapportages', 'pages.rapportages')->name('rapportages');
Route::view('/uitgifte', 'pages.uitgifte')->name('uitgifte');
