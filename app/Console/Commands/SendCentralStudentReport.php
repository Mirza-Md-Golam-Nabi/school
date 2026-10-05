<?php

namespace App\Console\Commands;

use App\Actions\Central\SendStudentCountReportAction;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('central:report-students')]
#[Description('Send this school\'s total student count to the central app.')]
class SendCentralStudentReport extends Command
{
    public function handle(SendStudentCountReportAction $action): int
    {
        try {
            $report = $action->handle();
        } catch (Throwable $exception) {
            report($exception);

            $this->error("Student report was not sent: {$exception->getMessage()}");

            return self::FAILURE;
        }

        $this->info("Reported {$report['total_students']} student(s) to the central app.");

        return self::SUCCESS;
    }
}
