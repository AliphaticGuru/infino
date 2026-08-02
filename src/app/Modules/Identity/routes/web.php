<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use App\Modules\Identity\Http\Controllers\Auth\LoginController;

/*
|--------------------------------------------------------------------------
| Identity Web Routes
|--------------------------------------------------------------------------
*/

Route::middleware(['web'])
    ->group(function () {
        
        Route::post('/login', [
            LoginController::class, 'store'
            ])->name('login');
    });