<?php

namespace App\Enums;

use App\Models\StaffProfile;
use Filament\Support\Contracts\HasLabel;

/**
 * The details a school can choose to print on a staff member's ID card.
 */
enum StaffIdCardField: string implements HasLabel
{
    case IdNo = 'id_no';
    case Designation = 'designation';
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
            self::IdNo => 'Staff ID',
            self::Designation => 'Designation',
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
            self::BloodGroup => 'BLOOD',
            self::DateOfBirth => 'BIRTH DATE',
            self::JoiningDate => 'JOINED',
            default => mb_strtoupper($this->getLabel()),
        };
    }

    /**
     * The value printed on the card for this staff member, or null when they
     * have nothing recorded for the field.
     */
    public function valueFor(StaffProfile $staff): ?string
    {
        $value = match ($this) {
            self::IdNo => $staff->idCardNumber(),
            self::Designation => $staff->designation,
            self::BloodGroup => $staff->blood_group?->value,
            self::DateOfBirth => $staff->date_of_birth?->format('d M Y'),
            self::Gender => $staff->gender?->getLabel(),
            self::Religion => $staff->religion?->getLabel(),
            self::Nationality => $staff->nationality,
            self::Phone => $staff->user?->phone,
            self::Email => $staff->user?->email,
            self::JoiningDate => $staff->joining_date?->format('d M Y'),
            self::Address => ($staff->addresses->firstWhere('type', AddressType::Present)
                ?? $staff->addresses->first())?->address,
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
        return [self::Phone, self::JoiningDate, self::Address];
    }
}
