<?php

namespace App\Http\Controllers;

use App\Actions\Sms\CreditSmsBalanceAction;
use App\Http\Requests\CreditSmsBalanceRequest;
use App\Models\SmsSetting;
use Illuminate\Http\JsonResponse;

class CentralSmsCreditController extends Controller
{
    /**
     * Adds a confirmed SMS recharge to this school's balance.
     */
    public function __invoke(CreditSmsBalanceRequest $request, CreditSmsBalanceAction $action): JsonResponse
    {
        $result = $action->handle(
            (float) $request->validated('amount'),
            $request->validated('reference'),
            $request->validated('recharge_request_id'),
            $request->validated('note'),
        );

        $setting = SmsSetting::current();

        return response()->json([
            'credited' => ! $result['already_credited'],
            'already_credited' => $result['already_credited'],
            'transaction_id' => $result['transaction']->id,
            'balance' => $setting->currentBalance(),
            'sms_count' => $setting->smsCountFor(),
        ], $result['already_credited'] ? 200 : 201);
    }
}
