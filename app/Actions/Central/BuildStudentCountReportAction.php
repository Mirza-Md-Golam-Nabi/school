<?php

namespace App\Actions\Central;

use App\Models\StudentProfile;

class BuildStudentCountReportAction
{
    /**
     * এই স্কুলের মোট active student সংখ্যা — মাসিক push আর সেন্ট্রালের pull, দুই
     * জায়গাতেই হুবহু এই রিপোর্টটাই যায়। শুধু সংখ্যা যায়, কোনো student-এর তথ্য নয়।
     *
     * @return array{school_id: string, total_students: int, reported_at: string}
     */
    public function handle(): array
    {
        return [
            'school_id' => (string) config('central.school_id'),
            'total_students' => StudentProfile::query()->active()->count(),
            'reported_at' => now()->toIso8601String(),
        ];
    }
}
