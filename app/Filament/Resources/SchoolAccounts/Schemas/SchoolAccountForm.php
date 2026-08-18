<?php

namespace App\Filament\Resources\SchoolAccounts\Schemas;

use App\Models\FeeType;
use App\Models\SchoolAccount;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;

class SchoolAccountForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(100)
                    ->placeholder('e.g. Main Fund, Exam Fund')
                    ->extraAttributes(['class' => FeeTypeResyncPolicyFields::RESPONSIVE_TEXT]),
                TextInput::make('current_balance')
                    ->label('Opening Balance')
                    ->required()
                    ->numeric()
                    ->minValue(0)
                    ->prefix('৳')
                    ->default(0)
                    ->disabled(fn (string $operation): bool => $operation === 'edit')
                    ->dehydrated(fn (string $operation): bool => $operation === 'create')
                    ->helperText(fn (string $operation): ?string => $operation === 'edit'
                        ? 'Balance is now managed automatically from fee payments.'
                        : null)
                    ->extraAttributes(['class' => FeeTypeResyncPolicyFields::RESPONSIVE_TEXT]),
                Select::make('fee_type_ids')
                    ->label('Fee Types (ফি টাইপ)')
                    ->multiple()
                    ->live()
                    ->options(fn (?SchoolAccount $record) => FeeType::query()
                        ->where(fn ($query) => $query
                            ->whereNull('school_account_id')
                            ->when($record, fn ($query) => $query->orWhere('school_account_id', $record->id)))
                        ->orderBy('name')
                        ->pluck('name', 'id'))
                    ->helperText(self::bilingualHelperText(
                        en: 'Payments collected for the selected fee types will automatically post into this fund. Fee types already linked to another fund are not shown here — use the "+" action on the accounts list to move one.',
                        bn: 'নির্বাচিত fee type-গুলোর জন্য সংগৃহীত payment স্বয়ংক্রিয়ভাবে এই fund-এ যোগ হয়ে যাবে। যেসব fee type ইতিমধ্যে অন্য কোনো fund-এর সাথে যুক্ত আছে, সেগুলো এখানে দেখানো হবে না — একটাকে সরাতে account list-এর "+" অ্যাকশন ব্যবহার করুন।',
                    ))
                    ->extraAttributes(['class' => FeeTypeResyncPolicyFields::RESPONSIVE_TEXT]),
                ...FeeTypeResyncPolicyFields::make('fee_type_ids'),
            ]);
    }

    /**
     * Renders helper text with a small "bn"/"en" toggle button so the field
     * doesn't have to permanently show both languages at once.
     */
    private static function bilingualHelperText(string $en, string $bn): HtmlString
    {
        $en = e($en);
        $bn = e($bn);

        return new HtmlString(<<<HTML
            <span x-data="{ lang: 'en' }" class="inline-flex flex-wrap items-baseline gap-x-1.5">
                <span x-show="lang === 'en'" x-cloak>{$en}</span>
                <span x-show="lang === 'bn'" x-cloak>{$bn}</span>
                <button
                    type="button"
                    x-on:click="lang = (lang === 'en' ? 'bn' : 'en')"
                    x-text="lang === 'en' ? 'bn' : 'en'"
                    class="shrink-0 rounded px-1 py-0.5 text-[10px] font-semibold uppercase text-primary-600 underline hover:text-primary-500 dark:text-primary-400 dark:hover:text-primary-300"
                ></button>
            </span>
            HTML);
    }
}
