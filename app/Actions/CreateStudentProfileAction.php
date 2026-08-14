<?php

namespace App\Actions;

use App\Enums\AddressType;
use App\Enums\UserType;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateStudentProfileAction
{
    public function handle(array $data): StudentProfile
    {
        return DB::transaction(function () use ($data) {
            // Email depends on the student_profiles primary key, which only exists after
            // the profile row is inserted — so the user is created with a placeholder first.
            $user = User::create([
                'name' => $data['name'],
                'email' => sprintf('pending-%s@placeholder.internal', Str::uuid()),
                'password' => 'password',
                'user_type' => UserType::Student,
                'is_active' => true,
                'email_verified_at' => now(),
                'must_change_password' => true,
            ]);

            $user->assignRole('student');
            event(new Registered($user));

            $profile = $user->studentProfile()->create($this->profileData($data));

            $generatedEmail = sprintf('std%05d@school.com', $profile->id);
            $user->update(['email' => $generatedEmail]);

            $this->saveAddresses($profile, $data);

            $profile->setAttribute('generated_email', $generatedEmail);
            $profile->setAttribute('generated_password', 'password');

            return $profile;
        });
    }

    private function saveAddresses(StudentProfile $profile, array $data): void
    {
        $isSame = (bool) ($data['same_address'] ?? false);

        if (filled($data['present_address'] ?? null)) {
            $profile->addresses()->create([
                'type' => AddressType::Present,
                'address' => $data['present_address'],
                'is_same' => $isSame,
            ]);
        }

        if (! $isSame && filled($data['permanent_address'] ?? null)) {
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
            'roll_no' => $data['roll_no'] ?? null,
            'registration_no' => $data['registration_no'] ?? null,
            'birth_certificate_no' => $data['birth_certificate_no'] ?? null,
            'current_class_id' => $data['current_class_id'] ?? null,
            'current_section_id' => $data['current_section_id'] ?? null,
            'current_group_id' => $data['current_group_id'] ?? null,
            'session_year' => $data['session_year'] ?? null,
            'gender' => $data['gender'] ?? null,
            'date_of_birth' => $data['date_of_birth'] ?? null,
            'blood_group' => $data['blood_group'] ?? null,
            'religion' => $data['religion'] ?? null,
            'nationality' => $data['nationality'] ?? 'Bangladeshi',
            'father_name' => $data['father_name'] ?? null,
            'father_occupation' => $data['father_occupation'] ?? null,
            'father_photo' => $data['father_photo'] ?? null,
            'mother_name' => $data['mother_name'] ?? null,
            'mother_occupation' => $data['mother_occupation'] ?? null,
            'mother_photo' => $data['mother_photo'] ?? null,
            'guardian_name' => $data['guardian_name'] ?? null,
            'guardian_relation' => $data['guardian_relation'] ?? null,
            'guardian_occupation' => $data['guardian_occupation'] ?? null,
            'guardian_photo' => $data['guardian_photo'] ?? null,
            'admission_date' => $data['admission_date'] ?? null,
            'status' => $data['status'] ?? null,
        ];
    }
}
