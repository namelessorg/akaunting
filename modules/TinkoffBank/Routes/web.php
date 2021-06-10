<?php

// routes without company

Route::module('tinkoff-bank', function () {
    Route::post('payment/notification', 'Payment@notification')->name('invoices.notification');
}, ['middleware' => 'payment', 'no_company' => true, ]);
