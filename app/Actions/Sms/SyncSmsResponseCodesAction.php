<?php

namespace App\Actions\Sms;

use App\Models\SmsVendorResponseCode;
use Illuminate\Support\Facades\DB;

class SyncSmsResponseCodesAction
{
    /**
     * Makes this school's response code table an exact copy of the list the
     * central app (NextFusion) sent: listed codes are added or updated, any
     * other code is removed. No one inside the school's panel edits them.
     *
     * @param  list<array{driver: string, code: string|int, message: string, is_success: bool}>  $codes
     * @return int how many codes the school now has
     */
    public function handle(array $codes): int
    {
        return DB::transaction(function () use ($codes): int {
            $keptIds = [];

            foreach ($codes as $code) {
                $keptIds[] = SmsVendorResponseCode::updateOrCreate(
                    ['driver' => $code['driver'], 'code' => (string) $code['code']],
                    ['message' => $code['message'], 'is_success' => (bool) $code['is_success']],
                )->id;
            }

            SmsVendorResponseCode::query()
                ->whereNotIn('id', $keptIds)
                ->get()
                ->each(fn (SmsVendorResponseCode $code) => $code->delete());

            return count($keptIds);
        });
    }
}
