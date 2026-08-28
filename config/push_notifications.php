<?php

return [

    /*
     * How long to wait, after a send attempt, before considering a still-
     * unacknowledged delivery due for a resend. Also drives how often the
     * webpush:resend-unacknowledged scheduled command runs.
     */
    'retry_interval_minutes' => (int) env('PUSH_NOTIFICATION_RETRY_INTERVAL_MINUTES', 10),

    /*
     * Stop retrying a delivery after this many total send attempts (the
     * original send counts as attempt 1).
     */
    'max_attempts' => (int) env('PUSH_NOTIFICATION_MAX_ATTEMPTS', 5),

];
