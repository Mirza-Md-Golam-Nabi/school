<?php

use App\Models\StaffProfile;
use App\Models\StudentProfile;
use App\Models\TeacherProfile;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * @var array<string, class-string<Model>>
     */
    private array $profileModels = [
        'student_profiles' => StudentProfile::class,
        'teacher_profiles' => TeacherProfile::class,
        'staff_profiles' => StaffProfile::class,
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach ($this->profileModels as $table => $model) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->string('id_card_token', 32)->nullable()->unique()->after('user_id'); // আইডি কার্ডের QR-এর যাচাই লিংকের গোপন টোকেন — কার্ড হারালে নতুন টোকেন দিলে পুরনো কার্ড বাতিল
            });

            $model::query()
                ->withTrashed()
                ->whereNull('id_card_token')
                ->select('id')
                ->chunkById(500, function (Collection $profiles) use ($model): void {
                    foreach ($profiles as $profile) {
                        $profile->forceFill(['id_card_token' => $model::newIdCardToken()])->saveQuietly();
                    }
                });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (array_keys($this->profileModels) as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropUnique(['id_card_token']);
                $blueprint->dropColumn('id_card_token');
            });
        }
    }
};
