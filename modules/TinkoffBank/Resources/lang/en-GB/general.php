<?php

return [

    'name'              => 'Tinkoff bank',
    'description'       => 'Enable the standard payment option of Tinkoff',

    'form' => [
        'terminal_key' => 'Terminal key',
        'secret_key' => 'Secret key',
        'mode' => 'Mode',
        'taxation' => 'Taxation scheme',
        'debug' => 'Debug',
        'transaction' => 'Transaction',
        'customer' => 'Show to Customer',
        'order' => 'Order',
    ],

    'test_mode'         => 'Warning: The payment gateway is in \'Sandbox Mode\'. Your account will not be charged.',
    //'description'       => 'Pay with PAYPAL',

];
