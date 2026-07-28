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
        Schema::create('salary_bulk_payments', function (Blueprint $table) {
            $table->id();
            // এক admin action-এ একাধিক salary_payments তৈরি হলে (একজনের একাধিক মাস অথবা একাধিক teacher/staff) সেগুলোকে গ্রুপ করার wrapper
            $table->decimal('total_amount', 10, 2);
            $table->foreignId('school_account_id')->constrained('school_accounts');
            $table->enum('payment_method', array_column(PaymentMethod::cases(), 'value'));
            $table->string('transaction_id')->nullable();
            $table->date('payment_date');
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
        Schema::dropIfExists('salary_bulk_payments');
    }
};
