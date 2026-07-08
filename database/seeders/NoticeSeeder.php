<?php

namespace Database\Seeders;

use App\Actions\Notice\SyncNoticeTargetsAction;
use App\Enums\NoticeTargetType;
use App\Enums\UserType;
use App\Models\Classes;
use App\Models\Notice;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class NoticeSeeder extends Seeder
{
    /** @var array<int, array{title: string, body: string}> */
    private array $notices = [
        ['title' => 'বার্ষিক ক্রীড়া প্রতিযোগিতা ২০২৬', 'body' => 'আগামী ১৫ ডিসেম্বর বিদ্যালয় মাঠে বার্ষিক ক্রীড়া প্রতিযোগিতা অনুষ্ঠিত হবে। সকল শিক্ষার্থীকে অংশগ্রহণের জন্য অনুরোধ করা হলো।'],
        ['title' => 'অর্ধ-বার্ষিক পরীক্ষার রুটিন প্রকাশ', 'body' => 'অর্ধ-বার্ষিক পরীক্ষার সময়সূচি নোটিশ বোর্ডে প্রকাশ করা হয়েছে। সকলকে রুটিন অনুযায়ী প্রস্তুতি নেওয়ার জন্য বলা হলো।'],
        ['title' => 'স্বাধীনতা দিবস উপলক্ষে বিদ্যালয় বন্ধ', 'body' => 'মহান স্বাধীনতা দিবস উপলক্ষে ২৬ মার্চ বিদ্যালয় বন্ধ থাকবে।'],
        ['title' => 'অভিভাবক সমাবেশ অনুষ্ঠিত হবে', 'body' => 'আগামী শুক্রবার সকাল ১০টায় অভিভাবক সমাবেশ অনুষ্ঠিত হবে। সকল অভিভাবককে উপস্থিত থাকার অনুরোধ করা হলো।'],
        ['title' => 'বার্ষিক পরীক্ষার ফলাফল প্রকাশ', 'body' => 'বার্ষিক পরীক্ষার ফলাফল বিদ্যালয়ের নোটিশ বোর্ডে প্রকাশ করা হয়েছে।'],
        ['title' => 'নতুন শিক্ষাবর্ষে ভর্তি বিজ্ঞপ্তি', 'body' => 'আগামী শিক্ষাবর্ষের ভর্তি কার্যক্রম শুরু হয়েছে। আগ্রহী অভিভাবকদের অফিসে যোগাযোগ করতে অনুরোধ করা হলো।'],
        ['title' => 'শিক্ষক-অভিভাবক সভা', 'body' => 'মাসিক শিক্ষক-অভিভাবক সভা আগামী শনিবার অনুষ্ঠিত হবে।'],
        ['title' => 'বৃত্তি পরীক্ষার প্রস্তুতি ক্লাস', 'body' => 'মেধাবী শিক্ষার্থীদের জন্য বৃত্তি পরীক্ষার বিশেষ প্রস্তুতি ক্লাস শুরু হচ্ছে।'],
        ['title' => 'স্কুল ইউনিফর্ম পরিবর্তন সংক্রান্ত নোটিশ', 'body' => 'আগামী শিক্ষাবর্ষ থেকে নতুন ইউনিফর্ম চালু করা হবে। বিস্তারিত তথ্যের জন্য অফিসে যোগাযোগ করুন।'],
        ['title' => 'মাসিক বেতন পরিশোধের সময়সীমা', 'body' => 'চলতি মাসের বেতন আগামী ১০ তারিখের মধ্যে পরিশোধ করার জন্য অনুরোধ করা হলো।'],
        ['title' => 'শিক্ষক নিয়োগ বিজ্ঞপ্তি', 'body' => 'বিদ্যালয়ে সহকারী শিক্ষক পদে নিয়োগের জন্য আবেদন আহ্বান করা হচ্ছে।'],
        ['title' => 'জাতীয় শোক দিবস পালন', 'body' => '১৫ আগস্ট জাতীয় শোক দিবস যথাযথ মর্যাদায় পালন করা হবে।'],
        ['title' => 'বিজয় দিবস উদযাপন', 'body' => 'মহান বিজয় দিবস উপলক্ষে বিদ্যালয়ে আলোচনা সভা ও সাংস্কৃতিক অনুষ্ঠানের আয়োজন করা হয়েছে।'],
        ['title' => 'ঈদ উপলক্ষে ছুটির ঘোষণা', 'body' => 'ঈদ-উল-ফিতর উপলক্ষে বিদ্যালয় কয়েক দিন বন্ধ থাকবে। ছুটির বিস্তারিত সময়সূচি নোটিশ বোর্ডে দেওয়া হয়েছে।'],
        ['title' => 'টিউটোরিয়াল পরীক্ষার সময়সূচি', 'body' => 'চতুর্থ টিউটোরিয়াল পরীক্ষার রুটিন প্রকাশ করা হয়েছে।'],
        ['title' => 'শ্রেণিকক্ষ পরিবর্তন সংক্রান্ত নোটিশ', 'body' => 'কিছু শ্রেণির কক্ষ পরিবর্তন করা হয়েছে। বিস্তারিত জানতে অফিসে যোগাযোগ করুন।'],
        ['title' => 'বার্ষিক সাংস্কৃতিক অনুষ্ঠান', 'body' => 'আগামী মাসে বার্ষিক সাংস্কৃতিক অনুষ্ঠান অনুষ্ঠিত হবে। অংশগ্রহণেচ্ছুক শিক্ষার্থীরা নাম নিবন্ধন করুন।'],
        ['title' => 'লাইব্রেরি বই জমাদানের নোটিশ', 'body' => 'সকল শিক্ষার্থীকে ধার করা লাইব্রেরি বই নির্ধারিত সময়ের মধ্যে জমা দেওয়ার অনুরোধ করা হলো।'],
        ['title' => 'আসন্ন বৃত্তি পরীক্ষা সংক্রান্ত জরুরি নোটিশ', 'body' => 'বৃত্তি পরীক্ষা সংক্রান্ত গুরুত্বপূর্ণ তথ্য শীঘ্রই জানানো হবে। প্রস্তুত থাকার জন্য অনুরোধ করা হলো।'],
        ['title' => 'প্রথম শ্রেণির অভিভাবক সভা', 'body' => 'প্রথম শ্রেণির শিক্ষার্থীদের অভিভাবকদের নিয়ে একটি বিশেষ সভা আয়োজন করা হবে।'],
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $createdBy = User::where('user_type', UserType::Admin)->value('id')
            ?? User::query()->value('id');

        if (! $createdBy) {
            return;
        }

        $targetTypes = NoticeTargetType::cases();
        $classIds = Classes::active()->pluck('id');
        $studentUserIds = StudentProfile::active()->pluck('user_id');
        $teacherUserIds = User::where('user_type', UserType::Teacher)->pluck('id');

        $syncTargets = app(SyncNoticeTargetsAction::class);

        foreach ($this->notices as $index => $data) {
            if (Notice::where('title', $data['title'])->exists()) {
                continue;
            }

            $targetType = $targetTypes[$index % count($targetTypes)];

            $notice = Notice::create([
                'title' => $data['title'],
                'body' => $data['body'],
                'target_type' => $targetType,
                'send_sms' => fake()->boolean(30),
                'published_at' => $this->publishedAtFor($index),
                'created_by' => $createdBy,
            ]);

            if (! $targetType->requiresTargets()) {
                continue;
            }

            $targetIds = match ($targetType) {
                NoticeTargetType::ByClass => $this->pickRandomIds($classIds, 3),
                NoticeTargetType::IndividualStudent => $this->pickRandomIds($studentUserIds, 3),
                NoticeTargetType::IndividualTeacher => $this->pickRandomIds($teacherUserIds, 3),
                default => [],
            };

            $syncTargets->handle($notice, $targetIds);
        }
    }

    private function publishedAtFor(int $index): ?Carbon
    {
        return match ($index) {
            18 => null, // draft, not yet published
            19 => now()->addDays(fake()->numberBetween(3, 14)), // scheduled for the future
            default => now()->subDays(fake()->numberBetween(1, 180)),
        };
    }

    /**
     * @param  Collection<int, int>  $ids
     * @return array<int, int>
     */
    private function pickRandomIds(Collection $ids, int $count): array
    {
        if ($ids->isEmpty()) {
            return [];
        }

        return $ids->random(min($count, $ids->count()))->all();
    }
}
