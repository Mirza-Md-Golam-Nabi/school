<?php

namespace App\Filament\Resources\SmsBalanceTransactions\Pages;

use App\Filament\Resources\SmsBalanceTransactions\SmsBalanceTransactionResource;
use App\Models\SmsSetting;
use Filament\Resources\Pages\ListRecords;

class ListSmsBalanceTransactions extends ListRecords
{
    protected static string $resource = SmsBalanceTransactionResource::class;

    public function getSubheading(): ?string
    {
        $setting = SmsSetting::current();

        return sprintf('বর্তমান ব্যালেন্স ৳%s · প্রায় %s টি SMS', number_format($setting->currentBalance(), 2), number_format($setting->smsCountFor()));
    }
}
