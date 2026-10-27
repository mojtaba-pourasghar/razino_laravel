<?php

return [
    /*
     * Which sender to use. Only 'log' ships with the server; it writes the
     * message to the Laravel log instead of sending it, which is what a
     * developer wants and what a production server must NOT be left on.
     */
    'driver' => env('SMS_DRIVER', 'log'),

    /* The sender line the provider assigned, when there is one. */
    'from' => env('SMS_FROM'),

    'api_key' => env('SMS_API_KEY'),
];
