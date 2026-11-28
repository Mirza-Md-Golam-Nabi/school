<?php

namespace App\Console\Commands;

use App\Actions\Leave\RecordLeaveAttendanceAction;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('attendance:record-leave')]
#[Description('Writes Leave into attendance for approved leave days once each day is over (yesterday and today), unless the person attended.')]
class SyncLeaveAttendance extends Command
{
    /**
     * Yesterday is included so a day whose last punches only synced after
     * midnight is still closed off correctly.
     */
    public function handle(RecordLeaveAttendanceAction $recordLeave): int
    {
        foreach ([now()->subDay()->startOfDay(), now()->startOfDay()] as $date) {
            $recorded = $recordLeave->handle($date);

            $this->line("{$date->toDateString()}: {$recorded} marked leave.");
        }

        return self::SUCCESS;
    }
}
