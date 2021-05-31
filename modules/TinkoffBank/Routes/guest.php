<?php

use Illuminate\Support\Facades\Route;

/**
 * 'guest' middleware and 'portal/paypal-standard' prefix applied to all routes (including names)
 *
 * @see \App\Providers\Route::register
 */

Route::portal('tinkoff-bank', function () {
    Route::get('invoices/{invoice}/fail', 'Payment@fail')->name('invoices.fail');
    Route::get('invoices/{invoice}/success', 'Payment@success')->name('invoices.success');
    Route::any('invoices/{invoice}/notify', 'Payment@complete')->name('invoices.complete');
}, ['middleware' => 'guest']);
