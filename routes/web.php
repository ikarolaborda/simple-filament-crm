<?php

use App\Http\Controllers\QuotePdfController;
use App\Livewire\AcceptInvitation;
use Illuminate\Support\Facades\Route;

Route::middleware('signed')
    ->get('invitation/{invitation}/accept', AcceptInvitation::class)
    ->name('invitation.accept');

Route::middleware('signed')
    ->get('quotes/{quote}/pdf', QuotePdfController::class)
    ->name('quotes.pdf');
