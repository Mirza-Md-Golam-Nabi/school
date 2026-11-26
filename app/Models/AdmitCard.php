<?php

namespace App\Models;

use App\Enums\AdmitCardDesign;
use App\Traits\OrdersByStudentRoll;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class AdmitCard extends Model
{
    use OrdersByStudentRoll;

    public const SHOW_QR_SETTING = 'admit_card_show_qr';

    protected $fillable = [
        'student_id',
        'exam_id',
        'is_generated',
        'page_size',
        'design',
        'routine_language',
        'student_info_language',
        'generated_by',
        'generated_at',
        'file_path',
        'file_generated_at',
    ];

    protected $casts = [
        'is_generated' => 'boolean',
        'design' => AdmitCardDesign::class,
        'generated_at' => 'datetime',
        'file_generated_at' => 'datetime',
    ];

    /**
     * Every card gets its secret token once, when created. Regenerating the
     * card (new design or language) keeps the row and so the token, so a card
     * already printed still scans.
     */
    protected static function booted(): void
    {
        static::creating(function (AdmitCard $admitCard): void {
            $admitCard->verification_token ??= static::newVerificationToken();
        });
    }

    /**
     * A short, unguessable token — short so the QR stays sparse and scans
     * easily from a printed card.
     */
    public static function newVerificationToken(): string
    {
        return Str::random(16);
    }

    /**
     * The public page the card's QR code opens, or null for a card without a
     * token (e.g. an unsaved sample).
     */
    public function verificationUrl(): ?string
    {
        return filled($this->verification_token)
            ? route('admit-cards.verify', ['token' => $this->verification_token])
            : null;
    }

    /**
     * Whether the school prints the verification QR code on admit cards.
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
