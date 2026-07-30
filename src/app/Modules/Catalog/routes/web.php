<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Catalog Web Routes
|--------------------------------------------------------------------------
*/

Route::middleware(['web'])
    ->group(function () {
        //
    });

Route::get('/catalog-test', function () {
    return 'Catalog module is working!';
});