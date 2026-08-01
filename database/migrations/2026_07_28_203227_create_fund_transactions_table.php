<?php

use App\Enums\TransactionType;
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
        Schema::create('fund_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_category_id')->constrained('transaction_categories');
            $table->foreignId('school_account_id')->constrained('school_accounts');
            $table->enum('type', array_column(TransactionType::cases(), 'value')); // category থেকে snapshot হয় তৈরির সময়
            $table->string('title');
            $table->decimal('amount', 15, 2);
            $table->date('transaction_date');
            $table->string('party_name')->nullable(); // vendor/donor-এর নাম
            $table->text('description')->nullable();
            $table->string('attachment_path')->nullable(); // receipt/bill, private disk-এ থাকে
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fund_transactions');
    }
};
