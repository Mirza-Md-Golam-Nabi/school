<?php

namespace App\Actions;

use App\Models\TeacherProfile;

class UpdateOwnTeacherProfileAction
{
    /**
     * Teacher নিজের প্রোফাইল থেকে যে তথ্যগুলো বদলাতে পারে। Status ও salary account
     * admin ঠিক করে, তাই এখানে নেই।
     *
     * @var array<int, string>
     */
    public const EDITABLE_FIELDS = [
        'gender',
        'date_of_birth',
        'blood_group',
        'religion',
        'nationality',
        'designation',
        'department',
        'qualification',
        'joining_date',
    ];

    public function __construct(private UpdateTeacherProfileAction $updateTeacherProfile) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(TeacherProfile $profile, array $data): TeacherProfile
    {
        $changes = [];

        foreach (self::EDITABLE_FIELDS as $field) {
            $changes[$field] = $data[$field] ?? null;
        }

        $changes['nationality'] = filled($changes['nationality']) ? $changes['nationality'] : 'Bangladeshi';

        $profile->update($changes);

        $this->updateTeacherProfile->syncAddresses($profile, $data);

        return $profile;
    }
}
