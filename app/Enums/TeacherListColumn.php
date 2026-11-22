<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Teacher List PDF-এ কোন কোন optional কলাম দেখানো যাবে।
 */
enum TeacherListColumn: string implements HasLabel
{
    case Designation = 'designation';
    case Department = 'department';
    case Qualification = 'qualification';
    case Phone = 'phone';
    case Email = 'email';
    case JoiningDate = 'joining_date';
    case Gender = 'gender';
    case DateOfBirth = 'date_of_birth';
    case BloodGroup = 'blood_group';
    case Religion = 'religion';
    case Nationality = 'nationality';
    case Status = 'status';
    case PresentAddress = 'present_address';
    case PermanentAddress = 'permanent_address';

    public function getLabel(): string
    {
        return match ($this) {
            self::Designation => 'Designation',
            self::Department => 'Department',
            self::Qualification => 'Qualification',
            self::Phone => 'Phone',
            self::Email => 'Email',
            self::JoiningDate => 'Joining Date',
            self::Gender => 'Gender',
            self::DateOfBirth => 'Date of Birth',
            self::BloodGroup => 'Blood Group',
            self::Religion => 'Religion',
            self::Nationality => 'Nationality',
            self::Status => 'Status',
            self::PresentAddress => 'Present Address',
            self::PermanentAddress => 'Permanent Address',
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
        return [self::Designation->value, self::Phone->value, self::JoiningDate->value];
    }
}
