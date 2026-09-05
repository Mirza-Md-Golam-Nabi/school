<?php

namespace App\Actions;

use App\Enums\AddressType;
use App\Enums\OptionalSubjectRole;
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
            $birthCertificateNo = $data['birth_certificate_no'] ?? null;

            $existingProfile = filled($birthCertificateNo)
                ? StudentProfile::where('birth_certificate_no', $birthCertificateNo)->first()
                : null;

            if ($existingProfile) {
                return $this->replaceExistingProfile($existingProfile, $data);
            }

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
            $this->saveOptionalSubjects($profile, $data);

            $profile->setAttribute('generated_email', $generatedEmail);
            $profile->setAttribute('generated_password', 'password');

            return $profile;
        });
    }

    /**
     * A birth certificate number matching an existing profile means this is the
     * same real student re-enrolling, not a new admission — update that profile
     * (and its user's name) in place instead of creating a duplicate account,
     * reusing the existing login email rather than generating a new one. The
     * password is reset to the default and must be changed again on next
     * login, the same as the "Reset Password" action on the edit page.
     */
    private function replaceExistingProfile(StudentProfile $existingProfile, array $data): StudentProfile
    {
        $existingProfile->user?->update([
            'name' => $data['name'],
            'password' => 'password',
            'must_change_password' => true,
            'pin' => null,
        ]);
        $existingProfile->update($this->profileData($data));

        $this->saveAddresses($existingProfile, $data);
        $this->saveOptionalSubjects($existingProfile, $data);

        $existingProfile->setAttribute('generated_email', $existingProfile->user?->email);
        $existingProfile->setAttribute('generated_password', 'password');

        return $existingProfile;
    }

    private function saveAddresses(StudentProfile $profile, array $data): void
    {
        $isSame = (bool) ($data['same_address'] ?? false);

        $profile->addresses()->delete();

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
     * Persist this student's main/extra optional subject choice for their
     * current class. Group ছাড়া বা group সিলেক্ট না থাকলে আগের কোনো
     * selection থাকলে মুছে ফেলা হয় (আর প্রযোজ্য নয়)।
     */
    private function saveOptionalSubjects(StudentProfile $profile, array $data): void
    {
        $classId = $data['current_class_id'] ?? null;
        $groupId = $data['current_group_id'] ?? null;

        if (! $classId || ! $groupId) {
            $profile->optionalSubjects()->delete();

            return;
        }

        // অন্য কোনো (আগের) ক্লাসের জন্য নয়, শুধু বর্তমান ক্লাসের selection রাখা হবে
        $profile->optionalSubjects()->where('class_id', '!=', $classId)->delete();

        $roleSubjects = [
            OptionalSubjectRole::MainOptional->value => $data['main_optional_subject_id'] ?? null,
            OptionalSubjectRole::ExtraOptional->value => $data['extra_optional_subject_id'] ?? null,
        ];

        foreach ($roleSubjects as $role => $subjectId) {
            if (! $subjectId) {
                $profile->optionalSubjects()
                    ->where('class_id', $classId)
                    ->where('role', $role)
                    ->delete();

                continue;
            }

            $profile->optionalSubjects()->updateOrCreate(
                ['class_id' => $classId, 'role' => $role],
                ['group_id' => $groupId, 'subject_id' => $subjectId]
            );
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
            'guardian_phone' => $data['guardian_phone'] ?? null,
            'guardian_photo' => $data['guardian_photo'] ?? null,
            'admission_date' => $data['admission_date'] ?? null,
            'status' => $data['status'] ?? null,
        ];
    }
}
