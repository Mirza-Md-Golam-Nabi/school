<?php

return [

    /*
     * Base URL of the central app that collects each school's student count,
     * e.g. https://central.example.com — the monthly report is POSTed to
     * {url}{report_path}. Leave empty to switch reporting off for this school.
     */
    'url' => rtrim((string) env('CENTRAL_URL', ''), '/'),

    'report_path' => env('CENTRAL_REPORT_PATH', '/api/school-reports'),

    /*
     * The identifier the central app gave this school's installation. It is
     * how the central app tells which school a report came from, and which
     * secret to verify the signature with.
     */
    'school_id' => env('CENTRAL_SCHOOL_ID'),

    /*
     * Shared secret between this installation and the central app. It never
     * travels over the wire — it only signs requests (HMAC-SHA256), in both
     * directions: reports this school pushes, and pull requests the central
     * app makes to this school.
     */
    'secret' => env('CENTRAL_SECRET'),

    /*
     * How old a signed pull request from the central app may be, in seconds,
     * before it is rejected — limits how long a captured request can be replayed.
     */
    'signature_tolerance_seconds' => (int) env('CENTRAL_SIGNATURE_TOLERANCE_SECONDS', 300),

];
