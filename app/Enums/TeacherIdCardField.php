<?php

namespace App\Enums;

use App\Models\TeacherProfile;
use Filament\Support\Contracts\HasLabel;

/**
 * The details a school can choose to print on a teacher's ID card.
 */
enum TeacherIdCardField: string implements HasLabel
{
    case IdNo = 'id_no';
    case Designation = 'designation';
    case Department = 'department';
    case Qualification = 'qualification';
    case BloodGroup = 'blood_group';
    case DateOfBirth = 'date_of_birth';
    case Gender = 'gender';
    case Religion = 'religion';
    case Nationality = 'nationality';
    case Phone = 'phone';
    case Email = 'email';
    case JoiningDate = 'joining_date';
    case Address = 'address';

    public function getLabel(): string
    {
        return match ($this) {
            self::IdNo => 'Teacher ID',
            self::Designation => 'Designation',
            self::Department => 'Department',
            self::Qualification => 'Qualification',
            self::BloodGroup => 'Blood Group',
            self::DateOfBirth => 'Date of Birth',
            self::Gender => 'Gender',
            self::Religion => 'Religion',
            self::Nationality => 'Nationality',
            self::Phone => 'Phone',
            self::Email => 'Email',
            self::JoiningDate => 'Joining Date',
            self::Address => 'Address',
        };
    }

    /**
     * The abbreviated label printed on the front of the card, where the
     * label sits in a narrow column beside the value.
     */
    public function shortLabel(): string
    {
        return match ($this) {
            self::IdNo => 'ID NO',
            self::Designation => 'POST',
            self::Department => 'DEPT.',
            self::BloodGroup => 'BLOOD',
            self::DateOfBirth => 'BIRTH DATE',
            self::Qualification => 'DEGREE',
            self::JoiningDate => 'JOINED',
            default => mb_strtoupper($this->getLabel()),
        };
    }

    /**
     * The value printed on the card for this teacher, or null when the
     * teacher has nothing recorded for the field.
     */
    public function valueFor(TeacherProfile $teacher): ?string
    {
        $value = match ($this) {
            self::IdNo => $teacher->idCardNumber(),
            self::Designation => $teacher->designation,
            self::Department => $teacher->department,
            self::Qualification => $teacher->qualification,
            self::BloodGroup => $teacher->blood_group?->value,
            self::DateOfBirth => $teacher->date_of_birth?->format('d M Y'),
            self::Gender => $teacher->gender?->getLabel(),
            self::Religion => $teacher->religion?->getLabel(),
            self::Nationality => $teacher->nationality,
            self::Phone => $teacher->user?->phone,
            self::Email => $teacher->user?->email,
            self::JoiningDate => $teacher->joining_date?->format('d M Y'),
            self::Address => ($teacher->addresses->firstWhere('type', AddressType::Present)
                ?? $teacher->addresses->first())?->address,
        };

        return filled($value) ? (string) $value : null;
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
     * @return array<int, self>
     */
    public static function defaultFront(): array
    {
        return [self::IdNo, self::Designation, self::BloodGroup];
    }

    /**
     * @return array<int, self>
     */
    public static function defaultBack(): array
    {
        return [self::Phone, self::Email, self::JoiningDate, self::Address];
    }
}
