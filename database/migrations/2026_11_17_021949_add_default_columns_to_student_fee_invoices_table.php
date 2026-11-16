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
        Schema::table('student_fee_invoices', function (Blueprint $table) {
            // unpaid = বকেয়া, partial = আংশিক পরিশোধ, paid = পরিশোধিত, waived = মওকুফ, defaulted = অনাদায়ী (খেলাপি)
            $table->enum('status', array_column(InvoiceStatus::cases(), 'value'))
                ->default(InvoiceStatus::Unpaid->value)
                ->change();

            // Default করার সময় যে বকেয়া অংশ অনাদায়ী ধরা হয়েছে (net_amount − ততক্ষণে পরিশোধিত)
            $table->decimal('default_amount', 10, 2)->default(0)->after('waiver_reason');
            $table->foreignId('defaulted_by')->nullable()->after('default_amount')->constrained('users')->nullOnDelete();
            $table->timestamp('defaulted_at')->nullable()->after('defaulted_by');
            $table->text('default_reason')->nullable()->after('defaulted_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('student_fee_invoices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('defaulted_by');
            $table->dropColumn(['default_amount', 'defaulted_at', 'default_reason']);
        });

        Schema::getConnection()
            ->table('student_fee_invoices')
            ->where('status', InvoiceStatus::Defaulted->value)
            ->update(['status' => InvoiceStatus::Unpaid->value]);

        Schema::table('student_fee_invoices', function (Blueprint $table) {
            $table->enum('status', ['unpaid', 'partial', 'paid', 'waived'])
                ->default(InvoiceStatus::Unpaid->value)
                ->change();
        });
    }
};
