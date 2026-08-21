<?php

namespace App\Http\Controllers;

use App\Http\Requests\AcknowledgePushNotificationRequest;
use App\Models\PushNotificationDelivery;
use Illuminate\Http\JsonResponse;

class PushNotificationDeliveryController extends Controller
{
    public function acknowledge(AcknowledgePushNotificationRequest $request): JsonResponse
    {
        $delivery = PushNotificationDelivery::where('token_hash', hash('sha256', $request->string('delivery_token')->toString()))->first();

        $delivery?->markReceived();

        return response()->json(['status' => 'acknowledged']);
    }
}
