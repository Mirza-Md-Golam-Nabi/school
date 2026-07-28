<?php

namespace App\Filament\Resources\Concerns;

use App\Models\StaffProfile;
use App\Models\TeacherProfile;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;

class HasProfileableFields
{
    /**
     * Cascading Teacher/Staff selector reused wherever a salary record needs to
     * point at the polymorphic TeacherProfile/StaffProfile pair. Only active
     * profiles are offered. Callers that need extra side effects on change
     * (resetting dependent fields, prefilling a default account, etc.) can
     * re-chain ->afterStateUpdated() on the returned fields — the base
     * behaviour here (clearing profileable_id when the type changes) is only
     * a sensible default, not a contract callers need to preserve.
     *
     * @return array{0: Select, 1: Select}
     */
    public static function fields(): array
    {
        $typeField = Select::make('profileable_type')
            ->label('Type')
            ->options([
                TeacherProfile::class => 'Teacher',
                StaffProfile::class => 'Staff',
            ])
            ->native(false)
            ->live()
            ->required()
            ->afterStateUpdated(fn (Set $set) => $set('profileable_id', null))
            ->extraAttributes(['class' => ResponsiveText::CLASSES]);

        $idField = Select::make('profileable_id')
            ->label('Teacher/Staff')
            ->options(function (Get $get) {
                $type = $get('profileable_type');

                if (! $type || ! class_exists($type)) {
                    return [];
                }

                return $type::query()
                    ->active()
                    ->with('user')
                    ->get()
                    ->mapWithKeys(fn ($profile) => [$profile->id => $profile->user?->name ?? "#{$profile->id}"]);
            })
            ->searchable()
            ->native(false)
            ->live()
            ->required()
            ->extraAttributes(['class' => ResponsiveText::CLASSES]);

        return [$typeField, $idField];
    }
}
