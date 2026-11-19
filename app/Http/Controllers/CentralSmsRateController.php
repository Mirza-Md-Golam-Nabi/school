<?php

namespace App\Http\Controllers;

use App\Actions\Sms\UpdateSmsRateAction;
use App\Http\Requests\UpdateSmsRateRequest;
use Illuminate\Http\JsonResponse;

class CentralSmsRateController extends Controller
{
    /**
     * Applies the per-SMS charge the central app set for this school.
     */
    public function __invoke(UpdateSmsRateRequest $request, UpdateSmsRateAction $action): JsonResponse
    {
        $setting = $action->handle((float) $request->validated('rate_per_sms'));

        return response()->json([
            'rate_per_sms' => $setting->ratePerSms(),
            'balance' => $setting->currentBalance(),
            'sms_count' => $setting->smsCountFor(),
        ]);
    }
}
