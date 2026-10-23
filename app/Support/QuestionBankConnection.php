<?php

namespace App\Support;

use App\Models\SchoolSetting;
use Filament\Navigation\NavigationItem;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;

/**
 * This school's connection to the Question Bank, as the school's admin set
 * it up on the "Question Bank" settings page: the Question Bank's address,
 * the connection code and secret its School account shows, and whether the
 * Question Bank has accepted them. Kept in school_settings — the secret
 * encrypted, never shown again once saved.
 */
class QuestionBankConnection
{
    private const URL = 'question_bank_url';

    private const CODE = 'question_bank_code';

    private const SECRET = 'question_bank_secret';

    private const CONNECTED_AT = 'question_bank_connected_at';

    private const SCHOOL_NAME = 'question_bank_school_name';

    /**
     * How old a signed request from the Question Bank may be, in seconds,
     * before it is refused — a technical limit against replays.
     */
    public const SIGNATURE_TOLERANCE_SECONDS = 300;

    public static function url(): ?string
    {
        $url = SchoolSetting::get(self::URL);

        return filled($url) ? rtrim((string) $url, '/') : null;
    }

    public static function code(): ?string
    {
        return SchoolSetting::get(self::CODE) ?: null;
    }

    public static function secret(): ?string
    {
        $secret = SchoolSetting::get(self::SECRET);

        return filled($secret) ? Crypt::decryptString((string) $secret) : null;
    }

    public static function isConfigured(): bool
    {
        return self::url() !== null && self::code() !== null && self::secret() !== null;
    }

    public static function isConnected(): bool
    {
        return self::isConfigured() && self::connectedAt() !== null;
    }

    public static function connectedAt(): ?Carbon
    {
        $at = SchoolSetting::get(self::CONNECTED_AT);

        return filled($at) ? Carbon::parse($at) : null;
    }

    /**
     * The name the Question Bank knows this school by, from the last
     * successful connection.
     */
    public static function schoolName(): ?string
    {
        return SchoolSetting::get(self::SCHOOL_NAME) ?: null;
    }

    /**
     * Saves what the admin entered; a blank secret keeps the one saved
     * before. Any change means the Question Bank has to accept it again.
     */
    public static function save(string $url, string $code, ?string $secret): void
    {
        SchoolSetting::set(self::URL, rtrim($url, '/'));
        SchoolSetting::set(self::CODE, trim($code));

        if (filled($secret)) {
            SchoolSetting::set(self::SECRET, Crypt::encryptString(trim($secret)));
        }

        SchoolSetting::set(self::CONNECTED_AT, '');
    }

    public static function markConnected(string $schoolName): void
    {
        SchoolSetting::set(self::CONNECTED_AT, now()->toIso8601String());
        SchoolSetting::set(self::SCHOOL_NAME, $schoolName);
    }

    /**
     * Where a panel's "Question Bank" link goes: that panel of the Question
     * Bank (`/school` for the school's own account, `/teacher`, `/student`).
     */
    public static function panelUrl(string $panel): ?string
    {
        return self::url() !== null ? self::url().'/'.$panel : null;
    }

    /**
     * The "Question Bank" link in a panel's menu, opening that panel of the
     * Question Bank in a new tab — shown only once the school is connected.
     * Each person signs in there with their own Google account; the admin
     * panel's link is for the school's own account.
     */
    public static function navigationItem(string $panel): NavigationItem
    {
        return NavigationItem::make('question-bank')
            ->label(fn (): string => __('Question Bank'))
            ->icon(Heroicon::OutlinedAcademicCap)
            ->url(fn (): ?string => self::panelUrl($panel), shouldOpenInNewTab: true)
            ->visible(fn (): bool => self::isConnected())
            ->sort(90);
    }

    /**
     * How long a "School questions" link stays open, in seconds — a new one
     * is made each time the page opens.
     */
    public const QUESTIONS_LINK_SECONDS = 7200;

    /**
     * The Question Bank page with this school's own questions that its
     * students may now read, in `$locale` — signed with the secret for a
     * short time, since the students have no Question Bank login for it.
     */
    public static function schoolQuestionsUrl(string $locale): ?string
    {
        if (! self::isConnected()) {
            return null;
        }

        $code = (string) self::code();
        $expires = (string) now()->addSeconds(self::QUESTIONS_LINK_SECONDS)->timestamp;

        return self::url().'/school-questions/'.rawurlencode($code).'?'.http_build_query([
            'expires' => $expires,
            'signature' => HmacSignature::sign((string) self::secret(), $expires, "link:{$code}"),
            'lang' => $locale,
        ]);
    }

    /**
     * The headers of a request to the Question Bank, signed with the secret.
     *
     * @return array<string, string>
     */
    public static function signedHeaders(string $body): array
    {
        $timestamp = (string) now()->timestamp;

        return [
            'X-School-Code' => (string) self::code(),
            'X-Timestamp' => $timestamp,
            'X-Signature' => HmacSignature::sign((string) self::secret(), $timestamp, $body),
        ];
    }
}
