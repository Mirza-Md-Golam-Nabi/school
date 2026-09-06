<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum StudentListColumn: string implements HasLabel
{
    case Email = 'email';
    case Section = 'section';
    case Group = 'group';
    case RegistrationNo = 'registration_no';
    case BirthCertificateNo = 'birth_certificate_no';
    case Gender = 'gender';
    case DateOfBirth = 'date_of_birth';
    case BloodGroup = 'blood_group';
    case Religion = 'religion';
    case FatherName = 'father_name';
    case FatherOccupation = 'father_occupation';
    case MotherName = 'mother_name';
    case MotherOccupation = 'mother_occupation';
    case GuardianName = 'guardian_name';
    case GuardianRelation = 'guardian_relation';
    case GuardianOccupation = 'guardian_occupation';
    case GuardianPhone = 'guardian_phone';
    case MainSubject = 'main_subject';
    case AdditionalSubject = 'additional_subject';

    public function getLabel(): string
    {
        return match ($this) {
            self::Email => 'Email',
            self::Section => 'Section',
            self::Group => 'Group',
            self::RegistrationNo => 'Registration No',
            self::BirthCertificateNo => 'Birth Certificate No',
            self::Gender => 'Gender',
            self::DateOfBirth => 'Date of Birth',
            self::BloodGroup => 'Blood Group',
            self::Religion => 'Religion',
            self::FatherName => "Father's Name",
            self::FatherOccupation => "Father's Occupation",
            self::MotherName => "Mother's Name",
            self::MotherOccupation => "Mother's Occupation",
            self::GuardianName => "Guardian's Name",
            self::GuardianRelation => "Guardian's Relation",
            self::GuardianOccupation => "Guardian's Occupation",
            self::GuardianPhone => "Guardian's Phone",
            self::MainSubject => 'Main Subject',
            self::AdditionalSubject => 'Additional Subject',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case) => [$case->value => $case->getLabel()])
            ->toArray();
    }

    /**
     * @return array<int, string>
     */
    public static function defaults(): array
    {
        return [self::Email->value, self::Section->value, self::Group->value];
    }
}
