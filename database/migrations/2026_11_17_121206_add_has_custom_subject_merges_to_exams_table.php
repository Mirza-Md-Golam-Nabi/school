<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * পরীক্ষার মার্জ সাবজেক্ট কোথা থেকে আসে:
     * false = ক্লাসের ডিফল্ট মার্জ অনুসরণ করে (ক্লাসে বদলালে এখানেও বদলায়),
     * true = এই পরীক্ষায় নিজে বদলানো হয়েছে (ক্লাসের বদল আর এখানে আসে না)।
     *
     * আগে থেকে যেসব পরীক্ষায় মার্জ আছে, সেগুলো হাতে বসানো — তাই নিজস্ব ধরা হলো,
     * যাতে ক্লাসের ডিফল্ট সেগুলো মুছে না দেয়।
     */
    public function up(): void
    {
        Schema::table('exams', function (Blueprint $table) {
            $table->boolean('has_custom_subject_merges')->default(false)->after('is_published');
        });

        DB::table('exams')
            ->whereIn('id', DB::table('exam_subject_merges')->select('exam_id'))
            ->update(['has_custom_subject_merges' => true]);
    }

    public function down(): void
    {
        Schema::table('exams', function (Blueprint $table) {
            $table->dropColumn('has_custom_subject_merges');
        });
    }
};
