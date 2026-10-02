<?php

namespace App\Enums;

use App\Models\StudentProfile;
use Filament\Support\Contracts\HasLabel;

enum StudentIdCardField: string implements HasLabel
{
    case IdNo = 'id_no';
    case ClassName = 'class';
    case RollNo = 'roll_no';
    case Section = 'section';
    case Group = 'group';
    case Session = 'session';
    case BloodGroup = 'blood_group';
    case DateOfBirth = 'date_of_birth';
    case Gender = 'gender';
    case Religion = 'religion';
    case Nationality = 'nationality';
    case BirthCertificateNo = 'birth_certificate_no';
    case AdmissionDate = 'admission_date';
    case FatherName = 'father_name';
    case MotherName = 'mother_name';
    case GuardianName = 'guardian_name';
    case EmergencyContact = 'emergency_contact';
    case Address = 'address';

    public function getLabel(): string
    {
        return match ($this) {
            self::IdNo => 'ID No',
            self::ClassName => 'Class',
            self::RollNo => 'Roll No',
            self::Section => 'Section',
            self::Group => 'Group',
            self::Session => 'Session',
            self::BloodGroup => 'Blood Group',
            self::DateOfBirth => 'Date of Birth',
            self::Gender => 'Gender',
            self::Religion => 'Religion',
            self::Nationality => 'Nationality',
            self::BirthCertificateNo => 'Birth Certificate No',
            self::AdmissionDate => 'Admission Date',
            self::FatherName => "Father's Name",
            self::MotherName => "Mother's Name",
            self::GuardianName => "Guardian's Name",
            self::EmergencyContact => 'Emergency Contact',
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
            self::BloodGroup => 'BLOOD',
            self::DateOfBirth => 'BIRTH DATE',
            self::BirthCertificateNo => 'BIRTH REG.',
            self::AdmissionDate => 'ADMITTED',
            self::FatherName => 'FATHER',
            self::MotherName => 'MOTHER',
            self::GuardianName => 'GUARDIAN',
            self::EmergencyContact => 'EMERGENCY',
            default => mb_strtoupper($this->getLabel()),
        };
    }

    /**
     * Whether this value changes when the student is promoted — a card
     * printing it has to be reissued every year.
     */
    public function changesYearly(): bool
    {
        return in_array($this, [self::ClassName, self::RollNo, self::Section, self::Group, self::Session], true);
    }

    /**
     * The value printed on the card for this student, or null when the
     * student has nothing recorded for the field.
     */
    public function valueFor(StudentProfile $student): ?string
    {
        $value = match ($this) {
            self::IdNo => $student->idCardNumber(),
            self::ClassName => $student->class?->display_name,
            self::RollNo => sprintf('%02d', $student->roll_no),
            self::Section => $student->section?->name,
            self::Group => $student->group?->name,
            self::Session => (string) $student->session_year,
            self::BloodGroup => $student->blood_group?->value,
            self::DateOfBirth => $student->date_of_birth?->format('d M Y'),
            self::Gender => $student->gender?->getLabel(),
            self::Religion => $student->religion?->getLabel(),
            self::Nationality => $student->nationality,
            self::BirthCertificateNo => $student->birth_certificate_no,
            self::AdmissionDate => $student->admission_date?->format('d M Y'),
            self::FatherName => $student->father_name,
            self::MotherName => $student->mother_name,
            self::GuardianName => $student->guardian_name,
            self::EmergencyContact => $student->guardian_phone ?: $student->user?->phone,
            self::Address => ($student->addresses->firstWhere('type', AddressType::Present)
                ?? $student->addresses->first())?->address,
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
     * @return array<string, string>
     */
    public static function descriptions(): array
    {
        return collect(self::cases())
            ->filter(fn (self $case) => $case->changesYearly())
            ->mapWithKeys(fn (self $case) => [$case->value => 'প্রতি বছর বদলায়'])
            ->toArray();
    }

    /**
     * Front of a card meant to last the student's whole time at the school.
     *
     * @return array<int, self>
     */
    public static function defaultFront(): array
    {
        return [self::IdNo, self::BloodGroup, self::DateOfBirth];
    }

    /**
     * Back of a card meant to last the student's whole time at the school.
     *
     * @return array<int, self>
     */
    public static function defaultBack(): array
    {
        return [self::FatherName, self::MotherName, self::EmergencyContact, self::Address];
    }
}
