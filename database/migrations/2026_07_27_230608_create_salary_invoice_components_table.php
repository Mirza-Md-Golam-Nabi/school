<?php

use App\Enums\SalaryComponentType;
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
        Schema::create('salary_invoice_components', function (Blueprint $table) {
            $table->id();
            $table->foreignId('salary_invoice_id')->constrained('salary_invoices')->cascadeOnDelete();
            $table->foreignId('salary_component_id')->nullable()->constrained('salary_components')->nullOnDelete();
            // name/type নিচে denormalized রাখা হলো যাতে পরে salary_component রিনেম/ডিলিট হলেও পুরনো payslip-এর তথ্য অপরিবর্তিত থাকে
            $table->string('name');
            $table->enum('type', array_column(SalaryComponentType::cases(), 'value'));
            $table->decimal('amount', 10, 2);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('salary_invoice_components');
    }
};
