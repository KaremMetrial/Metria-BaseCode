<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API routes
|--------------------------------------------------------------------------
| Versioned under /api/v1. New breaking versions get their own file
| (routes/api_v2.php) and prefix without touching v1 consumers.
*/

Route::prefix('v1')->group(base_path('routes/api_v1.php'));
