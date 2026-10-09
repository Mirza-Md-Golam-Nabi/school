<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * WHERE / JOIN / ORDER BY-তে ব্যবহৃত কলামগুলোর index।
     *
     * SQLite foreign key-তে নিজে থেকে index বানায় না, তাই FK কলামগুলো এখানে
     * আলাদা করে index করা হয়েছে। MySQL-এ FK index আগে থেকেই থাকে — সেজন্য
     * প্রতিটা index যোগ করার আগে একই কলামের index আছে কি না চেক করা হয়।
     *
     * @var array<string, list<list<string>>>
     */
    private const INDEXES = [
        'account_transactions' => [['account_id'], ['source_type', 'source_id'], ['transaction_date']],
        'acting_admins' => [['user_id']],
        'activity_log' => [['created_at']],
        'admit_cards' => [['exam_id']],
        'attendance_notifications' => [['attendance_id']],
        'attendance_status_changes' => [['attendance_id'], ['student_profile_id'], ['class_id']],
        'attendances' => [['class_id', 'date'], ['attendable_type', 'date', 'status']],
        'class_group_subject' => [['subject_id']],
        'class_groups' => [['class_id'], ['group_id']],
        'classes' => [['class_teacher_id']],
        'exam_contribute_rules' => [['source_exam_type_id'], ['target_exam_type_id', 'class_id', 'session_year']],
        'exam_type_configs' => [['exam_type_id']],
        'exams' => [['class_id', 'session_year'], ['exam_type_id']],
        'fee_payments' => [['student_id'], ['invoice_id'], ['payment_date']],
        'fee_structures' => [['fee_type_id']],
        'fee_types' => [['school_account_id']],
        'fund_transactions' => [['transaction_category_id'], ['school_account_id'], ['transaction_date']],
        'late_fee_rules' => [['class_id']],
        'leave_applications' => [['leave_type_id'], ['status']],
        'leave_excess_logs' => [['leave_application_id']],
        'marksheets' => [['exam_id']],
        'notice_reads' => [['user_id']],
        'push_notification_deliveries' => [['push_subscription_id'], ['received_at', 'last_sent_at']],
        'salary_bulk_payments' => [['school_account_id']],
        'salary_invoice_components' => [['salary_invoice_id']],
        'salary_invoice_deductions' => [['salary_invoice_id']],
        'salary_invoices' => [['salary_structure_id'], ['status'], ['year', 'month']],
        'salary_payments' => [['salary_invoice_id'], ['bulk_payment_id'], ['school_account_id'], ['payment_date']],
        'salary_structure_components' => [['salary_structure_id']],
        'sections' => [['class_id']],
        'staff_profiles' => [['user_id']],
        'student_class_history' => [['class_id', 'session_year']],
        'student_fee_discounts' => [['student_id'], ['fee_type_id'], ['discount_id']],
        'student_fee_invoices' => [['status'], ['fee_type_id', 'year'], ['year', 'month']],
        'student_merit_rankings' => [['student_id']],
        'student_optional_subjects' => [['class_id', 'subject_id']],
        'student_profiles' => [['user_id'], ['current_class_id', 'status', 'roll_no'], ['current_section_id'], ['current_group_id'], ['status']],
        'student_results' => [['student_id', 'exam_id']],
        'teacher_profiles' => [['user_id']],
        'teacher_subjects' => [['class_id', 'session_year']],
    ];

    public function up(): void
    {
        foreach (self::INDEXES as $tableName => $indexes) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            foreach ($indexes as $columns) {
                if (Schema::hasIndex($tableName, $columns)) {
                    continue;
                }

                Schema::table($tableName, function (Blueprint $table) use ($tableName, $columns): void {
                    $table->index($columns, $this->indexName($tableName, $columns));
                });
            }
        }
    }

    public function down(): void
    {
        foreach (self::INDEXES as $tableName => $indexes) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            foreach ($indexes as $columns) {
                $indexName = $this->indexName($tableName, $columns);

                if (! Schema::hasIndex($tableName, $indexName)) {
                    continue;
                }

                Schema::table($tableName, function (Blueprint $table) use ($indexName): void {
                    $table->dropIndex($indexName);
                });
            }
        }
    }

    /**
     * MySQL-এর identifier সীমা ৬৪ অক্ষর, তাই লম্বা নাম hash দিয়ে ছোট করা হয়।
     *
     * @param  list<string>  $columns
     */
    private function indexName(string $tableName, array $columns): string
    {
        $name = $tableName.'_'.implode('_', $columns).'_perf_idx';

        return strlen($name) <= 64
            ? $name
            : substr($name, 0, 46).'_'.substr(md5($name), 0, 8).'_perf_idx';
    }
};
