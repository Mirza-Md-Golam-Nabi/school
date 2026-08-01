<?php

use App\Enums\PaymentMethod;
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
        Schema::create('salary_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('salary_invoice_id')->constrained('salary_invoices')->cascadeOnDelete();
            $table->decimal('amount_paid', 10, 2);
            $table->enum('payment_method', array_column(PaymentMethod::cases(), 'value'));
            $table->string('transaction_id')->nullable();
            $table->date('payment_date');
            $table->foreignId('school_account_id')->constrained('school_accounts');
            $table->foreignId('bulk_payment_id')->nullable()->constrained('salary_bulk_payments')->nullOnDelete();
            $table->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('remarks')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('salary_payments');
    }
};
