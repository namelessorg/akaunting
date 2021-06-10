<?php

use Illuminate\Support\Facades\Route;

/**
 * 'signed' middleware and 'signed/paypal-standard' prefix applied to all routes (including names)
 *
 * @see \App\Providers\Route::register
 */

Route::signed('tinkoff-bank', function () {
    Route::get('invoices/{invoice}', 'Payment@show')->name('invoices.show');
    Route::any('invoices/{invoice}/fail', 'Payment@fail')->name('invoices.fail');
    Route::any('invoices/{invoice}/success', 'Payment@success')->name('invoices.success');
});
