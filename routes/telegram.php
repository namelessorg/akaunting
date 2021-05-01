<?php

use App\Http\Controllers\Telegram\Webhook;
use Illuminate\Support\Facades\Route;

// telegram
Route::post('{token}/hook', [Webhook::class, 'handle'])->name('webhook_url');
