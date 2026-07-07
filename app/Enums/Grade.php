<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum Grade: string implements HasColor, HasLabel
{
    case APlus = 'A+';
    case A = 'A';
    case AMinus = 'A-';
    case B = 'B';
    case C = 'C';
    case D = 'D';
    case F = 'F';

    public function getLabel(): string
    {
        return $this->value;
    }

    public function getColor(): string
    {
        return match ($this) {
            self::APlus => 'success',
            self::A => 'success',
            self::AMinus => 'info',
            self::B => 'info',
            self::C => 'warning',
            self::D => 'danger',
            self::F => 'danger',
        };
    }

    public function gpa(): float
    {
        return match ($this) {
            self::APlus => 5.0,
            self::A => 4.0,
            self::AMinus => 3.5,
            self::B => 3.0,
            self::C => 2.0,
            self::D => 1.0,
            self::F => 0.0,
        };
    }

    public static function fromMarks(float $marks): self
    {
        return match (true) {
            $marks >= 80 => self::APlus,
            $marks >= 70 => self::A,
            $marks >= 60 => self::AMinus,
            $marks >= 50 => self::B,
            $marks >= 40 => self::C,
            $marks >= 33 => self::D,
            default => self::F,
        };
    }

    public static function fromGpa(float $gpa): self
    {
        return match (true) {
            $gpa >= 5.0 => self::APlus,
            $gpa >= 4.0 => self::A,
            $gpa >= 3.5 => self::AMinus,
            $gpa >= 3.0 => self::B,
            $gpa >= 2.0 => self::C,
            $gpa >= 1.0 => self::D,
            default => self::F,
        };
    }
}
