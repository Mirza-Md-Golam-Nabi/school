<?php

namespace App\Models;

use App\Enums\SalaryComponentType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class SalaryComponent extends Model
{
    use LogsActivity;

    protected $fillable = [
        'name',
        'type',
        'is_active',
    ];

    protected $casts = [
        'type' => SalaryComponentType::class,
        'is_active' => 'boolean',
    ];

    public function structureComponents(): HasMany
    {
        return $this->hasMany(SalaryStructureComponent::class);
    }

    public function invoiceComponents(): HasMany
    {
        return $this->hasMany(SalaryInvoiceComponent::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('salary_component')
            ->setDescriptionForEvent(fn (string $eventName): string => $this->activityLogDescription($eventName));
    }

    private function activityLogDescription(string $eventName): string
    {
        if ($eventName === 'created') {
            $typeLabel = $this->type?->getLabel() ?? (string) $this->type;

            return "Created salary component \"{$this->name}\" ({$typeLabel}).";
        }

        return ucfirst($eventName)." salary component \"{$this->name}\".";
    }
}
