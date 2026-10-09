<?php

namespace App\Actions;

use App\Enums\OptionalSubjectRole;
use App\Models\ClassGroupSubject;
use App\Models\StudentOptionalSubject;
use App\Models\StudentProfile;
use Illuminate\Support\Collection;

class ResolveDefaultPromotionOptionalSubjects
{
    /**
     * Optional subject IDs per class/group — the same for every student of a
     * bulk promotion, so they are looked up once per instance.
     *
     * @var array<string, Collection<int, int>>
     */
    private array $availableSubjectIds = [];

    /**
     * সাধারণত class 9 থেকে class 10-এ promotion হলে student একই group এবং
     * একই main/extra optional subject-ই রাখে — তাই promotion form খোলার সময়
     * আগের ক্লাসের choice ডিফল্ট হিসেবে বসিয়ে দেওয়া হয়, শুধু group বদলে গেলে বা
     * টার্গেট ক্লাসের optional pool-এ subject না থাকলে খালি রেখে admin-কে নতুন
     * করে বেছে নিতে বলা হয়।
     *
     * @return array{main_optional_subject_id: ?int, extra_optional_subject_id: ?int}
     */
    public function execute(StudentProfile $student, ?int $targetClassId, ?int $targetGroupId, ?Collection $currentSelections = null): array
    {
        $empty = ['main_optional_subject_id' => null, 'extra_optional_subject_id' => null];

        // Group বদলে গেলে আগের optional subject choice আর প্রযোজ্য নয়।
        if (! $targetClassId || ! $targetGroupId || $targetGroupId !== $student->current_group_id) {
            return $empty;
        }

        $currentSelections ??= StudentOptionalSubject::where('student_id', $student->id)
            ->where('class_id', $student->current_class_id)
            ->get();

        if ($currentSelections->isEmpty()) {
            return $empty;
        }

        // main_optional শুধু group-এর নিজস্ব pool থেকে বৈধ, extra_optional-এর
        // জন্য group-এর + "All Groups" pool দুটোই বৈধ — main/extra optional
        // subject select field দুটো যেভাবে option দেখায়, সেভাবেই যাচাই হচ্ছে।
        $availableMainSubjectIds = $this->availableSubjectIds($targetClassId, $targetGroupId, includeAllGroups: false);
        $availableExtraSubjectIds = $this->availableSubjectIds($targetClassId, $targetGroupId, includeAllGroups: true);

        $main = $currentSelections->firstWhere('role', OptionalSubjectRole::MainOptional);
        $extra = $currentSelections->firstWhere('role', OptionalSubjectRole::ExtraOptional);

        return [
            'main_optional_subject_id' => ($main && $availableMainSubjectIds->contains($main->subject_id))
                ? $main->subject_id
                : null,
            'extra_optional_subject_id' => ($extra && $availableExtraSubjectIds->contains($extra->subject_id))
                ? $extra->subject_id
                : null,
        ];
    }

    /**
     * @return Collection<int, int>
     */
    private function availableSubjectIds(int $classId, int $groupId, bool $includeAllGroups): Collection
    {
        return $this->availableSubjectIds["{$classId}-{$groupId}-".(int) $includeAllGroups]
            ??= ClassGroupSubject::optionalSubjectOptions($classId, $groupId, $includeAllGroups)->keys();
    }
}
