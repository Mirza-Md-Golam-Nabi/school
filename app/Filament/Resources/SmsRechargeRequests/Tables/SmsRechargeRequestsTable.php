<?php

namespace App\Filament\Resources\SmsRechargeRequests\Tables;

use App\Actions\Sms\SubmitSmsRechargeRequestAction;
use App\Enums\SmsRechargeRequestStatus;
use App\Filament\Resources\Concerns\ResponsiveText;
use App\Models\SmsRechargeRequest;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class SmsRechargeRequestsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')
                    ->label('Requested')
                    ->dateTime('d M Y, h:i A')
                    ->sortable()
                    ->extraAttributes(['class' => ResponsiveText::CLASSES]),
                TextColumn::make('amount')
                    ->money('BDT')
                    ->extraAttributes(['class' => ResponsiveText::CLASSES]),
                TextColumn::make('estimated_sms')
                    ->label('SMS (approx.)')
                    ->numeric()
                    ->extraAttributes(['class' => ResponsiveText::CLASSES]),
                TextColumn::make('status')
                    ->badge(),
                IconColumn::make('delivered_at')
                    ->label('Sent')
                    ->boolean()
                    ->state(fn (SmsRechargeRequest $record): bool => $record->delivered_at !== null)
                    ->tooltip(fn (SmsRechargeRequest $record): ?string => $record->delivery_error),
                TextColumn::make('credited_amount')
                    ->label('Credited')
                    ->money('BDT')
                    ->placeholder('—')
                    ->extraAttributes(['class' => ResponsiveText::CLASSES]),
                TextColumn::make('requestedBy.name')
                    ->label('Requested By')
                    ->placeholder('—')
                    ->toggleable()
                    ->extraAttributes(['class' => ResponsiveText::CLASSES]),
                TextColumn::make('note')
                    ->limit(40)
                    ->tooltip(fn (SmsRechargeRequest $record): ?string => $record->note)
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('completed_at')
                    ->dateTime('d M Y, h:i A')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(SmsRechargeRequestStatus::class),
            ])
            ->recordActions([
                Action::make('resend')
                    ->label('Resend')
                    ->icon(Heroicon::OutlinedArrowPath)
                    ->iconButton()
                    ->tooltip('রিকুয়েস্টটা আবার পাঠান')
                    ->visible(fn (SmsRechargeRequest $record): bool => $record->status === SmsRechargeRequestStatus::Pending && $record->delivered_at === null)
                    ->action(function (SmsRechargeRequest $record): void {
                        $isDelivered = app(SubmitSmsRechargeRequestAction::class)->deliver($record);

                        $isDelivered
                            ? Notification::make()->success()->title('রিকুয়েস্ট পাঠানো হয়েছে')->send()
                            : Notification::make()->warning()->title('রিকুয়েস্ট পাঠানো যায়নি')->body($record->delivery_error)->send();
                    }),
            ]);
    }
}
