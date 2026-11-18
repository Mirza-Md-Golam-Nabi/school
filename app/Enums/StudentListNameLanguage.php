<?php

namespace App\Enums;

use App\Models\StudentProfile;
use Filament\Support\Contracts\HasLabel;

/**
 * Student List PDF-এ নাম কোন ভাষায় দেখাবে — ইংরেজি, বাংলা, নাকি দুটো আলাদা কলামে।
 */
enum StudentListNameLanguage: string implements HasLabel
{
    case English = 'en';
    case Bangla = 'bn';
    case Both = 'both';

    public function getLabel(): string
    {
        return match ($this) {
            self::English => 'English',
            self::Bangla => 'বাংলা',
            self::Both => 'English + বাংলা',
        };
    }

    /**
     * PDF-এর নামের কলামগুলোর শিরোনাম।
     *
     * @return array<int, string>
     */
    public function headers(): array
    {
        return match ($this) {
            self::English => ['Name'],
            self::Bangla => ['নাম'],
            self::Both => ['Name', 'নাম'],
        };
    }

    /**
     * Student-এর নাম(গুলো), headers()-এর ক্রমে। বাংলা নাম না থাকলে ইংরেজি নামটাই দেখায়।
     *
     * @return array<int, string|null>
     */
    public function names(StudentProfile $student): array
    {
        $englishName = $student->user?->name;
        $banglaName = $student->name_bn ?: $englishName;

        return match ($this) {
            self::English => [$englishName],
            self::Bangla => [$banglaName],
            self::Both => [$englishName, $banglaName],
        };
    }
}
