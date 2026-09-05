<?php

namespace App\Actions;

use App\Enums\AddressType;
use App\Enums\OptionalSubjectRole;
use App\Models\StudentProfile;

class UpdateStudentProfileAction
{
    public function handle(StudentProfile $profile, array $data): StudentProfile
    {
        // Email is system-generated and read-only — never accept updates to it here.
        $userData = [
            'name' => $data['name'],
        ];

        if (filled($data['password'] ?? null)) {
            $userData['password'] = $data['password'];
        }

        $profile->user->update($userData);

        $profile->update([
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
        ]);

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

        $this->saveOptionalSubjects($profile, $data);

        return $profile;
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
}
