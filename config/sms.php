<?php

return [
    /*
     * Available drivers: 'log', 'null', 'gov', 'twilio'
     */
    'sms_driver' => env('SMS_DRIVER', 'log'),

    /*
     * Twilio credentials.
     */
    'twilio' => [
        'sid' => env('TWILIO_SID'),
        'token' => env('TWILIO_TOKEN'),
        'from' => env('TWILIO_FROM', 'LOOP'),
    ],
];
