<?php

namespace App\Support;

use App\Enums\StaffIdCardField;
use App\Enums\StudentIdCardTemplate;
use App\Models\SchoolSetting;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Which details the school prints on each side of the staff ID card, the
 * issue date, the design and whether the QR is printed — chosen
 * independently of the student and teacher cards.
 */
class StaffIdCardLayout
{
    /**
     * The most detail rows each side has room for before the text has to shrink.
     */
    public const MAX_FIELDS_PER_SIDE = 6;

    private const FRONT_FIELDS_KEY = 'staff_id_card_front_fields';

    private const BACK_FIELDS_KEY = 'staff_id_card_back_fields';

    private const ISSUE_DATE_KEY = 'staff_id_card_issue_date';

    private const TEMPLATE_KEY = 'staff_id_card_template';

    private const LOGO_COLOUR_KEY = 'staff_id_card_logo_colour';

    private const SHOW_QR_KEY = 'staff_id_card_show_qr';

    /**
     * @return array<int, StaffIdCardField>
     */
    public static function frontFields(): array
    {
        return self::storedFields(self::FRONT_FIELDS_KEY) ?? StaffIdCardField::defaultFront();
    }

    /**
     * @return array<int, StaffIdCardField>
     */
    public static function backFields(): array
    {
        return self::storedFields(self::BACK_FIELDS_KEY) ?? StaffIdCardField::defaultBack();
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
     * The design the school picked for staff cards — Emerald Prism until it
     * picks one, so it differs from the student and teacher defaults.
     */
    public static function template(): StudentIdCardTemplate
    {
        return StudentIdCardTemplate::tryFrom((string) SchoolSetting::get(self::TEMPLATE_KEY))
            ?? StudentIdCardTemplate::Emerald;
    }

    public static function saveTemplate(StudentIdCardTemplate $template): void
    {
        SchoolSetting::set(self::TEMPLATE_KEY, $template->value);
    }

    /**
     * Whether the school has submitted a design yet — cards are made only after it has.
     */
    public static function hasSubmittedDesign(): bool
    {
        return StudentIdCardTemplate::tryFrom((string) SchoolSetting::get(self::TEMPLATE_KEY)) !== null;
    }

    /**
     * The single #rrggbb colour the school logo is printed in, or null to
     * print the logo exactly as uploaded.
     */
    public static function logoColour(): ?string
    {
        $stored = SchoolSetting::get(self::LOGO_COLOUR_KEY);

        return IdCardLogo::isValidColour($stored) ? strtolower($stored) : null;
    }

    /**
     * Save the finalised design and logo colour — cards are printed in them from now on.
     */
    public static function submitDesign(StudentIdCardTemplate $template, ?string $logoColour): void
    {
        self::saveTemplate($template);

        SchoolSetting::set(self::LOGO_COLOUR_KEY, IdCardLogo::isValidColour($logoColour) ? strtolower($logoColour) : '');
    }

    /**
     * Whether the verification QR code is printed on the back of the card —
     * on until the school turns it off.
     */
    public static function showsQrCode(): bool
    {
        return (bool) SchoolSetting::get(self::SHOW_QR_KEY, '1');
    }

    public static function saveShowsQrCode(bool $show): void
    {
        SchoolSetting::set(self::SHOW_QR_KEY, $show ? '1' : '0');
    }

    /**
     * The fields are printed top to bottom in the order given.
     *
     * @param  array<int, string>  $frontFields
     * @param  array<int, string>  $backFields
     */
    public static function save(array $frontFields, array $backFields, CarbonInterface $issueDate): void
    {
        SchoolSetting::set(self::FRONT_FIELDS_KEY, json_encode(array_values($frontFields)));
        SchoolSetting::set(self::BACK_FIELDS_KEY, json_encode(array_values($backFields)));
        SchoolSetting::set(self::ISSUE_DATE_KEY, $issueDate->toDateString());
    }

    /**
     * The saved selection in the order the school arranged it, or null when the
     * school has never saved one — an empty saved selection is respected as-is.
     *
     * @return array<int, StaffIdCardField>|null
     */
    private static function storedFields(string $key): ?array
    {
        $stored = json_decode((string) SchoolSetting::get($key, ''), true);

        if (! is_array($stored)) {
            return null;
        }

        return collect($stored)
            ->map(fn (mixed $value): ?StaffIdCardField => is_string($value) ? StaffIdCardField::tryFrom($value) : null)
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}
