<?php

use App\Livewire\JoinSession;
use App\Livewire\ResultPage;
use App\Livewire\SessionManage;
use App\Livewire\SetupWizard;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome');

Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware(['auth'])->group(function () {
    Route::get('sessions/create', SetupWizard::class)->name('sessions.create');
    Route::get('sessions/{session}/manage', SessionManage::class)->name('sessions.manage');
});

// Public member join — no account required.
Route::get('join/{token}', JoinSession::class)->middleware('throttle:60,1')->name('join');

// Public permanent result page (Q7) — survives invite-link expiry.
Route::get('result/{token}', ResultPage::class)->middleware('throttle:60,1')->name('result');

Route::view('profile', 'profile')
    ->middleware(['auth'])
    ->name('profile');

require __DIR__.'/auth.php';
