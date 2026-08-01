<?php

namespace App\Filament\Resources\FundTransactions\Schemas;

use App\Enums\TransactionType;
use App\Filament\Resources\Concerns\ResponsiveText;
use App\Models\TransactionCategory;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;

class FundTransactionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('transaction_category_id')
                    ->label('Category')
                    ->relationship('category', 'name', fn ($query) => $query->where('is_active', true))
                    ->required()
                    ->searchable()
                    ->preload()
                    ->live()
                    ->native(false)
                    ->createOptionForm([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(100)
                            ->placeholder('e.g. Donation, Government Grant, Maintenance, Utility Bill'),
                        Select::make('type')
                            ->options(TransactionType::class)
                            ->required()
                            ->native(false),
                        Toggle::make('is_active')
                            ->default(true),
                    ])
                    ->createOptionAction(fn (Action $action) => $action->modalWidth(Width::Medium))
                    ->extraAttributes(['class' => ResponsiveText::CLASSES]),
                TextEntry::make('category_type')
                    ->label('Type')
                    ->state(function (Get $get): string {
                        $category = TransactionCategory::find($get('transaction_category_id'));

                        return $category?->type->getLabel() ?? '—';
                    })
                    ->extraAttributes(['class' => ResponsiveText::CLASSES]),
                Select::make('school_account_id')
                    ->label('Account')
                    ->relationship('schoolAccount', 'name')
                    ->required()
                    ->searchable()
                    ->preload()
                    ->native(false)
                    ->extraAttributes(['class' => ResponsiveText::CLASSES]),
                TextInput::make('title')
                    ->required()
                    ->maxLength(150)
                    ->placeholder('e.g. স্কুল ভবনের রং করা, জনাব করিমের অনুদান')
                    ->extraAttributes(['class' => ResponsiveText::CLASSES]),
                TextInput::make('amount')
                    ->required()
                    ->numeric()
                    ->minValue(0.01)
                    ->prefix('৳')
                    ->extraAttributes(['class' => ResponsiveText::CLASSES]),
                DatePicker::make('transaction_date')
                    ->required()
                    ->default(now())
                    ->native(false)
                    ->extraAttributes(['class' => ResponsiveText::CLASSES]),
                TextInput::make('party_name')
                    ->label('Vendor / Donor Name')
                    ->nullable()
                    ->extraAttributes(['class' => ResponsiveText::CLASSES]),
                FileUpload::make('attachment_path')
                    ->label('Receipt / Bill (Optional)')
                    ->disk('local')
                    ->directory('fund-transaction-attachments')
                    ->maxSize(5120)
                    ->helperText('আপলোড করা ফাইল প্রাইভেট থাকবে — শুধু admin/staff দেখতে পারবে।')
                    ->nullable(),
                Textarea::make('description')
                    ->nullable()
                    ->rows(2)
                    ->extraAttributes(['class' => ResponsiveText::CLASSES])
                    ->columnSpanFull(),
            ]);
    }
}
