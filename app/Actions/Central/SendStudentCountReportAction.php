<?php

namespace App\Actions\Central;

use App\Support\CentralSignature;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class SendStudentCountReportAction
{
    public function __construct(private BuildStudentCountReportAction $buildReport) {}

    /**
     * Student সংখ্যার রিপোর্ট সেন্ট্রাল অ্যাপে পাঠায়। Body-টা secret দিয়ে সই করা
     * থাকে (X-Signature), যাতে সেন্ট্রাল নিশ্চিত হতে পারে রিপোর্টটা এই স্কুল থেকেই এসেছে।
     *
     * @return array{school_id: string, total_students: int, reported_at: string}
     *
     * @throws RuntimeException when this installation is not configured for central reporting
     * @throws ConnectionException|RequestException when the central app cannot be reached or rejects the report
     */
    public function handle(): array
    {
        if (blank(config('central.url')) || ! CentralSignature::isConfigured()) {
            throw new RuntimeException('Central reporting is not configured — set CENTRAL_URL, CENTRAL_SCHOOL_ID and CENTRAL_SECRET in .env.');
        }

        $report = $this->buildReport->handle();
        $body = json_encode($report);
        $timestamp = (string) now()->timestamp;

        Http::withHeaders([
            'X-School-Id' => $report['school_id'],
            'X-Timestamp' => $timestamp,
            'X-Signature' => CentralSignature::sign($timestamp, $body),
        ])
            ->withBody($body, 'application/json')
            ->acceptJson()
            ->connectTimeout(10)
            ->timeout(30)
            // A rejected report (4xx) will be rejected again — only retry when the
            // central app was unreachable or failed on its own side.
            ->retry([1000, 5000, 15000], when: fn (Throwable $exception): bool => $exception instanceof ConnectionException
                || ($exception instanceof RequestException && $exception->response->serverError()))
            ->post(config('central.url').config('central.report_path'))
            ->throw();

        return $report;
    }
}
