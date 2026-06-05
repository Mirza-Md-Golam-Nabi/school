<?php

namespace App\Actions\Notice;

use App\Enums\NoticeTargetType;
use App\Models\Classes;
use App\Models\Notice;
use App\Models\User;

class SyncNoticeTargetsAction
{
    /**
     * Sync the polymorphic notice_targets records for the given notice.
     *
     * ByClass            → each id maps to a Classes record.
     * IndividualStudent  → each id maps to a User record (student).
     * IndividualTeacher  → each id maps to a User record (teacher).
     * All/Students/Teachers → no explicit targets (resolved at delivery time).
     *
     * @param  array<int>  $targetIds
     */
    public function handle(Notice $notice, array $targetIds): void
    {
        $notice->targets()->delete();

        if (empty($targetIds)) {
            return;
        }

        $morphClass = match ($notice->target_type) {
            NoticeTargetType::ByClass => (new Classes)->getMorphClass(),
            NoticeTargetType::IndividualStudent,
            NoticeTargetType::IndividualTeacher => (new User)->getMorphClass(),
            default => null,
        };

        if ($morphClass === null) {
            return;
        }

        $records = array_map(
            fn (int $id) => [
                'notice_id' => $notice->id,
                'targetable_type' => $morphClass,
                'targetable_id' => $id,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            $targetIds,
        );

        $notice->targets()->insert($records);
    }
}
