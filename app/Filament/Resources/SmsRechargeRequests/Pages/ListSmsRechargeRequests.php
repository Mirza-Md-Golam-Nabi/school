<?php

namespace App\Filament\Resources\SmsRechargeRequests\Pages;

use App\Actions\Sms\SubmitSmsRechargeRequestAction;
use App\Filament\Resources\SmsRechargeRequests\SmsRechargeRequestResource;
use App\Models\SmsSetting;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListSmsRechargeRequests extends ListRecords
{
    protected static string $resource = SmsRechargeRequestResource::class;

    public function getSubheading(): ?string
    {
        $setting = SmsSetting::current();

        return sprintf(
            'বর্তমান ব্যালেন্স ৳%s · প্রায় %s টি SMS · প্রতি SMS ৳%s',
            number_format($setting->currentBalance(), 2),
            number_format($setting->smsCountFor()),
            rtrim(rtrim(number_format($setting->ratePerSms(), 4), '0'), '.'),
        );
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('requestRecharge')
                ->label('New Recharge Request')
                ->icon(Heroicon::OutlinedPlus)
                ->modalHeading('SMS Recharge Request')
                ->modalDescription('রিকুয়েস্ট পাঠানোর পর টাকা পরিশোধ নিশ্চিত হলে ব্যালেন্স যোগ করে দেওয়া হবে।')
                ->modalSubmitActionLabel('Send Request')
                ->disabled(fn (): bool => SmsSetting::current()->ratePerSms() <= 0)
                ->tooltip(fn (): ?string => SmsSetting::current()->ratePerSms() <= 0 ? 'প্রতি SMS-এর চার্জ এখনো সেট করা হয়নি।' : null)
                ->schema([
                    TextInput::make('amount')
                        ->label('Amount (৳)')
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(1000000)
                        ->required()
                        ->live(debounce: 400)
                        ->helperText(fn (?string $state): string => $this->smsCountText((float) $state)),
                    Textarea::make('note')
                        ->label('Note')
                        ->placeholder('যেমন: bKash TrxID, যোগাযোগের নম্বর')
                        ->rows(2)
                        ->maxLength(500),
                ])
                ->action(function (array $data): void {
                    $request = app(SubmitSmsRechargeRequestAction::class)->handle((float) $data['amount'], $data['note'] ?? null, auth()->user());

                    if ($request->delivered_at !== null) {
                        Notification::make()->success()->title('রিকুয়েস্ট পাঠানো হয়েছে')->body($this->smsCountText((float) $request->amount))->send();

                        return;
                    }

                    Notification::make()
                        ->warning()
                        ->title('রিকুয়েস্ট সংরক্ষিত হয়েছে, কিন্তু পাঠানো যায়নি')
                        ->body('রিকুয়েস্ট লিংক এখনো চালু হয়নি বা পাওয়া যায়নি। পরে তালিকা থেকে "Resend" করুন।')
                        ->persistent()
                        ->send();
                }),
        ];
    }

    private function smsCountText(float $amount): string
    {
        $setting = SmsSetting::current();

        if ($amount <= 0) {
            return sprintf('প্রতি SMS ৳%s', rtrim(rtrim(number_format($setting->ratePerSms(), 4), '0'), '.'));
        }

        return sprintf(
            '৳%s দিয়ে প্রায় %s টি SMS পাঠানো যাবে (বাংলায় ৭০ / ইংরেজিতে ১৬০ অক্ষর = ১ টি SMS)।',
            number_format($amount, 2),
            number_format($setting->smsCountFor($amount)),
        );
    }
}
