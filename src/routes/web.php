<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');
});

// Route::view('post/create', 'pages.post.⚡create')->name('post.create');

Route::livewire('/post/create', 'pages::post.create')->name('post.create');

require __DIR__.'/settings.php';
