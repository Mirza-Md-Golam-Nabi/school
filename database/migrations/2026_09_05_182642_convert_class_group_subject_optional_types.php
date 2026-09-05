<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * এতদিন class_group_subject-এ main_optional/extra_optional আলাদাভাবে সেট করা হতো,
     * কিন্তু main/extra optional বাছাই আসলে student-ভিত্তিক (দেখুন student_optional_subjects
     * টেবিল)। তাই class+group লেভেলে এখন শুধু compulsory/optional থাকবে — বিদ্যমান
     * main_optional ও extra_optional value-গুলো optional-এ রূপান্তর করা হচ্ছে।
     */
    public function up(): void
    {
        DB::table('class_group_subject')
            ->whereIn('subject_type', ['main_optional', 'extra_optional'])
            ->update(['subject_type' => 'optional']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // মূল main_optional/extra_optional value কোনটা ছিল তা আর জানার উপায় নেই,
        // তাই rollback-এ সবকিছু optional-ই থেকে যাবে।
    }
};
