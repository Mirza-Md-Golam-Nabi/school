<?php

namespace App\Models;

use App\Enums\MarksheetBundleType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * A ready-made combined marksheet PDF of one exam (all / passed / failed
 * students), stored on the local disk so it downloads instantly.
 */
class MarksheetBundle extends Model
{
    protected $fillable = [
        'exam_id',
        'type',
        'subject_language',
        'file_path',
        'student_count',
        'is_generated',
        'generation_token',
        'file_generated_at',
    ];

    protected $casts = [
        'type' => MarksheetBundleType::class,
        'student_count' => 'integer',
        'is_generated' => 'boolean',
        'file_generated_at' => 'datetime',
    ];

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    /**
     * Built and ready to serve in the given language — either a stored file,
     * or knowingly empty (no student matched, so there is nothing to download).
     */
    public function isReadyFor(string $subjectLanguage): bool
    {
        if (! $this->is_generated || $this->subject_language !== $subjectLanguage) {
            return false;
        }

        return $this->student_count === 0 || $this->hasStoredFile();
    }

    public function hasStoredFile(): bool
    {
        return $this->file_path !== null && Storage::disk('local')->exists($this->file_path);
    }
}
