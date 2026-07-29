<?php

use App\Livewire\ResultPage;
use App\Livewire\RoomBiddingTool;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome');

// The tool is public — guests can use it (results just aren't saved).
Route::get('tool', RoomBiddingTool::class)->name('tool');

Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

// Public permanent result page — shareable, read-only.
Route::get('result/{token}', ResultPage::class)->middleware('throttle:60,1')->name('result');

Route::view('profile', 'profile')
    ->middleware(['auth'])
    ->name('profile');

require __DIR__.'/auth.php';
