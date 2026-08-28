<?php

namespace Database\Seeders;

use Database\Seeders\ActingAdminRoleSeeder;
use Database\Seeders\ActingAdminSeeder;
use Database\Seeders\ClassSeeder;
use Database\Seeders\ClassSubjectSeeder;
use Database\Seeders\ExamConfigSeeder;
use Database\Seeders\ExamSeeder;
use Database\Seeders\FeeDiscountSeeder;
use Database\Seeders\FeePaymentSeeder;
use Database\Seeders\FeeStructureSeeder;
use Database\Seeders\FeeTypeSeeder;
use Database\Seeders\FundTransactionSeeder;
use Database\Seeders\LeaveApplicationSeeder;
use Database\Seeders\LeaveTypeSeeder;
use Database\Seeders\NoticeSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\PublicHolidaySeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SalaryComponentSeeder;
use Database\Seeders\SalaryInvoiceSeeder;
use Database\Seeders\SalaryPaymentSeeder;
use Database\Seeders\SalaryStructureSeeder;
use Database\Seeders\SchoolAccountSeeder;
use Database\Seeders\StaffAttendanceSeeder;
use Database\Seeders\StaffSeeder;
use Database\Seeders\StudentAttendanceSeeder;
use Database\Seeders\StudentFeeDiscountSeeder;
use Database\Seeders\StudentFeeInvoiceSeeder;
use Database\Seeders\StudentResultSeeder;
use Database\Seeders\StudentSeeder;
use Database\Seeders\SubjectSeeder;
use Database\Seeders\TeacherAttendanceSeeder;
use Database\Seeders\TeacherSeeder;
use Database\Seeders\TeacherSubjectSeeder;
use Database\Seeders\TransactionCategorySeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            PermissionSeeder::class,
            ActingAdminRoleSeeder::class,
            UserSeeder::class,
            LeaveTypeSeeder::class,
            PublicHolidaySeeder::class,
            TeacherSeeder::class,
            ClassSeeder::class,
            StudentSeeder::class,
            StudentAttendanceSeeder::class,
            TeacherAttendanceSeeder::class,
            ActingAdminSeeder::class,
            NoticeSeeder::class,
            StaffSeeder::class,
            StaffAttendanceSeeder::class,
            LeaveApplicationSeeder::class,
            SalaryComponentSeeder::class,
            SalaryStructureSeeder::class,
            SalaryInvoiceSeeder::class,
            SalaryPaymentSeeder::class,
            SubjectSeeder::class,
            ClassSubjectSeeder::class,
            TeacherSubjectSeeder::class,
            ExamConfigSeeder::class,
            FeeTypeSeeder::class,
            FeeStructureSeeder::class,
            FeeDiscountSeeder::class,
            StudentFeeDiscountSeeder::class,
            ExamSeeder::class,
            StudentFeeInvoiceSeeder::class,
            FeePaymentSeeder::class,
            SchoolAccountSeeder::class,
            TransactionCategorySeeder::class,
            FundTransactionSeeder::class,
            StudentResultSeeder::class,
            DocumentSettingsSeeder::class,
        ]);
    }
}
