<?php

namespace App\Actions;

use App\Enums\AddressType;
use App\Enums\UserType;
use App\Models\TeacherProfile;
use App\Models\User;
use Illuminate\Auth\Events\Registered;

class CreateTeacherProfileAction
{
    public function handle(array $data): TeacherProfile
    {
        $user = User::where('email', $data['email'])->first();

        if ($user) {
            // Case 1: user has an existing teacher profile → delegate to UpdateTeacherProfileAction
            if ($user->teacherProfile) {
                return app(UpdateTeacherProfileAction::class)->handle($user->teacherProfile, $data);
            }

            // Case 2: user exists but has no teacher profile → update user + create profile
            $userUpdate = ['name' => $data['name']];

            if (filled($data['password'] ?? null)) {
                $userUpdate['password'] = $data['password'];
            }

            $user->update($userUpdate);

            if (! $user->hasRole('teacher')) {
                $user->assignRole('teacher');
            }

            $profile = $user->teacherProfile()->create($this->profileData($data));
            $this->saveAddresses($profile, $data);

            return $profile;
        }

        // Case 3: new email → create user + profile and send verification email
        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'user_type' => UserType::Teacher,
            'is_active' => true,
        ]);

        $user->assignRole('teacher');
        event(new Registered($user));

        $profile = $user->teacherProfile()->create($this->profileData($data));
        $this->saveAddresses($profile, $data);

        return $profile;
    }

    private function saveAddresses(TeacherProfile $profile, array $data): void
    {
        $isSame = (bool) ($data['same_address'] ?? false);

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
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function profileData(array $data): array
    {
        return [
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
        ];
    }
}
