<?php

use App\Livewire\AcceptInvitation;
use Illuminate\Support\Facades\Route;

Route::middleware('signed')
    ->get('invitation/{invitation}/accept', AcceptInvitation::class)
    ->name('invitation.accept');
