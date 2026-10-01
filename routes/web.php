<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

// Serve the Vue SPA for every non-API URL; Vue Router takes over client-side.
Route::get('/{any?}', fn () => view('app'))->where('any', '^(?!api(/|$)|up$).*$');
