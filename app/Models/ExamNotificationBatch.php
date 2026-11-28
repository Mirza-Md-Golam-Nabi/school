<?php

namespace App\Models;

use App\Enums\ExamNotificationKind;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One send of an exam's subject marks or complete result — who sent it,
 * through which channels and to how many students. Each SMS itself is in
 * sms_logs and each push in push_notification_deliveries.
 */
class ExamNotificationBatch extends Model
{
    protected $fillable = [
        'exam_id',
        'kind',
        'subject_ids',
        'channels',
        'sent_by',
        'recipients_count',
        'push_count',
        'sms_count',
        'sms_status',
        'sms_cost',
    ];

    protected $casts = [
        'kind' => ExamNotificationKind::class,
        'subject_ids' => 'array',
        'channels' => 'array',
        'recipients_count' => 'integer',
        'push_count' => 'integer',
        'sms_count' => 'integer',
        'sms_cost' => 'float',
    ];

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by');
    }

    public function includesSubject(int $subjectId): bool
    {
        return in_array($subjectId, array_map('intval', $this->subject_ids ?? []), true);
    }
}
