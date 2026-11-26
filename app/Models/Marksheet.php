<?php

namespace App\Models;

use App\Traits\OrdersByStudentRoll;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Marksheet extends Model
{
    use OrdersByStudentRoll;

    public const SHOW_QR_SETTING = 'marksheet_show_qr';

    /**
     * Letters and digits that can't be mistaken for one another when read off
     * paper and typed in by hand: no 0/O, 1/I/L.
     */
    public const VERIFICATION_CODE_ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    public const VERIFICATION_CODE_LENGTH = 8;

    protected $fillable = [
        'student_id',
        'exam_id',
        'is_generated',
        'generated_by',
        'generated_at',
        'file_path',
        'file_generated_at',
    ];

    protected $casts = [
        'is_generated' => 'boolean',
        'generated_at' => 'datetime',
        'file_generated_at' => 'datetime',
    ];

    /**
     * Every marksheet gets its verification code once, when created.
     * Regenerating the marksheet keeps the row and so the code, so a
     * marksheet already printed still verifies.
     */
    protected static function booted(): void
    {
        static::creating(function (Marksheet $marksheet): void {
            $marksheet->verification_code ??= static::newVerificationCode();
        });
    }

    /**
     * A new, unused code — short and unambiguous so it can be typed in from
     * the printed marksheet (31^8 ≈ 850 billion codes, behind a throttle).
     */
    public static function newVerificationCode(): string
    {
        do {
            $code = collect(range(1, self::VERIFICATION_CODE_LENGTH))
                ->map(fn (): string => self::VERIFICATION_CODE_ALPHABET[random_int(0, strlen(self::VERIFICATION_CODE_ALPHABET) - 1)])
                ->implode('');
        } while (
            static::query()->where('verification_code', $code)->exists()
            || FeePayment::query()->where('verification_code', $code)->exists()
        );

        return $code;
    }

    /**
     * A code as someone typed it ("k7m4-p9qx ", "K7M4 P9QX") in its stored form.
     */
    public static function normalizeVerificationCode(string $code): string
    {
        return Str::upper((string) preg_replace('/[^A-Za-z0-9]/', '', $code));
    }

    public static function findByVerificationCode(string $code): ?self
    {
        $code = static::normalizeVerificationCode($code);

        if (strlen($code) !== self::VERIFICATION_CODE_LENGTH) {
            return null;
        }

        return static::query()->where('verification_code', $code)->first();
    }

    /**
     * The code split in two halves for print ("K7M4-P9QX"), easier to read
     * aloud and type.
     */
    public function formattedVerificationCode(): ?string
    {
        return filled($this->verification_code)
            ? implode('-', str_split($this->verification_code, (int) ceil(strlen($this->verification_code) / 2)))
            : null;
    }

    /**
     * The public page the marksheet's QR code opens, or null for a marksheet
     * without a code (e.g. an unsaved sample).
     */
    public function verificationUrl(): ?string
    {
        return filled($this->verification_code)
            ? route('marksheets.verify', ['code' => $this->verification_code])
            : null;
    }

    /**
     * Whether the school prints the verification QR code on marksheets.
     */
    public static function showsQrCode(): bool
    {
        return (bool) SchoolSetting::get(self::SHOW_QR_SETTING, '1');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(StudentProfile::class, 'student_id');
    }

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    public function generatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }
}
