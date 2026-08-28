<?php

namespace App\Http\Controllers;

use App\Http\Requests\DestroyPushSubscriptionRequest;
use App\Http\Requests\StorePushSubscriptionRequest;
use Illuminate\Http\JsonResponse;

class PushSubscriptionController extends Controller
{
    public function store(StorePushSubscriptionRequest $request): JsonResponse
    {
        $request->user()->updatePushSubscription(
            endpoint: $request->string('endpoint')->toString(),
            key: $request->string('keys.p256dh')->toString(),
            token: $request->string('keys.auth')->toString(),
        );

        return response()->json(['status' => 'subscribed']);
    }

    public function destroy(DestroyPushSubscriptionRequest $request): JsonResponse
    {
        $request->user()->deletePushSubscription($request->string('endpoint')->toString());

        return response()->json(['status' => 'unsubscribed']);
    }
}
