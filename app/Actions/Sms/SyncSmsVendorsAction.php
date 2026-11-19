<?php

namespace App\Actions\Sms;

use App\Models\SmsVendor;
use Illuminate\Support\Facades\DB;

class SyncSmsVendorsAction
{
    /**
     * Makes this school's SMS vendors an exact copy of the central app's
     * (NextFusion) list: listed vendors are added or updated, any other
     * vendor is removed (its SMS logs stay, without a vendor). No one inside
     * the school's panel adds or edits vendors.
     *
     * @param  list<array{id: int, name: string, driver: string, api_key: string, sender_id: string, priority: int, is_active: bool}>  $vendors  API keys already opened.
     * @return int how many vendors the school now has
     */
    public function handle(array $vendors): int
    {
        return DB::transaction(function () use ($vendors): int {
            $keptIds = [];

            foreach ($vendors as $vendor) {
                $keptIds[] = SmsVendor::updateOrCreate(
                    ['central_id' => $vendor['id']],
                    [
                        'name' => $vendor['name'],
                        'driver' => $vendor['driver'],
                        'api_key' => $vendor['api_key'],
                        'sender_id' => $vendor['sender_id'],
                        'priority' => $vendor['priority'],
                        'is_active' => $vendor['is_active'],
                    ],
                )->id;
            }

            SmsVendor::query()
                ->whereNotIn('id', $keptIds)
                ->get()
                ->each(fn (SmsVendor $vendor) => $vendor->delete());

            return count($keptIds);
        });
    }
}
