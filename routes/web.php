<?php

use Illuminate\Support\Facades\Route;

Route::get('/health', function () {
    return response()->json([
        'status' => 'OK',
        'message' => 'Laravel fonctionne',
        'timestamp' => now(),
    ]);
});
