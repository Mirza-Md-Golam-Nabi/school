<?php

use App\Enums\InvoiceStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('salary_invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_no')->unique(); // SAL-2026-07-0001, প্রতি মাসে রিসেট হয়
            $table->morphs('profileable'); // teacher_profiles, staff_profiles
            $table->foreignId('salary_structure_id')->nullable()->constrained('salary_structures')->nullOnDelete(); // manual invoice হলে ফাঁকা
            $table->unsignedTinyInteger('month');
            $table->unsignedSmallInteger('year');
            $table->decimal('gross_amount', 10, 2);
            $table->decimal('deduction_amount', 10, 2)->default(0);
            $table->decimal('net_amount', 10, 2);
            $table->enum('status', array_column(InvoiceStatus::cases(), 'value'))->default(InvoiceStatus::Unpaid->value);
            $table->boolean('is_manual')->default(false); // বাল্ক জেনারেটেড নাকি admin ম্যানুয়ালি বানিয়েছে
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['profileable_type', 'profileable_id', 'year', 'month'], 'salary_invoices_profileable_year_month_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('salary_invoices');
    }
};
