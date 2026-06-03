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
        Schema::create('student_fee_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('student_profiles')->cascadeOnDelete();
            $table->foreignId('fee_type_id')->constrained('fee_types')->cascadeOnDelete();
            $table->tinyInteger('month')->unsigned()->nullable(); // null for one-time fees without specific month
            $table->unsignedSmallInteger('year');
            $table->decimal('original_amount', 10, 2);
            $table->decimal('discount_amount', 10, 2)->default(0);
            $table->decimal('fine_amount', 10, 2)->default(0);
            $table->decimal('waiver_amount', 10, 2)->default(0);
            $table->decimal('net_amount', 10, 2); // original - discount + fine - waiver
            $table->foreignId('waiver_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('waiver_reason')->nullable();
            $table->enum('status', array_column(InvoiceStatus::cases(), 'value'))->default(InvoiceStatus::Unpaid); // unpaid, partial, paid, waived
            $table->timestamps();

            $table->unique(['student_id', 'fee_type_id', 'month', 'year'], 'unique_student_invoice');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_fee_invoices');
    }
};
