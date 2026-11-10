<?php

use App\Models\Subject;
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
        Schema::table('subjects', function (Blueprint $table) {
            $table->unsignedSmallInteger('sort_order')->default(0)->after('code')->index(); // মার্কশীট/রেজাল্টে সাবজেক্টের ক্রম (ছোট সংখ্যা আগে)
        });

        // Existing subjects keep the order they were created in.
        Subject::withTrashed()
            ->orderBy('id')
            ->pluck('id')
            ->each(fn (int $subjectId, int $index) => Subject::withTrashed()
                ->whereKey($subjectId)
                ->toBase()
                ->update(['sort_order' => $index + 1]));
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subjects', function (Blueprint $table) {
            $table->dropIndex(['sort_order']);
            $table->dropColumn('sort_order');
        });
    }
};
