<?php

return [

    /*
     * Where an admin's SMS recharge request is POSTed (JSON, signed with the
     * central secret when one is configured). Leave empty until the link is
     * known — requests are still saved here and can be resent later.
     */
    'recharge_request_url' => env('SMS_RECHARGE_REQUEST_URL', ''),

    'drivers' => [
        'bulksmsbd' => [
            'base_url' => env('SMS_BULKSMSBD_BASE_URL', 'http://bulksmsbd.net/api'),
        ],
    ],

    /*
     * How long (seconds) a provider's real balance is cached for the internal
     * "can the provider cover this batch" check.
     */
    'provider_balance_cache_seconds' => 60,

    /*
     * At most one "SMS balance is too low" alert per this many minutes, so an
     * automatic sender (device sync, scheduler) can't flood the admins.
     */
    'shortfall_alert_minutes' => 60,

];
