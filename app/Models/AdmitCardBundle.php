<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * A ready-made combined admit card PDF of one exam (i.e. one class), stored
 * on the local disk so it downloads instantly.
 */
class AdmitCardBundle extends Model
{
    protected $fillable = [
        'exam_id',
        'file_path',
        'card_count',
        'is_generated',
        'generation_token',
        'file_generated_at',
    ];

    protected $casts = [
        'card_count' => 'integer',
        'is_generated' => 'boolean',
        'file_generated_at' => 'datetime',
    ];

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    /**
     * Built, stored, and still covering exactly the exam's current admit
     * cards — a card deleted or added since makes the stored file stale.
     */
    public function isReadyFor(int $currentCardCount): bool
    {
        return $this->is_generated
            && $this->card_count > 0
            && $this->card_count === $currentCardCount
            && $this->hasStoredFile();
    }

    public function hasStoredFile(): bool
    {
        return $this->file_path !== null && Storage::disk('local')->exists($this->file_path);
    }

    public function deleteStoredFile(): void
    {
        if ($this->hasStoredFile()) {
            Storage::disk('local')->delete($this->file_path);
        }
    }
}
