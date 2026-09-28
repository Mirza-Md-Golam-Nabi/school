<?php

namespace App\Console\Commands;

use App\Actions\Attendance\MarkAbsentForDateAction;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Throwable;

#[Signature('attendance:mark-absent {--date= : Date to process (Y-m-d). Defaults to yesterday and today.}')]
#[Description('Marks enrolled people who never punched as absent once the school day is over — skipping weekends, holidays, approved leave and days the devices did not report.')]
class MarkAbsentAttendance extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(MarkAbsentForDateAction $markAbsent): int
    {
        $dates = $this->resolveDates();

        if ($dates === null) {
            $this->error('Invalid --date. Use the Y-m-d format, e.g. 2026-09-28.');

            return self::FAILURE;
        }

        foreach ($dates as $date) {
            $marked = $markAbsent->handle($date);

            $this->line("{$date->toDateString()}: {$marked} marked absent.");
        }

        return self::SUCCESS;
    }

    /**
     * Yesterday is included so a school day whose last punches only synced after
     * midnight (laptop offline in the evening) still gets closed off.
     *
     * @return array<int, Carbon>|null
     */
    private function resolveDates(): ?array
    {
        $option = $this->option('date');

        if ($option === null) {
            return [now()->subDay()->startOfDay(), now()->startOfDay()];
        }

        try {
            return [Carbon::createFromFormat('Y-m-d', (string) $option)->startOfDay()];
        } catch (Throwable) {
            return null;
        }
    }
}
