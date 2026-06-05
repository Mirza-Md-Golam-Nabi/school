<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum NoticeTargetType: string implements HasColor, HasIcon, HasLabel
{
    case All = 'all';
    case Students = 'students';
    case Teachers = 'teachers';
    case ByClass = 'class';
    case IndividualStudent = 'individual_student';
    case IndividualTeacher = 'individual_teacher';

    public function getLabel(): string
    {
        return match ($this) {
            self::All => 'Everyone',
            self::Students => 'All Students',
            self::Teachers => 'All Teachers',
            self::ByClass => 'Specific Class(es)',
            self::IndividualStudent => 'Individual Student(s)',
            self::IndividualTeacher => 'Individual Teacher(s)',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::All => 'primary',
            self::Students => 'success',
            self::Teachers => 'warning',
            self::ByClass => 'info',
            self::IndividualStudent => 'success',
            self::IndividualTeacher => 'warning',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::All => 'heroicon-o-globe-alt',
            self::Students => 'heroicon-o-academic-cap',
            self::Teachers => 'heroicon-o-briefcase',
            self::ByClass => 'heroicon-o-building-library',
            self::IndividualStudent => 'heroicon-o-user',
            self::IndividualTeacher => 'heroicon-o-user-circle',
        };
    }

    public function requiresTargets(): bool
    {
        return in_array($this, [self::ByClass, self::IndividualStudent, self::IndividualTeacher]);
    }

    public function morphsToUser(): bool
    {
        return in_array($this, [self::IndividualStudent, self::IndividualTeacher]);
    }
}
