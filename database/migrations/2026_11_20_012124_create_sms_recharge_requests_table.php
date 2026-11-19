<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sms_recharge_requests', function (Blueprint $table) {
            $table->id();
            $table->decimal('amount', 12, 2);
            $table->decimal('rate_per_sms', 8, 4); // রিকুয়েস্টের সময়কার রেট
            $table->unsignedInteger('estimated_sms'); // এই টাকায় কতগুলো SMS পার্ট
            $table->text('note')->nullable();
            $table->string('status')->default('pending'); // pending = অপেক্ষমাণ, completed = ব্যালেন্স যোগ হয়েছে
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('delivered_at')->nullable(); // রিকুয়েস্ট লিংকে সফলভাবে পাঠানোর সময়
            $table->text('delivery_error')->nullable();
            $table->decimal('credited_amount', 12, 2)->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sms_recharge_requests');
    }
};
