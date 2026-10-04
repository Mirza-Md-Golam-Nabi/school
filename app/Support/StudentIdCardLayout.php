<?php

namespace App\Support;

use App\Enums\StudentIdCardField;
use App\Enums\StudentIdCardTemplate;
use App\Enums\StudentIdCardValidity;
use App\Models\SchoolSetting;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Which details the school has chosen to print on each side of the student
 * ID card, persisted in school settings.
 */
class StudentIdCardLayout
{
    /**
     * The most detail rows each side has room for before the text has to shrink.
     */
    public const MAX_FIELDS_PER_SIDE = 6;

    private const FRONT_FIELDS_KEY = 'student_id_card_front_fields';

    private const BACK_FIELDS_KEY = 'student_id_card_back_fields';

    private const VALIDITY_KEY = 'student_id_card_validity';

    private const ISSUE_DATE_KEY = 'student_id_card_issue_date';

    private const TEMPLATE_KEY = 'student_id_card_template';

    /**
     * @return array<int, StudentIdCardField>
     */
    public static function frontFields(): array
    {
        return self::storedFields(self::FRONT_FIELDS_KEY) ?? StudentIdCardField::defaultFront();
    }

    /**
     * @return array<int, StudentIdCardField>
     */
    public static function backFields(): array
    {
        return self::storedFields(self::BACK_FIELDS_KEY) ?? StudentIdCardField::defaultBack();
    }

    public static function validity(): StudentIdCardValidity
    {
        return StudentIdCardValidity::tryFrom((string) SchoolSetting::get(self::VALIDITY_KEY))
            ?? StudentIdCardValidity::IssueDate;
    }

    /**
     * The issue date printed on the card — today until the school has set one.
     */
    public static function issueDate(): CarbonInterface
    {
        $stored = SchoolSetting::get(self::ISSUE_DATE_KEY);

        return filled($stored) ? Carbon::parse($stored) : today();
    }

    /**
     * The card design the school picked — the original design until it picks one.
     */
    public static function template(): StudentIdCardTemplate
    {
        return StudentIdCardTemplate::tryFrom((string) SchoolSetting::get(self::TEMPLATE_KEY))
            ?? StudentIdCardTemplate::Royal;
    }

    public static function saveTemplate(StudentIdCardTemplate $template): void
    {
        SchoolSetting::set(self::TEMPLATE_KEY, $template->value);
    }

    /**
     * The fields are printed top to bottom in the order given.
     *
     * @param  array<int, string>  $frontFields
     * @param  array<int, string>  $backFields
     */
    public static function save(array $frontFields, array $backFields, StudentIdCardValidity $validity, ?CarbonInterface $issueDate = null): void
    {
        SchoolSetting::set(self::FRONT_FIELDS_KEY, json_encode(array_values($frontFields)));
        SchoolSetting::set(self::BACK_FIELDS_KEY, json_encode(array_values($backFields)));
        SchoolSetting::set(self::VALIDITY_KEY, $validity->value);

        if ($issueDate !== null) {
            SchoolSetting::set(self::ISSUE_DATE_KEY, $issueDate->toDateString());
        }
    }

    /**
     * The saved selection in the order the school arranged it, or null when the
     * school has never saved one — an empty saved selection is respected as-is.
     *
     * @return array<int, StudentIdCardField>|null
     */
    private static function storedFields(string $key): ?array
    {
        $stored = json_decode((string) SchoolSetting::get($key, ''), true);

        if (! is_array($stored)) {
            return null;
        }

        return collect($stored)
            ->map(fn (mixed $value): ?StudentIdCardField => is_string($value) ? StudentIdCardField::tryFrom($value) : null)
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}
