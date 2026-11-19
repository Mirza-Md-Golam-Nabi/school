<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sms_logs', function (Blueprint $table) {
            $table->string('event')->nullable()->after('source_id'); // কোন ঘটনার SMS (SmsEvent)
            $table->string('recipient_name')->nullable()->after('phone');
            $table->unsignedSmallInteger('parts')->default(1)->after('message');
            $table->decimal('cost', 10, 4)->default(0)->after('parts');
            $table->uuid('batch_id')->nullable()->after('cost');
            $table->foreignId('sms_vendor_id')->nullable()->after('batch_id')->constrained('sms_vendors')->nullOnDelete();
            $table->string('vendor_message_id')->nullable()->after('sms_vendor_id');
            $table->text('error_message')->nullable()->after('status');

            $table->index('batch_id');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::table('sms_logs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sms_vendor_id');
            $table->dropIndex(['batch_id']);
            $table->dropIndex(['created_at']);
            $table->dropColumn(['event', 'recipient_name', 'parts', 'cost', 'batch_id', 'vendor_message_id', 'error_message']);
        });
    }
};
