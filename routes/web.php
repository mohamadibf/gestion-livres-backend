<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Application React compilée (public/index.html) : toute URL qui n'est pas une route Laravel
// retombe sur elle, et c'est React Router qui affiche la bonne page. En développement local
// (sans build du frontend), on garde la page d'accueil Laravel.
Route::fallback(function (Request $request) {
    abort_if($request->is('api/*', 'storage/*', 'sanctum/*', 'up'), 404);

    $index = public_path('index.html');

    return file_exists($index)
        ? response()->file($index, ['Cache-Control' => 'no-cache'])
        : view('welcome');
});

Route::get('/', function () {
    $index = public_path('index.html');

    return file_exists($index)
        ? response()->file($index, ['Cache-Control' => 'no-cache'])
        : view('welcome');
});
