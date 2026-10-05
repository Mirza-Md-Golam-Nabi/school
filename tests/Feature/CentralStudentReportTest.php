<?php

use App\Console\Commands\SendCentralStudentReport;
use App\Enums\Gender;
use App\Enums\StudentStatus;
use App\Enums\UserType;
use App\Models\StudentProfile;
use App\Models\User;
use App\Support\CentralSignature;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    config([
        'central.url' => 'https://central.test',
        'central.report_path' => '/api/school-reports',
        'central.school_id' => 'school-42',
        'central.secret' => 'test-secret',
    ]);
});

function makeCentralReportTestStudents(int $count, StudentStatus $status = StudentStatus::Active): void
{
    foreach (range(1, $count) as $rollNo) {
        StudentProfile::create([
            'user_id' => User::factory()->create(['user_type' => UserType::Student])->id,
            'roll_no' => $rollNo,
            'session_year' => now()->year,
            'gender' => Gender::Male,
            'status' => $status,
        ]);
    }
}

/**
 * @return array<string, string>
 */
function centralPullHeaders(?int $timestamp = null, string $secret = 'test-secret', string $schoolId = 'school-42'): array
{
    $timestamp = (string) ($timestamp ?? now()->timestamp);

    return [
        'X-Timestamp' => $timestamp,
        'X-Signature' => hash_hmac('sha256', "{$timestamp}.{$schoolId}", $secret),
    ];
}

it('pushes the signed active student count to the central app', function () {
    Http::fake(['central.test/*' => Http::response(['ok' => true])]);

    makeCentralReportTestStudents(3);
    makeCentralReportTestStudents(2, StudentStatus::Graduated);

    $this->artisan('central:report-students')
        ->expectsOutputToContain('Reported 3 student(s)')
        ->assertSuccessful();

    Http::assertSentCount(1);
    Http::assertSent(function (Request $request): bool {
        $timestamp = $request->header('X-Timestamp')[0];

        return $request->url() === 'https://central.test/api/school-reports'
            && $request->method() === 'POST'
            && $request->header('X-School-Id')[0] === 'school-42'
            && $request->header('X-Signature')[0] === hash_hmac('sha256', "{$timestamp}.{$request->body()}", 'test-secret')
            && array_keys($request->data()) === ['school_id', 'total_students', 'reported_at']
            && $request['school_id'] === 'school-42'
            && $request['total_students'] === 3;
    });
});

it('fails without calling the central app when reporting is not configured', function (string $missing) {
    Http::fake();
    config([$missing => null]);

    $this->artisan('central:report-students')
        ->expectsOutputToContain('not configured')
        ->assertFailed();

    Http::assertNothingSent();
})->with(['central.url', 'central.school_id', 'central.secret']);

it('fails when the central app rejects the report', function () {
    Http::fake(['central.test/*' => Http::response(['message' => 'Bad signature'], 401)]);

    $this->artisan('central:report-students')->assertFailed();
});

it('schedules the report for midnight on the 15th of every month', function () {
    $event = collect(app(Schedule::class)->events())
        ->first(fn ($event): bool => str_contains($event->command ?? '', 'central:report-students'));

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('0 0 15 * *')
        ->and(app(SendCentralStudentReport::class)->getName())->toBe('central:report-students');
});

it('returns the student count when the central app pulls with a valid signature', function () {
    makeCentralReportTestStudents(4);
    makeCentralReportTestStudents(1, StudentStatus::Dropped);

    $this->getJson('/api/central/student-report', centralPullHeaders())
        ->assertOk()
        ->assertJsonPath('school_id', 'school-42')
        ->assertJsonPath('total_students', 4)
        ->assertJsonStructure(['school_id', 'total_students', 'reported_at']);
});

it('rejects a pull request that is unsigned, wrongly signed, stale or meant for another school', function (array $headers) {
    makeCentralReportTestStudents(1);

    $this->getJson('/api/central/student-report', $headers)
        ->assertUnauthorized()
        ->assertJsonMissingPath('total_students');
})->with([
    'no headers' => [[]],
    'wrong secret' => fn () => centralPullHeaders(secret: 'someone-else'),
    'another school' => fn () => centralPullHeaders(schoolId: 'school-99'),
    'ten minutes old' => fn () => centralPullHeaders(now()->subMinutes(10)->timestamp),
    'non-numeric timestamp' => [['X-Timestamp' => 'now', 'X-Signature' => 'abc']],
]);

it('rejects every pull request while this school has no secret configured', function () {
    config(['central.secret' => null]);

    $this->getJson('/api/central/student-report', centralPullHeaders(secret: ''))
        ->assertUnauthorized();

    expect(CentralSignature::isConfigured())->toBeFalse();
});
