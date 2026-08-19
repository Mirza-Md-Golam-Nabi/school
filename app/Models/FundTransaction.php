<?php

namespace App\Models;

use App\Actions\SyncFundTransactionAccountTransactionAction;
use App\Enums\TransactionType;
use App\Traits\LogsRelationLabels;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class FundTransaction extends Model
{
    use LogsActivity;
    use LogsRelationLabels;

    protected $fillable = [
        'transaction_category_id',
        'school_account_id',
        'type',
        'title',
        'amount',
        'transaction_date',
        'party_name',
        'description',
        'attachment_path',
        'created_by',
    ];

    protected $casts = [
        'type' => TransactionType::class,
        'amount' => 'decimal:2',
        'transaction_date' => 'date',
    ];

    protected static function booted(): void
    {
        static::created(function (FundTransaction $fundTransaction) {
            app(SyncFundTransactionAccountTransactionAction::class)->sync($fundTransaction);
        });

        static::updated(function (FundTransaction $fundTransaction) {
            if ($fundTransaction->wasChanged(['amount', 'type', 'school_account_id', 'transaction_date'])) {
                app(SyncFundTransactionAccountTransactionAction::class)->sync($fundTransaction);
            }
        });

        static::deleted(function (FundTransaction $fundTransaction) {
            app(SyncFundTransactionAccountTransactionAction::class)->reverse($fundTransaction);
        });
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(TransactionCategory::class, 'transaction_category_id');
    }

    public function schoolAccount(): BelongsTo
    {
        return $this->belongsTo(SchoolAccount::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('fund_transaction')
            ->setDescriptionForEvent(fn (string $eventName): string => $this->activityLogDescription($eventName));
    }

    protected function activityLogRelationLabels(): array
    {
        return [
            'transaction_category_id' => fn (int|string|null $id): ?string => $id === null ? null : TransactionCategory::find($id)?->name,
            'school_account_id' => fn (int|string|null $id): ?string => $id === null ? null : SchoolAccount::find($id)?->name,
            'created_by' => fn (int|string|null $id): ?string => $id === null ? null : User::find($id)?->name,
            'type' => fn (?string $value): ?string => $value === null ? null : TransactionType::tryFrom($value)?->getLabel(),
        ];
    }

    private function activityLogDescription(string $eventName): string
    {
        $categoryLabel = $this->category?->name ?? "Category #{$this->transaction_category_id}";
        $typeLabel = $this->type?->getLabel() ?? (string) $this->type;
        $amountLabel = number_format((float) $this->amount, 2);

        if ($eventName === 'created') {
            return "Recorded {$typeLabel} \"{$this->title}\" of ৳{$amountLabel} under \"{$categoryLabel}\".";
        }

        return ucfirst($eventName)." {$typeLabel} \"{$this->title}\" (৳{$amountLabel}).";
    }
}
