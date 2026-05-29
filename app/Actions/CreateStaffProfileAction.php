<?php

namespace App\Actions;

use App\Enums\AddressType;
use App\Enums\UserType;
use App\Models\StaffProfile;
use App\Models\User;
use Illuminate\Auth\Events\Registered;

class CreateStaffProfileAction
{
    public function handle(array $data): StaffProfile
    {
        $user = User::where('email', $data['email'])->first();

        if ($user) {
            // Case 1: user has an existing staff profile → delegate to UpdateStaffProfileAction
            if ($user->staffProfile) {
                return app(UpdateStaffProfileAction::class)->handle($user->staffProfile, $data);
            }

            // Case 2: user exists but has no staff profile → update user + create profile
            $userUpdate = ['name' => $data['name']];

            if (filled($data['password'] ?? null)) {
                $userUpdate['password'] = $data['password'];
            }

            $user->update($userUpdate);

            if (! $user->hasRole('staff')) {
                $user->assignRole('staff');
            }

            $profile = $user->staffProfile()->create($this->profileData($data));
            $this->saveAddresses($profile, $data);

            return $profile;
        }

        // Case 3: new email → create user + profile and send verification email
        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'user_type' => UserType::Staff,
            'is_active' => true,
        ]);

        $user->assignRole('staff');
        event(new Registered($user));

        $profile = $user->staffProfile()->create($this->profileData($data));
        $this->saveAddresses($profile, $data);

        return $profile;
    }

    private function saveAddresses(StaffProfile $profile, array $data): void
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
            'joining_date' => $data['joining_date'] ?? null,
            'status' => $data['status'] ?? null,
        ];
    }
}
