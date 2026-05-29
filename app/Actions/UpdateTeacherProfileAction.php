<?php

namespace App\Actions;

use App\Enums\AddressType;
use App\Models\TeacherProfile;

class UpdateTeacherProfileAction
{
    public function handle(TeacherProfile $profile, array $data): TeacherProfile
    {
        $userData = [
            'name' => $data['name'],
            'email' => $data['email'],
        ];

        if (filled($data['password'] ?? null)) {
            $userData['password'] = $data['password'];
        }

        $profile->user->update($userData);

        $profile->update([
            'gender' => $data['gender'] ?? null,
            'date_of_birth' => $data['date_of_birth'] ?? null,
            'blood_group' => $data['blood_group'] ?? null,
            'religion' => $data['religion'] ?? null,
            'nationality' => $data['nationality'] ?? 'Bangladeshi',
            'designation' => $data['designation'] ?? null,
            'department' => $data['department'] ?? null,
            'qualification' => $data['qualification'] ?? null,
            'joining_date' => $data['joining_date'] ?? null,
            'status' => $data['status'] ?? null,
        ]);

        $isSame = (bool) ($data['same_address'] ?? false);

        $profile->addresses()->delete();

        $profile->addresses()->create([
            'type' => AddressType::Present,
            'address' => $data['present_address'],
            'is_same' => $isSame,
        ]);

        if (! $isSame) {
            $profile->addresses()->create([
                'type' => AddressType::Permanent,
                'address' => $data['permanent_address'],
                'is_same' => false,
            ]);
        }

        return $profile;
    }
}
