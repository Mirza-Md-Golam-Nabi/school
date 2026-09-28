<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Automatic Device Enrollment
    |--------------------------------------------------------------------------
    |
    | When on, every newly created student, teacher or staff profile is given an
    | enroll ID on all active attendance devices straight away. Turn it off before a
    | large bulk import and use "Auto-Assign Enroll IDs" on the device once afterwards.
    |
    */

    'auto_enroll' => (bool) env('ATTENDANCE_AUTO_ENROLL', true),

    /*
    |--------------------------------------------------------------------------
    | Device Removal Grace Period
    |--------------------------------------------------------------------------
    |
    | When a student graduates or transfers, their device enrollment is scheduled
    | for removal, but the sync client only deletes them from the device after this
    | many hours — long enough to notice and undo a wrongly changed status, since
    | deleting a user from the device also loses their card and fingerprints.
    | Removals an admin approves by hand (e.g. dropped students) are not delayed.
    |
    */

    'removal_grace_hours' => (int) env('ATTENDANCE_REMOVAL_GRACE_HOURS', 24),

    /*
    |--------------------------------------------------------------------------
    | Removals Per Sync
    |--------------------------------------------------------------------------
    |
    | The most users the sync plan asks the device to delete in one run. A bulk
    | graduation is simply spread over a few consecutive syncs.
    |
    */

    'max_removals_per_sync' => (int) env('ATTENDANCE_MAX_REMOVALS_PER_SYNC', 50),

];
